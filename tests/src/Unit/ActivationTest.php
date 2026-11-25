<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Unit;

use Drupal\sql_sanitize_extended\Activation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ActivationTest extends TestCase {

  /**
   * SIGINT; `\SIGINT` exists only with the pcntl extension.
   */
  private const INTERRUPTING_SIGNAL = 2;

  /**
   * @return array<string, array{?string, bool}>
   */
  public static function optionValues(): array {
    return [
      'unset' => [NULL, TRUE],
      'yes' => ['yes', TRUE],
      'no' => ['no', FALSE],
      'zero' => ['0', FALSE],
    ];
  }

  #[DataProvider('optionValues')]
  public function testOnlyNoAndZeroTurnTheModuleOff(?string $optionValue, bool $expected): void {
    $this->assertSame($expected, Activation::isEnabled([Activation::OPTION => $optionValue]));
  }

  /**
   * @return array<string, array{?string, int, ?int, array<string, mixed>, bool}>
   */
  public static function runs(): array {
    return [
      'sanitize that succeeded' => ['sql:sanitize', 0, NULL, [Activation::OPTION => NULL], TRUE],
      'option missing' => ['sql:sanitize', 0, NULL, [], TRUE],
      'sanitize that was aborted' => ['sql:sanitize', 1, NULL, [], FALSE],
      'sanitize interrupted by a signal' => ['sql:sanitize', 0, self::INTERRUPTING_SIGNAL, [], FALSE],
      'another command' => ['status', 0, NULL, [], FALSE],
      'no command' => [NULL, 0, NULL, [], FALSE],
      'sanitize switched off' => ['sql:sanitize', 0, NULL, [Activation::OPTION => 'no'], FALSE],
    ];
  }

  /**
   * @param array<string, mixed> $options
   */
  #[DataProvider('runs')]
  public function testActsOnlyAfterSuccessfulSanitizeThatIsNotSwitchedOff(?string $commandName, int $exitCode, ?int $signal, array $options, bool $expected): void {
    $this->assertSame($expected, Activation::shouldRun($commandName, $exitCode, $signal, $options));
  }

  public function testBooleanOptionValueFromConfigurationIsRejected(): void {
    $this->expectException(\TypeError::class);

    Activation::isEnabled([Activation::OPTION => TRUE]);
  }

  /**
   * @return array<string, array{int, ?int}>
   */
  public static function unfinishedRuns(): array {
    return [
      'aborted' => [1, NULL],
      'interrupted by a signal' => [0, self::INTERRUPTING_SIGNAL],
    ];
  }

  /**
   * A boolean option throws when read, so these fail if it is read first.
   */
  #[DataProvider('unfinishedRuns')]
  public function testOptionIsNotReadAfterUnfinishedRun(int $exitCode, ?int $signal): void {
    $this->assertFalse(Activation::shouldRun('sql:sanitize', $exitCode, $signal, [Activation::OPTION => TRUE]));
  }

}
