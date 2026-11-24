<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Stage;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Drupal\sql_sanitize_extended\BatchDeleter;
use Drupal\sql_sanitize_extended\Fetch;
use Drupal\sql_sanitize_extended\SqlContentEntityTypes;
use Drupal\sql_sanitize_extended\UserReference\Column;
use Drupal\sql_sanitize_extended\UserReference\Finder;

/**
 * Deletes the users that are not kept, and what only meant something for them.
 */
final class UserDeleter implements Stage {

  public function __construct(
    private readonly Connection $connection,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SqlContentEntityTypes $contentEntityTypes,
    private readonly BatchDeleter $batchDeleter,
    private readonly Finder $userReferenceFinder,
    private readonly KeptAccounts $keptAccounts,
    private readonly LoggerInterface $logger,
  ) {
  }

  public function validate(): array {
    return $this->keptAccounts->validate();
  }

  public function confirmation(): string {
    return $this->keptAccounts->confirmation();
  }

  public function run(): void {
    $keptUids = $this->keptAccounts->uids();
    $count = $this->batchDeleter->deleteBatches(
      'user',
      fn () => $this->entityTypeManager->getStorage('user')->getQuery()->accessCheck(FALSE)->condition('uid', $keptUids, 'NOT IN')->sort('uid')->range(0, BatchDeleter::BATCH_SIZE)->execute(),
      'users',
    );
    $this->logger->notice('Deleted {count} users', ['count' => $count]);
    $this->deleteLeftovers($keptUids);
  }

  /**
   * Deletes what refers to users that no longer exist, through the API.
   *
   * Memberships and flaggings are deleted with their user by modules' own
   * hooks; this catches those of users that were already gone.
   *
   * @param list<int> $keptUids
   */
  private function deleteLeftovers(array $keptUids): void {
    $idsByEntityTypeId = [];
    foreach ($this->userReferenceFinder->withAction(Column::SKIP) as $column) {
      // Revision tables would delete entities for their old revisions.
      if ($column->entityTypeId === NULL || $column->entityIdColumn === NULL || !in_array($column->userIdTable, $this->contentEntityTypes->baseAndDataTables($column->entityTypeId), TRUE)) {
        continue;
      }
      $query = $this->connection->select($column->userIdTable, 't')
        ->distinct()
        ->fields('t', [$column->entityIdColumn])
        ->condition($column->userIdColumn, $keptUids, 'NOT IN');
      foreach (Fetch::column(Finder::restrictToBundles($query, $column)) as $id) {
        $idsByEntityTypeId[$column->entityTypeId][$id] = $id;
      }
    }
    foreach ($idsByEntityTypeId as $entityTypeId => $ids) {
      $count = $this->batchDeleter->deleteBatches($entityTypeId, static function () use (&$ids): array {
        return array_splice($ids, 0, BatchDeleter::BATCH_SIZE);
      }, 'entities of users that no longer exist');
      $this->logger->notice('Deleted {count} {entityType} entities of users that no longer exist', ['count' => $count, 'entityType' => $entityTypeId]);
    }
  }

}
