<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\UserReference;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\sql_sanitize_extended\SqlContentEntityTypes;

/**
 * Reads every stored column of an entity reference field to users.
 */
final class CandidateReader {

  public function __construct(
    private readonly Connection $connection,
    private readonly SqlContentEntityTypes $contentEntityTypes,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {
  }

  /**
   * @return array<string, list<string>>
   */
  public function bundlesByEntityTypeId(): array {
    $bundles = [];
    foreach ($this->entityFieldManager->getFieldMap() as $entityTypeId => $fields) {
      $bundleNames = [];
      foreach ($fields as $field) {
        foreach ($field['bundles'] as $bundle) {
          $bundleNames[$bundle] = $bundle;
        }
      }
      $bundles[$entityTypeId] = array_values($bundleNames);
    }
    return $bundles;
  }

  /**
   * @param array<string, list<string>> $bundlesByEntityTypeId
   *
   * @return list<Candidate>
   */
  public function candidates(array $bundlesByEntityTypeId): array {
    $candidates = [];
    foreach ($this->contentEntityTypes->all() as $entityTypeId => $entityType) {
      $mapping = $this->contentEntityTypes->tableMapping($entityTypeId);
      $idFieldName = (string) $entityType->getKey('id');
      foreach ($bundlesByEntityTypeId[$entityTypeId] ?? [] as $bundle) {
        foreach ($this->entityFieldManager->getFieldDefinitions($entityTypeId, $bundle) as $fieldName => $definition) {
          $storageDefinition = $definition->getFieldStorageDefinition();
          if ($definition->isComputed() || $storageDefinition->hasCustomStorage() || !is_a($definition->getItemDefinition()->getClass(), EntityReferenceItem::class, TRUE)) {
            continue;
          }
          if ($definition->getSetting('target_type') !== 'user') {
            continue;
          }
          $tables = $mapping->getAllFieldTableNames($fieldName);
          $column = $mapping->getFieldColumnName($storageDefinition, 'target_id');
          $isDedicated = $mapping->requiresDedicatedTableStorage($storageDefinition);
          foreach ($tables as $table) {
            $bundleColumn = $isDedicated ? 'bundle' : ($entityType->getKey('bundle') ?: NULL);
            $candidates[] = new Candidate(
              $entityTypeId,
              $bundle,
              $fieldName,
              $storageDefinition->isBaseField(),
              $definition->isRequired(),
              $table,
              $column,
              $idFieldName,
              $isDedicated ? 'entity_id' : $idFieldName,
              $bundleColumn !== NULL && $this->connection->schema()->fieldExists($table, $bundleColumn) ? $bundleColumn : NULL,
            );
          }
        }
      }
    }
    return $candidates;
  }

}
