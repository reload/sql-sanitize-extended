<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Unit\Stage;

use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\Stage\TableEmptier;
use PHPUnit\Framework\TestCase;

final class TableEmptierTest extends TestCase {

  /**
   * @param list<string> $existingTables
   */
  #[DataProvider('invalidConfigurations')]
  public function testInvalidConfigurationIsRejectedWithMessage(Configuration $configuration, string $expectedError, array $existingTables): void {
    $this->assertSame([$expectedError], TableEmptier::problems($configuration, $existingTables)['errors']);
  }

  /**
   * @return array<string, array<string, mixed>>
   */
  public static function invalidConfigurations(): array {
    return [
      'kept table is not in the default list' => [
        'configuration' => new Configuration(allowlistTables: ['custom']),
        'expectedError' => '--allowlist-tables: "custom" is not in the default list of tables to empty.',
        'existingTables' => ['custom'],
      ],
      'table is kept and emptied' => [
        'configuration' => new Configuration(allowlistTables: ['watchdog'], emptyTables: ['watchdog']),
        'expectedError' => '"watchdog" is in both --allowlist-tables and --empty-tables.',
        'existingTables' => ['watchdog'],
      ],
    ];
  }

  /**
   * @param list<string> $existingTables
   */
  #[DataProvider('namesThatMatchNothing')]
  public function testNameThatMatchesNothingIsWarnedAboutAndNotAnError(Configuration $configuration, string $expectedWarning, array $existingTables): void {
    $result = TableEmptier::problems($configuration, $existingTables);

    $this->assertSame([$expectedWarning], $result['warnings']);
    $this->assertSame([], $result['errors']);
  }

  /**
   * @return array<string, array<string, mixed>>
   */
  public static function namesThatMatchNothing(): array {
    return [
      'kept table does not exist' => [
        'configuration' => new Configuration(allowlistTables: ['history']),
        'expectedWarning' => '--allowlist-tables: no table named "history"; ignored.',
        'existingTables' => ['watchdog'],
      ],
      'emptied table does not exist' => [
        'configuration' => new Configuration(emptyTables: ['typo']),
        'expectedWarning' => '--empty-tables: no table named "typo"; ignored.',
        'existingTables' => ['watchdog'],
      ],
    ];
  }

  public function testKeptTableFoundByPrefixIsAccepted(): void {
    $result = TableEmptier::problems(new Configuration(allowlistTables: ['old_members']), ['old_members']);

    $this->assertSame(['errors' => [], 'warnings' => []], $result);
  }

  public function testValidConfigurationHasNoErrorsOrWarnings(): void {
    $result = TableEmptier::problems(new Configuration(allowlistTables: ['watchdog'], emptyTables: ['custom']), ['watchdog', 'custom']);

    $this->assertSame(['errors' => [], 'warnings' => []], $result);
  }

  public function testDefaultTablesIncludeThoseFoundByPrefix(): void {
    $emptied = TableEmptier::tablesToEmpty(new Configuration(), ['watchdog', 'cache_render', 'old_members', 'field_deleted_data_1a2b', 'field_deleted_revision_1a2b'])['emptied'];

    $this->assertEqualsCanonicalizing(['watchdog', 'cache_render', 'old_members', 'field_deleted_data_1a2b', 'field_deleted_revision_1a2b'], $emptied);
  }

  public function testTableStartingWithPrefixWithoutUnderscoreIsNotEmptied(): void {
    $emptied = TableEmptier::tablesToEmpty(new Configuration(), ['olden'])['emptied'];

    $this->assertSame([], $emptied);
  }

  public function testKeptDefaultTableIsNotEmptied(): void {
    $tables = TableEmptier::tablesToEmpty(new Configuration(allowlistTables: ['history']), ['history']);

    $this->assertSame([], $tables['emptied']);
    $this->assertSame(['history'], $tables['kept']);
  }

  public function testMissingDefaultTableIsSkipped(): void {
    $tables = TableEmptier::tablesToEmpty(new Configuration(), ['watchdog']);

    $this->assertContains('flood', $tables['missing']);
  }

  public function testConfiguredTableIsEmptiedOnTopOfDefaults(): void {
    $tables = TableEmptier::tablesToEmpty(new Configuration(emptyTables: ['custom']), ['custom']);

    $this->assertSame(['custom'], $tables['emptied']);
  }

  public function testConfiguredTableThatDoesNotExistIsSkipped(): void {
    $tables = TableEmptier::tablesToEmpty(new Configuration(emptyTables: ['typo']), ['watchdog']);

    $this->assertNotContains('typo', $tables['emptied']);
    $this->assertContains('typo', $tables['missing']);
  }

}
