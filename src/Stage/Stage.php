<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Stage;

/**
 * One step of the sanitizing: checks its options, says what it will do, then
 * does it.
 */
interface Stage {

  /**
   * Reads what exists and checks the options against it.
   *
   * @return array{errors: list<string>, warnings: list<string>}
   *   Errors stop the run before the prompt. A warning names a value that
   *   matches nothing; the value is ignored.
   */
  public function validate(): array;

  /**
   * The line the prompt shows for this step, or NULL if it has nothing to do.
   */
  public function confirmation(): ?string;

  public function run(): void;

}
