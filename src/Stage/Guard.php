<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Stage;

use Drupal\Core\Database\Connection;
use Psr\Log\LoggerInterface;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\Fetch;
use Drupal\sql_sanitize_extended\UserReference\Column;
use Drupal\sql_sanitize_extended\UserReference\Finder;

/**
 * Fails the job if any user id column points to a user that does not exist.
 *
 * Run it after the steps.
 */
final class Guard {

  /**
   * The user entity's own id, which is no reference to a user.
   */
  private const USER_ID_COLUMNS = ['users.uid', 'users_field_data.uid'];

  public function __construct(
    private readonly Connection $connection,
    private readonly Finder $userReferenceFinder,
    private readonly NamedUserIdColumns $namedUserIdColumns,
    private readonly LoggerInterface $logger,
    private readonly Configuration $configuration,
  ) {
  }

  public function run(): void {
    $guardColumns = self::guardColumns($this->userReferenceFinder->all(), $this->namedUserIdColumns->all(), $this->configuration->allowlistTables);
    $counts = [];
    foreach ($guardColumns as $column) {
      $query = $this->connection->select($column->userIdTable, 't')
        ->condition("t.$column->userIdColumn", 0, '<>');
      Finder::restrictToBundles($query, $column);
      $query->condition("t.$column->userIdColumn", $this->connection->select('users', 'u')->fields('u', ['uid']), 'NOT IN');
      $counts[] = [
        'table' => $column->userIdTable,
        'column' => $column->userIdColumn,
        'count' => Fetch::count($query),
      ];
    }
    $violations = self::guardViolations($counts);
    if ($violations !== []) {
      throw new \RuntimeException("Personal data may remain:\n" . implode("\n", $violations));
    }
    $this->logger->notice('Guard checked {count} columns', ['count' => count($guardColumns)]);
    $this->logUncheckedTables();
  }

  /**
   * Columns the guard checks: entity columns plus user id columns by name.
   *
   * Entity columns keep their bundle condition, so a column that holds user
   * ids for some bundles only is not checked for the others.
   *
   * @param list<Column> $entityColumns
   * @param list<array{table: string, column: string}> $namedColumns
   * @param list<string> $keptTables
   *   Tables the options allowlist; exempt from the guard, as is the
   *   user entity's own id column.
   *
   * @return list<Column>
   */
  public static function guardColumns(array $entityColumns, array $namedColumns, array $keptTables): array {
    $isExempt = fn (string $table, string $column) => in_array($table, $keptTables, TRUE) || in_array("$table.$column", self::USER_ID_COLUMNS, TRUE);
    $columns = [];
    $entityColumnKeys = [];
    foreach ($entityColumns as $entityColumn) {
      $entityColumnKeys[$entityColumn->userIdTable . '.' . $entityColumn->userIdColumn] = TRUE;
      if (!$isExempt($entityColumn->userIdTable, $entityColumn->userIdColumn)) {
        $columns[] = $entityColumn;
      }
    }
    foreach ($namedColumns as $namedColumn) {
      $key = $namedColumn['table'] . '.' . $namedColumn['column'];
      if (!isset($entityColumnKeys[$key]) && !$isExempt($namedColumn['table'], $namedColumn['column'])) {
        $columns[] = new Column(NULL, $namedColumn['table'], $namedColumn['column'], Column::BY_NAME, NULL, NULL, NULL);
        $entityColumnKeys[$key] = TRUE;
      }
    }
    return $columns;
  }

  /**
   * @param list<array{table: string, column: string, count: int}> $counts
   *   Rows per column pointing to a user that does not exist.
   *
   * @return list<string>
   */
  public static function guardViolations(array $counts): array {
    $violations = [];
    foreach ($counts as $count) {
      if ($count['count'] > 0) {
        $violations[] = sprintf('%s.%s: %d rows point to a user that does not exist.', $count['table'], $count['column'], $count['count']);
      }
    }
    return $violations;
  }

  /**
   * Tables the guard cannot know hold user ids: no entity, schema or rule.
   */
  private function logUncheckedTables(): void {
    $existingTables = array_values($this->connection->schema()->findTables('%'));
    $sorted = TableEmptier::tablesToEmpty($this->configuration, $existingTables);
    $unchecked = array_diff($existingTables, $this->namedUserIdColumns->entityTables(), array_keys($this->namedUserIdColumns->declaredTables()), $sorted['emptied'], $sorted['kept']);
    $this->logger->notice('Tables the guard could not check: {tables}', ['tables' => implode(', ', $unchecked) ?: 'none']);
  }

}
