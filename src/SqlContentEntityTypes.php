<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;

/**
 * The content entity types that live in SQL tables, and where.
 */
final class SqlContentEntityTypes {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * @return array<string, ContentEntityTypeInterface>
   */
  public function all(): array {
    $entityTypes = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entityTypeId => $entityType) {
      if ($entityType instanceof ContentEntityTypeInterface && $this->entityTypeManager->getStorage($entityTypeId) instanceof SqlEntityStorageInterface) {
        $entityTypes[$entityTypeId] = $entityType;
      }
    }
    return $entityTypes;
  }

  /**
   * @return list<string>
   */
  public function ids(): array {
    return array_keys($this->all());
  }

  public function tableMapping(string $entityTypeId): DefaultTableMapping {
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    if (!$storage instanceof SqlEntityStorageInterface) {
      throw new \RuntimeException("Entity type $entityTypeId has no SQL storage.");
    }
    $mapping = $storage->getTableMapping();
    if (!$mapping instanceof DefaultTableMapping) {
      throw new \RuntimeException("Entity type $entityTypeId has an unsupported table mapping.");
    }
    return $mapping;
  }

  /**
   * @return list<string>
   */
  public function baseAndDataTables(string $entityTypeId): array {
    $mapping = $this->tableMapping($entityTypeId);
    return array_values(array_filter([$mapping->getBaseTable(), $mapping->getDataTable()]));
  }

}
