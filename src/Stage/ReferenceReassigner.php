<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Stage;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Drupal\sql_sanitize_extended\SqlContentEntityTypes;
use Drupal\sql_sanitize_extended\UserReference\Column;
use Drupal\sql_sanitize_extended\UserReference\Finder;

/**
 * Reassigns references to users that will not be kept, or deletes them.
 *
 * Runs before the users are deleted: deleting a user deletes the nodes and
 * groups it still owns, and those deletes can call external services. Core's
 * own reassignment is not used, since it falls short:
 * - https://www.drupal.org/project/drupal/issues/3043725: only node and
 *   comment.
 * - https://www.drupal.org/project/drupal/issues/1461042: file_managed.uid.
 * - https://www.drupal.org/project/drupal/issues/2977362: revision authors
 *   reassigned wrongly.
 */
final class ReferenceReassigner implements Stage {

  public function __construct(
    private readonly Connection $connection,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SqlContentEntityTypes $contentEntityTypes,
    private readonly Finder $userReferenceFinder,
    private readonly KeptAccounts $keptAccounts,
    private readonly LoggerInterface $logger,
  ) {
  }

  public function validate(): array {
    try {
      $this->userReferenceFinder->all();
    }
    catch (\RuntimeException $exception) {
      return ['errors' => [$exception->getMessage()], 'warnings' => []];
    }
    return ['errors' => [], 'warnings' => []];
  }

  public function confirmation(): string {
    return 'Point references to users that are not kept at uid 0, or delete them if the field is optional.';
  }

  public function run(): void {
    $keptUids = $this->keptAccounts->uids();
    foreach ($this->userReferenceFinder->withAction(Column::REASSIGN) as $column) {
      $query = $this->connection->update($column->userIdTable)
        ->fields([$column->userIdColumn => 0])
        ->condition($column->userIdColumn, $keptUids, 'NOT IN');
      $count = Finder::restrictToBundles($query, $column)->execute();
      $this->logger->notice("Reassigned to anonymous: {table}.{column}, {count} rows", ['table' => $column->userIdTable, 'column' => $column->userIdColumn, 'count' => $count]);
    }
    foreach ($this->userReferenceFinder->withAction(Column::DELETE) as $column) {
      $query = $this->connection->delete($column->userIdTable)
        ->condition($column->userIdColumn, $keptUids, 'NOT IN');
      $count = Finder::restrictToBundles($query, $column)->execute();
      $this->logger->notice("Deleted references: {table}.{column}, {count} rows", ['table' => $column->userIdTable, 'column' => $column->userIdColumn, 'count' => $count]);
    }
    foreach ($this->contentEntityTypes->ids() as $entityTypeId) {
      $this->entityTypeManager->getStorage($entityTypeId)->resetCache();
    }
  }

}
