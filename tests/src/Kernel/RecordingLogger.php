<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Kernel;

use Psr\Log\AbstractLogger;

final class RecordingLogger extends AbstractLogger {

  /**
   * @var list<array{level: string, message: string, context: array<string, mixed>}>
   */
  public array $records = [];

  public function log($level, string|\Stringable $message, array $context = []): void {
    $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
  }

  /**
   * @return list<string>
   */
  public function messagesOfLevel(string $level): array {
    return array_column(array_filter($this->records, fn (array $record) => $record['level'] === $level), 'message');
  }

  /**
   * @return list<array<string, mixed>>
   */
  public function contextsOf(string $message): array {
    return array_column(array_filter($this->records, fn (array $record) => $record['message'] === $message), 'context');
  }

}
