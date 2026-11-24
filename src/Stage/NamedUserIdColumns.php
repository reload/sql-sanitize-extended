<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Stage;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\sql_sanitize_extended\SqlContentEntityTypes;

/**
 * Finds integer columns that look like user ids by name, in known tables.
 */
final class NamedUserIdColumns {

  private const INTEGER_SCHEMA_TYPES = ['int', 'serial'];

  public function __construct(
    private readonly Connection $connection,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly SqlContentEntityTypes $contentEntityTypes,
  ) {
  }

  /**
   * Whether a column name looks like it holds a user id.
   */
  public static function isUserIdColumnName(string $column): bool {
    return $column === 'uid' || $column === 'user_id' || str_ends_with($column, '_uid');
  }

  /**
   * Columns of tables that `hook_schema()` declares and of entity tables.
   *
   * @return list<array{table: string, column: string}>
   */
  public function all(): array {
    return [...$this->ofDeclaredTables(), ...$this->ofEntityTables()];
  }

  /**
   * @return array<string, array<string, array<string, mixed>>>
   *   Field specifications by column name by table name.
   */
  public function declaredTables(): array {
    $declaredTables = [];
    foreach (array_keys($this->moduleHandler->getModuleList()) as $module) {
      $this->moduleHandler->loadInclude($module, 'install');
      foreach ($this->moduleHandler->invoke($module, 'schema') ?? [] as $table => $specification) {
        $declaredTables[$table] = $specification['fields'] ?? [];
      }
    }
    return $declaredTables;
  }

  /**
   * @return list<string>
   */
  public function entityTables(): array {
    $tables = [];
    foreach ($this->contentEntityTypes->ids() as $entityTypeId) {
      $mapping = $this->contentEntityTypes->tableMapping($entityTypeId);
      $tables = [...$tables, ...$mapping->getTableNames()];
    }
    return array_values(array_unique($tables));
  }

  /**
   * Integer user id columns of tables that `hook_schema()` declares and that
   * exist.
   *
   * @return list<array{table: string, column: string}>
   */
  private function ofDeclaredTables(): array {
    $schema = $this->connection->schema();
    $columns = [];
    foreach ($this->declaredTables() as $table => $fields) {
      if (!$schema->tableExists($table)) {
        continue;
      }
      foreach ($fields as $column => $specification) {
        if (in_array($specification['type'] ?? '', self::INTEGER_SCHEMA_TYPES, TRUE) && self::isUserIdColumnName($column)) {
          $columns[] = ['table' => $table, 'column' => $column];
        }
      }
    }
    return $columns;
  }

  /**
   * Integer user id columns of entity field storage that the finder skips.
   *
   * The finder only knows entity reference fields; a plain integer `uid`
   * field is found by name instead. The id key is never a user id.
   *
   * @return list<array{table: string, column: string}>
   */
  private function ofEntityTables(): array {
    $schema = $this->connection->schema();
    $columns = [];
    foreach ($this->contentEntityTypes->all() as $entityTypeId => $entityType) {
      $mapping = $this->contentEntityTypes->tableMapping($entityTypeId);
      foreach ($this->entityFieldManager->getFieldStorageDefinitions($entityTypeId) as $fieldName => $storageDefinition) {
        if ($storageDefinition->hasCustomStorage()) {
          continue;
        }
        $tables = $mapping->getAllFieldTableNames($fieldName);
        foreach ($storageDefinition->getSchema()['columns'] as $property => $specification) {
          $column = $mapping->getFieldColumnName($storageDefinition, $property);
          if (!in_array($specification['type'] ?? '', self::INTEGER_SCHEMA_TYPES, TRUE) || $column === $entityType->getKey('id') || !self::isUserIdColumnName($column)) {
            continue;
          }
          foreach ($tables as $table) {
            if ($schema->fieldExists($table, $column)) {
              $columns[] = ['table' => $table, 'column' => $column];
            }
          }
        }
      }
    }
    return $columns;
  }

}
