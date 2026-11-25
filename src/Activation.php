<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended;

/**
 * Decides whether the module acts, from the command and its option.
 */
final class Activation {

  public const COMMAND = 'sql:sanitize';

  public const OPTION = 'sanitize-extended';

  /**
   * @param array<string, mixed> $options
   *   The options of `sql:sanitize` by name.
   */
  public static function isEnabled(array $options): bool {
    return self::isOn($options[self::OPTION] ?? NULL);
  }

  /**
   * Terminate listeners also run after other commands, an abort, a failed hook
   * and a signal, which Symfony reports with exit code 0, so the command, a
   * clean exit and no signal are checked before the option, which must be a
   * string or fails loudly.
   *
   * @param array<string, mixed> $options
   *   The options of `sql:sanitize` by name.
   */
  public static function shouldRun(?string $commandName, int $exitCode, ?int $signal, array $options): bool {
    return $commandName === self::COMMAND && $exitCode === 0 && $signal === NULL && self::isEnabled($options);
  }

  /**
   * On when unset; otherwise PHP's boolean words, ignoring case.
   *
   * Anything else throws, so a typo in the switch that keeps the module off in
   * production fails the run instead of turning the module on.
   */
  private static function isOn(?string $optionValue): bool {
    if ($optionValue === NULL) {
      return TRUE;
    }
    return filter_var($optionValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
      ?? throw new \InvalidArgumentException(sprintf('Invalid sql:sanitize option --%s=%s: use yes or no.', self::OPTION, $optionValue));
  }

}
