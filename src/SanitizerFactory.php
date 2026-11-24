<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended;

use Psr\Log\LoggerInterface;

/**
 * Wires the stages and the guard from Drupal's services.
 */
final class SanitizerFactory {

  /**
   * @return list<\Drupal\sql_sanitize_extended\Stage\Stage>
   *   In the order they run.
   */
  public function stages(Configuration $configuration, LoggerInterface $logger): array {
    return [];
  }

}
