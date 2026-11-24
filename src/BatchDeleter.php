<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Deletes entities in batches through the entity API.
 */
final class BatchDeleter {

  /**
   * Same as core's `field_purge_batch_size`.
   */
  public const BATCH_SIZE = 50;

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * Deletes the next batch until no ids are left, resetting the entity cache
   * between batches.
   *
   * @param \Closure(): array<int|string> $nextIds
   *   The ids of the next batch, at most BATCH_SIZE.
   * @param \Closure(array<mixed>): void|null $beforeDelete
   *   Called with each loaded batch just before it is deleted.
   * @param string $what
   *   Names the entities in the message of the exception.
   *
   * @return int
   *   The number of entities deleted.
   *
   * @throws \RuntimeException
   *   When a batch removes nothing, instead of looping until the runner times
   *   out.
   */
  public function deleteBatches(string $entityTypeId, \Closure $nextIds, string $what, ?\Closure $beforeDelete = NULL): int {
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $count = 0;
    $previousIds = [];
    while ($ids = $nextIds()) {
      if ($ids === $previousIds) {
        throw new \RuntimeException("Deleting $what did not remove them.");
      }
      $entities = $storage->loadMultiple($ids);
      if ($beforeDelete !== NULL) {
        $beforeDelete($entities);
      }
      $storage->delete($entities);
      $storage->resetCache();
      $previousIds = $ids;
      $count += count($entities);
    }
    return $count;
  }

}
