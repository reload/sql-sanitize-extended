<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Stage;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Psr\Log\LoggerInterface;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\SqlContentEntityTypes;

/**
 * Deletes the rows of the configured fields, per bundle, with SQL.
 */
final class FieldEmptier implements Stage {

  public const BASE_FIELD = 'base';

  public const WITHOUT_OWN_TABLES = 'without own tables';

  public const WITH_OWN_TABLES = 'with own tables';

  public function __construct(
    private readonly Connection $connection,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly SqlContentEntityTypes $contentEntityTypes,
    private readonly LoggerInterface $logger,
    private readonly Configuration $configuration,
  ) {
  }

  public function validate(): array {
    return self::problems($this->configuration, $this->fieldKindByFieldKey());
  }

  public function confirmation(): ?string {
    $fieldKeys = array_keys($this->fieldKindByFieldKey());
    return $fieldKeys === [] ? NULL : 'Empty fields: ' . implode(', ', $fieldKeys) . '.';
  }

  public function run(): void {
    foreach (array_keys($this->fieldKindByFieldKey()) as $fieldKey) {
      [$entityTypeId, $bundle, $fieldName] = explode('.', $fieldKey);
      $mapping = $this->contentEntityTypes->tableMapping($entityTypeId);
      foreach ($mapping->getAllFieldTableNames($fieldName) as $table) {
        $count = $this->connection->delete($table)->condition('bundle', $bundle)->execute();
        $this->logger->notice('Emptied field {field}: {table}, {count} rows', ['field' => $fieldKey, 'table' => $table, 'count' => $count]);
      }
    }
  }

  /**
   * @param array<string, string> $fieldKindByFieldKey
   *   The fields named in `--empty-fields` that exist, keyed
   *   `<entity type>.<bundle>.<field>`, each with one of the field kind
   *   constants.
   *
   * @return array{errors: list<string>, warnings: list<string>}
   */
  public static function problems(Configuration $configuration, array $fieldKindByFieldKey): array {
    $errors = [];
    $warnings = [];
    foreach ($configuration->emptyFields as $fieldKey) {
      $kind = $fieldKindByFieldKey[$fieldKey] ?? NULL;
      if (count(explode('.', $fieldKey)) !== 3) {
        $errors[] = "--empty-fields: \"$fieldKey\" must be <entity type>.<bundle>.<field>.";
      }
      elseif ($kind === NULL) {
        $warnings[] = "--empty-fields: no field \"$fieldKey\"; ignored.";
      }
      elseif ($kind === self::BASE_FIELD) {
        $errors[] = "--empty-fields: \"$fieldKey\" is a base field; only configurable fields are supported.";
      }
      elseif ($kind === self::WITHOUT_OWN_TABLES) {
        $errors[] = "--empty-fields: \"$fieldKey\" is computed or has custom storage, so it has no tables of its own to empty.";
      }
    }
    return ['errors' => $errors, 'warnings' => $warnings];
  }

  /**
   * Only the fields named in `--empty-fields` that exist.
   *
   * @return array<string, string>
   */
  private function fieldKindByFieldKey(): array {
    $contentEntityTypes = $this->contentEntityTypes->all();
    $fieldKindByFieldKey = [];
    foreach ($this->configuration->emptyFields as $fieldKey) {
      $parts = explode('.', $fieldKey);
      if (count($parts) !== 3 || !isset($contentEntityTypes[$parts[0]])) {
        continue;
      }
      [$entityTypeId, $bundle, $fieldName] = $parts;
      $definitions = $this->entityFieldManager->getFieldDefinitions($entityTypeId, $bundle);
      if (isset($definitions[$fieldName])) {
        $fieldKindByFieldKey[$fieldKey] = $this->fieldKind($entityTypeId, $definitions[$fieldName]);
      }
    }
    return $fieldKindByFieldKey;
  }

  private function fieldKind(string $entityTypeId, FieldDefinitionInterface $fieldDefinition): string {
    $storageDefinition = $fieldDefinition->getFieldStorageDefinition();
    return match (TRUE) {
      $storageDefinition->isBaseField() => self::BASE_FIELD,
      $fieldDefinition->isComputed(), $storageDefinition->hasCustomStorage(), !$this->contentEntityTypes->tableMapping($entityTypeId)->requiresDedicatedTableStorage($storageDefinition) => self::WITHOUT_OWN_TABLES,
      default => self::WITH_OWN_TABLES,
    };
  }

}
