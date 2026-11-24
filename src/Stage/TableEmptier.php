<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Stage;

use Drupal\Core\Database\Connection;
use Psr\Log\LoggerInterface;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\Defaults;

/**
 * Truncates the tables that hold personal data or rebuildable copies.
 */
final class TableEmptier implements Stage {

  public function __construct(
    private readonly Connection $connection,
    private readonly LoggerInterface $logger,
    private readonly Configuration $configuration,
  ) {
  }

  public function validate(): array {
    return self::problems($this->configuration, $this->existingTables());
  }

  public function confirmation(): ?string {
    return EmptyingPlan::describe('tables', self::tablesToEmpty($this->configuration, $this->existingTables()), Defaults::TABLE_PREFIXES);
  }

  public function run(): void {
    $sorted = self::tablesToEmpty($this->configuration, $this->existingTables());
    foreach ($sorted['emptied'] as $table) {
      $this->connection->truncate($table)->execute();
    }
    EmptyingPlan::log($this->logger, 'tables', $sorted);
  }

  /**
   * @param list<string> $existingTables
   *
   * @return array{errors: list<string>, warnings: list<string>}
   */
  public static function problems(Configuration $configuration, array $existingTables): array {
    $errors = [];
    $warnings = [];
    $defaultTables = self::defaultTables($existingTables);
    foreach ($configuration->allowlistTables as $table) {
      if (!in_array($table, $existingTables, TRUE)) {
        $warnings[] = "--allowlist-tables: no table named \"$table\"; ignored.";
      }
      elseif (!in_array($table, $defaultTables, TRUE)) {
        $errors[] = "--allowlist-tables: \"$table\" is not in the default list of tables to empty.";
      }
    }
    foreach ($configuration->emptyTables as $table) {
      if (in_array($table, $configuration->allowlistTables, TRUE)) {
        $errors[] = "\"$table\" is in both --allowlist-tables and --empty-tables.";
      }
      if (!in_array($table, $existingTables, TRUE)) {
        $warnings[] = "--empty-tables: no table named \"$table\"; ignored.";
      }
    }
    return ['errors' => $errors, 'warnings' => $warnings];
  }

  /**
   * The default tables that exist, including those found by prefix.
   *
   * @param list<string> $existingTables
   *
   * @return list<string>
   */
  public static function defaultTables(array $existingTables): array {
    $tables = Defaults::tables();
    foreach ($existingTables as $table) {
      foreach (Defaults::TABLE_PREFIXES as $prefix) {
        if (str_starts_with($table, $prefix)) {
          $tables[] = $table;
        }
      }
    }
    return array_values(array_unique($tables));
  }

  /**
   * Sorts tables into emptied, kept and skipped because they do not exist.
   *
   * @param list<string> $existingTables
   *
   * @return array{emptied: list<string>, kept: list<string>, missing: list<string>}
   */
  public static function tablesToEmpty(Configuration $configuration, array $existingTables): array {
    return EmptyingPlan::sort(self::defaultTables($existingTables), $configuration->allowlistTables, $configuration->emptyTables, $existingTables);
  }

  /**
   * @return list<string>
   */
  private function existingTables(): array {
    return array_values($this->connection->schema()->findTables('%'));
  }

}
