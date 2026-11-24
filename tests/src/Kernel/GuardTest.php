<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\sql_sanitize_extended\Configuration;

final class GuardTest extends SanitizeKernelTestBase {

  protected static $modules = ['system', 'sql_sanitize_extended', 'user', 'field', 'text', 'filter', 'node', 'history', 'sanitizer_declared_uid_table', 'sanitizer_plain_uid_entity'];

  private function createUndeclaredUidTable(string $table): void {
    $this->container->get('database')->schema()->createTable($table, [
      'fields' => ['uid' => ['type' => 'int', 'not null' => TRUE]],
    ]);
  }

  public function testDanglingUidInDeclaredTableFails(): void {
    $this->installSchema('sanitizer_declared_uid_table', ['declared_uid_table']);
    $this->container->get('database')->insert('declared_uid_table')->fields(['uid' => self::UID_WITHOUT_ACCOUNT])->execute();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('declared_uid_table.uid: 1 rows point to a user that does not exist.');

    $this->guard();
  }

  public function testDanglingPlainUidFieldOfEntityTypeFails(): void {
    $this->installEntitySchema('sanitizer_plain_uid_record');
    $this->container->get('entity_type.manager')->getStorage('sanitizer_plain_uid_record')->create(['uid' => self::UID_WITHOUT_ACCOUNT])->save();

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('sanitizer_plain_uid_record.uid: 1 rows point to a user that does not exist.');

    $this->guard();
  }

  /**
   * @return array<string, array<string, mixed>>
   */
  public static function configurationsWithHistoryTableAllowlistedOrNot(): array {
    return [
      'history table not allowlisted' => ['configuration' => new Configuration(), 'expectedFailure' => "Personal data may remain:\nhistory.uid: 1 rows point to a user that does not exist."],
      'history table allowlisted' => ['configuration' => new Configuration(allowlistTables: ['history']), 'expectedFailure' => NULL],
    ];
  }

  #[DataProvider('configurationsWithHistoryTableAllowlistedOrNot')]
  public function testDanglingUidInHistoryTableFailsUnlessTableIsAllowlisted(Configuration $configuration, ?string $expectedFailure): void {
    $this->installSchema('history', ['history']);
    $this->container->get('database')->insert('history')->fields(['uid' => self::UID_WITHOUT_ACCOUNT, 'nid' => 1, 'timestamp' => 1])->execute();

    $this->assertSame($expectedFailure, $this->guardFailure($configuration));
  }

  public function testUndeclaredTableIsLoggedAsUnchecked(): void {
    $this->createUndeclaredUidTable('undeclared_uid_table');
    $this->container->get('database')->insert('undeclared_uid_table')->fields(['uid' => self::UID_WITHOUT_ACCOUNT])->execute();

    $this->guard();

    $this->assertContains('undeclared_uid_table', $this->uncheckedTables());
  }

  /**
   * @return array<string, array<string, mixed>>
   */
  public static function bundlesOfRowWithDanglingUserReference(): array {
    return [
      'bundle with the field' => ['bundle' => 'bundle_with_field', 'expectedFailure' => "Personal data may remain:\nnode__field_user_reference.field_user_reference_target_id: 1 rows point to a user that does not exist."],
      'bundle without the field' => ['bundle' => 'bundle_without_field', 'expectedFailure' => NULL],
    ];
  }

  #[DataProvider('bundlesOfRowWithDanglingUserReference')]
  public function testDanglingUserReferenceFailsOnlyInBundleWithTheField(string $bundle, ?string $expectedFailure): void {
    $this->installEntitySchema('node');
    foreach (['bundle_with_field', 'bundle_without_field'] as $nodeType) {
      NodeType::create(['type' => $nodeType, 'name' => $nodeType])->save();
    }
    FieldStorageConfig::create(['field_name' => 'field_user_reference', 'entity_type' => 'node', 'type' => 'entity_reference', 'settings' => ['target_type' => 'user']])->save();
    FieldConfig::create(['field_name' => 'field_user_reference', 'entity_type' => 'node', 'bundle' => 'bundle_with_field'])->save();
    $this->container->get('database')->insert('node__field_user_reference')->fields([
      'bundle' => $bundle,
      'deleted' => 0,
      'entity_id' => 1,
      'revision_id' => 1,
      'langcode' => 'en',
      'delta' => 0,
      'field_user_reference_target_id' => self::UID_WITHOUT_ACCOUNT,
    ])->execute();

    $this->assertSame($expectedFailure, $this->guardFailure());
  }

  private function guardFailure(?Configuration $configuration = NULL): ?string {
    try {
      $this->guard($configuration);
    }
    catch (\RuntimeException $exception) {
      return $exception->getMessage();
    }
    return NULL;
  }

  /**
   * @return list<string>
   */
  private function uncheckedTables(): array {
    return explode(', ', $this->logger->contextsOf('Tables the guard could not check: {tables}')[0]['tables']);
  }

}
