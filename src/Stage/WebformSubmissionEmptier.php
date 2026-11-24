<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Stage;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\webform\WebformSubmissionStorageInterface;
use Drupal\sql_sanitize_extended\BatchDeleter;

/**
 * Deletes webform submissions without running the webforms' handlers.
 */
final class WebformSubmissionEmptier {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $connection,
    private readonly BatchDeleter $batchDeleter,
  ) {
  }

  /**
   * Webform handlers fire on delete (Remote Post, Email); none may run.
   *
   * @return int
   *   The number of submissions deleted.
   */
  public function emptyAll(WebformSubmissionStorageInterface $storage): int {
    $count = 0;
    foreach ($this->entityTypeManager->getStorage('webform')->loadMultiple() as $webform) {
      $count += $this->batchDeleter->deleteBatches(
        'webform_submission',
        fn () => $storage->getQuery()->accessCheck(FALSE)->condition('webform_id', $webform->id())->sort('sid')->range(0, BatchDeleter::BATCH_SIZE)->execute(),
        "submissions of webform {$webform->id()}",
        function (array $submissions): void {
          foreach ($submissions as $submission) {
            // Each submission's own webform: one evicted from the entity cache
            // is reloaded with its handlers enabled. In memory only: never
            // saved.
            foreach ($submission->getWebform()->getHandlers() as $handler) {
              $handler->disable();
            }
          }
        },
      );
    }
    $count += $this->deleteRemainingSubmissions();
    return $count;
  }

  /**
   * Deletes with SQL every submission left after the per-webform deletes.
   *
   * These belong to webforms that no longer exist, which webform cannot load.
   * Must run last.
   */
  private function deleteRemainingSubmissions(): int {
    $submissionIds = fn () => $this->connection->select('webform_submission', 's')->fields('s', ['sid']);
    if ($this->connection->schema()->tableExists('file_usage')) {
      $this->connection->delete('file_usage')
        ->condition('module', 'webform')
        ->condition('type', 'webform_submission')
        ->condition('id', $submissionIds(), 'IN')
        ->execute();
    }
    $this->connection->delete('webform_submission_data')->condition('sid', $submissionIds(), 'IN')->execute();
    return (int) $this->connection->delete('webform_submission')->execute();
  }

}
