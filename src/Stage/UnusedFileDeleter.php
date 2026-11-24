<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Stage;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Psr\Log\LoggerInterface;
use Drupal\sql_sanitize_extended\BatchDeleter;
use Drupal\sql_sanitize_extended\Fetch;
use Drupal\sql_sanitize_extended\SqlContentEntityTypes;

/**
 * Deletes every file that no field and no other module uses, on disk too.
 *
 * Files keep the names and paths of the people who uploaded them. The
 * `file_usage` counts are unreliable (drupal.org #2821423, #3364744), so the
 * fields are read directly; `file_usage` rows of the `file` module mirror
 * those references and are ignored. Rows of other modules (editor, webform)
 * still protect their files.
 */
final class UnusedFileDeleter implements Stage {

  public function __construct(
    private readonly Connection $connection,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly FieldTypePluginManagerInterface $fieldTypeManager,
    private readonly SqlContentEntityTypes $contentEntityTypes,
    private readonly BatchDeleter $batchDeleter,
    private readonly LoggerInterface $logger,
  ) {
  }

  public function validate(): array {
    return ['errors' => [], 'warnings' => []];
  }

  public function confirmation(): string {
    return 'Delete files no file-referencing field or other module uses, from disk too.';
  }

  public function run(): void {
    $schema = $this->connection->schema();
    if (!$schema->tableExists('file_managed') || !$schema->tableExists('file_usage')) {
      return;
    }
    $fileReferences = $this->fileReferences();
    $count = $this->batchDeleter->deleteBatches('file', fn () => Fetch::column($this->unusedFileIds($fileReferences)), 'files');
    $this->logger->notice('Deleted {count} files that no field or other module uses', ['count' => $count]);
  }

  /**
   * @param list<array{table: string, column: string}> $fileReferences
   */
  private function unusedFileIds(array $fileReferences): SelectInterface {
    $query = $this->connection->select('file_managed', 'f')->fields('f', ['fid']);
    foreach ($fileReferences as $fileReference) {
      $query->notExists($this->referenceTo($fileReference['table'], $fileReference['column']));
    }
    $usage = $this->referenceTo('file_usage', 'fid')->condition('r.module', 'file', '<>');
    return $query->notExists($usage)->orderBy('f.fid')->range(0, BatchDeleter::BATCH_SIZE);
  }

  private function referenceTo(string $table, string $column): SelectInterface {
    $query = $this->connection->select($table, 'r');
    $query->addExpression('1');
    return $query->where("r.$column = f.fid");
  }

  /**
   * The data and revision tables of every field that references files.
   *
   * @return list<array{table: string, column: string}>
   */
  private function fileReferences(): array {
    $fileReferences = [];
    foreach ($this->contentEntityTypes->ids() as $entityTypeId) {
      $mapping = $this->contentEntityTypes->tableMapping($entityTypeId);
      foreach ($this->entityFieldManager->getFieldStorageDefinitions($entityTypeId) as $fieldName => $storageDefinition) {
        $itemClass = $this->fieldTypeManager->getDefinition($storageDefinition->getType(), FALSE)['class'] ?? NULL;
        if ($storageDefinition->hasCustomStorage() || $itemClass === NULL || !is_a($itemClass, EntityReferenceItem::class, TRUE) || $storageDefinition->getSetting('target_type') !== 'file') {
          continue;
        }
        $column = $mapping->getFieldColumnName($storageDefinition, 'target_id');
        foreach ($mapping->getAllFieldTableNames($fieldName) as $table) {
          $fileReferences[] = ['table' => $table, 'column' => $column];
        }
      }
    }
    return $fileReferences;
  }

}
