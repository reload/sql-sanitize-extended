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
   * Off for `no` and `0`, as core's switches are.
   */
  private static function isOn(?string $optionValue): bool {
    return $optionValue != 'no' && $optionValue != '0';
  }

}
