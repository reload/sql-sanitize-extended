<?php

declare(strict_types=1);

namespace Drupal\sanitizer_recording_handler\Plugin\WebformHandler;

use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Counts deletes, standing in for handlers that post or send on delete.
 *
 * @WebformHandler(
 *   id = "sanitizer_record_delete",
 *   label = @Translation("Record delete"),
 *   category = @Translation("Testing"),
 *   description = @Translation("Counts submission deletes."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
final class RecordDeleteWebformHandler extends WebformHandlerBase {

  public function postDelete(WebformSubmissionInterface $webform_submission): void {
    $state = \Drupal::state();
    $state->set('sanitizer_recording_handler.deletes', $state->get('sanitizer_recording_handler.deletes', 0) + 1);
  }

}
