<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Unit;

use Drupal\sql_sanitize_extended\Configuration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase {

  public function testOptionsAreEmptyListsWhenUnset(): void {
    $this->assertEquals(new Configuration(), Configuration::fromOptions([
      'ignored-roles' => NULL,
      'empty-tables' => '',
    ]));
  }

  /**
   * @return array<string, array{string, string, string, list<string>}>
   */
  public static function listOptions(): array {
    return [
      'kept user names' => ['kept-user-names', 'first,second', 'keptUserNames', ['first', 'second']],
      'ignored roles are not trimmed' => ['ignored-roles', 'editor, tester', 'ignoredRoles', ['editor', ' tester']],
      'allowlisted tables' => ['allowlist-tables', 'history', 'allowlistTables', ['history']],
      'allowlisted entity types' => ['allowlist-entity-types', 'comment', 'allowlistEntityTypes', ['comment']],
      'emptied tables' => ['empty-tables', 'custom_log,custom_cache', 'emptyTables', ['custom_log', 'custom_cache']],
      'emptied entity types' => ['empty-entity-types', 'profile', 'emptyEntityTypes', ['profile']],
      'emptied fields' => ['empty-fields', 'node.article.field_secret', 'emptyFields', ['node.article.field_secret']],
    ];
  }

  /**
   * @param list<string> $expectedList
   */
  #[DataProvider('listOptions')]
  public function testOptionIsSplitOnCommas(string $option, string $value, string $property, array $expectedList): void {
    $configuration = Configuration::fromOptions([$option => $value]);

    $this->assertSame($expectedList, $configuration->$property);
  }

}
