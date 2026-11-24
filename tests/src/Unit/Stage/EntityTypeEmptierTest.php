<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Unit\Stage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\Stage\EntityTypeEmptier;

#[CoversClass(EntityTypeEmptier::class)]
final class EntityTypeEmptierTest extends TestCase {

  /**
   * @param list<string> $installedEntityTypeIds
   * @param list<string> $contentEntityTypeIds
   */
  #[DataProvider('invalidConfigurations')]
  public function testInvalidConfigurationIsRejectedWithMessage(Configuration $configuration, string $expectedError, array $installedEntityTypeIds, array $contentEntityTypeIds): void {
    $this->assertSame([$expectedError], EntityTypeEmptier::problems($configuration, $installedEntityTypeIds, $contentEntityTypeIds)['errors']);
  }

  /**
   * @return array<string, array<string, mixed>>
   */
  public static function invalidConfigurations(): array {
    return [
      'emptied entity type is a config entity type' => [
        'configuration' => new Configuration(emptyEntityTypes: ['node_type']),
        'expectedError' => '--empty-entity-types: "node_type" is not a content entity type with SQL storage.',
        'installedEntityTypeIds' => ['node', 'node_type'],
        'contentEntityTypeIds' => ['node'],
      ],
      'emptied entity type is user' => [
        'configuration' => new Configuration(emptyEntityTypes: ['user']),
        'expectedError' => '--empty-entity-types: "user" is not supported; users are handled by --kept-user-names and --ignored-roles.',
        'installedEntityTypeIds' => ['user'],
        'contentEntityTypeIds' => ['user'],
      ],
      'entity type is kept and emptied' => [
        'configuration' => new Configuration(allowlistEntityTypes: ['comment'], emptyEntityTypes: ['comment']),
        'expectedError' => '"comment" is in both --allowlist-entity-types and --empty-entity-types.',
        'installedEntityTypeIds' => ['comment'],
        'contentEntityTypeIds' => ['comment'],
      ],
      'kept entity type is not in the default list' => [
        'configuration' => new Configuration(allowlistEntityTypes: ['node']),
        'expectedError' => '--allowlist-entity-types: "node" is not in the default list of entity types to empty.',
        'installedEntityTypeIds' => ['node'],
        'contentEntityTypeIds' => ['node'],
      ],
    ];
  }

  /**
   * @param list<string> $installedEntityTypeIds
   */
  #[DataProvider('namesThatMatchNothing')]
  public function testNameThatMatchesNothingIsWarnedAboutAndNotAnError(Configuration $configuration, string $expectedWarning, array $installedEntityTypeIds): void {
    $result = EntityTypeEmptier::problems($configuration, $installedEntityTypeIds, $installedEntityTypeIds);

    $this->assertSame([$expectedWarning], $result['warnings']);
    $this->assertSame([], $result['errors']);
  }

  /**
   * @return array<string, array<string, mixed>>
   */
  public static function namesThatMatchNothing(): array {
    return [
      'kept entity type is not installed' => [
        'configuration' => new Configuration(allowlistEntityTypes: ['comment']),
        'expectedWarning' => '--allowlist-entity-types: no entity type "comment"; ignored.',
        'installedEntityTypeIds' => [],
      ],
      'emptied entity type does not exist' => [
        'configuration' => new Configuration(emptyEntityTypes: ['typo']),
        'expectedWarning' => '--empty-entity-types: no entity type "typo"; ignored.',
        'installedEntityTypeIds' => ['node'],
      ],
    ];
  }

  public function testValidConfigurationHasNoErrorsOrWarnings(): void {
    $result = EntityTypeEmptier::problems(new Configuration(allowlistEntityTypes: ['comment'], emptyEntityTypes: ['node']), ['comment', 'node'], ['comment', 'node']);

    $this->assertSame(['errors' => [], 'warnings' => []], $result);
  }

  public function testKeptEntityTypeIsNotEmptied(): void {
    $entityTypes = EntityTypeEmptier::entityTypesToEmpty(new Configuration(allowlistEntityTypes: ['comment']), ['comment', 'webform_submission']);

    $this->assertSame(['webform_submission'], $entityTypes['emptied']);
    $this->assertSame(['comment'], $entityTypes['kept']);
  }

  public function testConfiguredEntityTypeIsEmptiedOnTopOfDefaults(): void {
    $entityTypes = EntityTypeEmptier::entityTypesToEmpty(new Configuration(emptyEntityTypes: ['node']), ['comment', 'node']);

    $this->assertSame(['comment', 'node'], $entityTypes['emptied']);
  }

  public function testConfiguredEntityTypeThatIsNotInstalledIsSkipped(): void {
    $entityTypes = EntityTypeEmptier::entityTypesToEmpty(new Configuration(emptyEntityTypes: ['typo']), ['comment', 'webform_submission', 'oauth2_token']);

    $this->assertNotContains('typo', $entityTypes['emptied']);
    $this->assertContains('typo', $entityTypes['missing']);
  }

  public function testDefaultEntityTypeThatIsNotInstalledIsSkipped(): void {
    $entityTypes = EntityTypeEmptier::entityTypesToEmpty(new Configuration(), ['comment', 'webform_submission']);

    $this->assertSame(['oauth2_token'], $entityTypes['missing']);
  }

}
