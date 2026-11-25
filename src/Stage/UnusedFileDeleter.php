<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Stage;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Psr\Log\LoggerInterface;
use Drupal\sql_sanitize_extended\SqlContentEntityTypes;

/**
 * Deletes the rows of every file that no field and no other module uses.
 *
 * Files keep the names and paths of the people who uploaded them. The rows
 * are deleted with SQL and the files stay on disk: the dump ships only the
 * database, and a run against the wrong database must not destroy files. The
 * `file_usage` counts are unreliable (drupal.org #2821423, #3364744), so the
 * fields are read directly; `file_usage` rows of the `file` module mirror
 * those references and are ignored. Rows of other modules (editor, webform)
 * still protect their files.
 */
final class UnusedFileDeleter implements Stage {

  public function __construct(
    private readonly Connection $connection,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly FieldTypePluginManagerInterface $fieldTypeManager,
    private readonly SqlContentEntityTypes $contentEntityTypes,
    private readonly LoggerInterface $logger,
  ) {
  }

  public function validate(): array {
    return ['errors' => [], 'warnings' => []];
  }

  public function confirmation(): string {
    return 'Delete the rows of files no file-referencing field or other module uses; the files stay on disk.';
  }

  public function run(): void {
    $schema = $this->connection->schema();
    if (!$schema->tableExists('file_managed') || !$schema->tableExists('file_usage')) {
      return;
    }
    $unusedFiles = $this->connection->delete('file_managed');
    foreach ($this->fileReferences() as $fileReference) {
      $unusedFiles->notExists($this->referenceTo($fileReference['table'], $fileReference['column'], '{file_managed}.fid'));
    }
    $unusedFiles->notExists($this->referenceTo('file_usage', 'fid', '{file_managed}.fid')->condition('r.module', 'file', '<>'));
    $transaction = $this->connection->startTransaction();
    try {
      $count = $unusedFiles->execute();
      $this->connection->delete('file_usage')->notExists($this->referenceTo('file_managed', 'fid', '{file_usage}.fid'))->execute();
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
    // Earlier stages and later listeners load files through the entity API.
    $this->entityTypeManager->getStorage('file')->resetCache();
    $this->logger->notice('Deleted {count} files that no field or other module uses', ['count' => $count]);
  }

  /**
   * @param string $outerColumn
   *   The column of the deleted table, with the table in braces, since a
   *   delete query has no alias.
   */
  private function referenceTo(string $table, string $column, string $outerColumn): SelectInterface {
    $query = $this->connection->select($table, 'r');
    $query->addExpression('1');
    return $query->where("r.$column = $outerColumn");
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
