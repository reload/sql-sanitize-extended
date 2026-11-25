<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Unit;

use Drupal\sql_sanitize_extended\Activation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ActivationTest extends TestCase {

  /**
   * @return array<string, array{?string, bool}>
   */
  public static function optionValues(): array {
    return [
      'unset' => [NULL, TRUE],
      'yes' => ['yes', TRUE],
      'one' => ['1', TRUE],
      'true' => ['true', TRUE],
      'on' => ['on', TRUE],
      'no' => ['no', FALSE],
      'no in capitals' => ['NO', FALSE],
      'zero' => ['0', FALSE],
      'false' => ['false', FALSE],
      'off' => ['off', FALSE],
    ];
  }

  #[DataProvider('optionValues')]
  public function testBooleanWordsSwitchTheModuleIgnoringCase(?string $optionValue, bool $expected): void {
    $this->assertSame($expected, Activation::isEnabled([Activation::OPTION => $optionValue]));
  }

  public function testValueThatIsNotBooleanIsRejected(): void {
    $this->expectException(\InvalidArgumentException::class);

    Activation::isEnabled([Activation::OPTION => 'nej']);
  }

  /**
   * @return array<string, array{?string, int, array<string, mixed>, bool}>
   */
  public static function runs(): array {
    return [
      'sanitize that succeeded' => ['sql:sanitize', 0, [Activation::OPTION => NULL], TRUE],
      'option missing' => ['sql:sanitize', 0, [], TRUE],
      'sanitize that was aborted' => ['sql:sanitize', 1, [], FALSE],
      'another command' => ['status', 0, [], FALSE],
      'no command' => [NULL, 0, [], FALSE],
      'sanitize switched off' => ['sql:sanitize', 0, [Activation::OPTION => 'no'], FALSE],
    ];
  }

  /**
   * @param array<string, mixed> $options
   */
  #[DataProvider('runs')]
  public function testActsOnlyAfterSuccessfulSanitizeThatIsNotSwitchedOff(?string $commandName, int $exitCode, array $options, bool $expected): void {
    $this->assertSame($expected, Activation::shouldRun($commandName, $exitCode, $options));
  }

  public function testBooleanOptionValueFromConfigurationIsRejected(): void {
    $this->expectException(\TypeError::class);

    Activation::isEnabled([Activation::OPTION => TRUE]);
  }

  public function testOptionIsNotReadAfterAbort(): void {
    $this->assertFalse(Activation::shouldRun('sql:sanitize', 1, [Activation::OPTION => TRUE]));
  }

}
