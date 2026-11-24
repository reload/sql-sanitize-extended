<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\Stage\FieldEmptier;

/**
 * Cases that only need core: users and content.
 */
final class CoreEntitiesTest extends SanitizeKernelTestBase {

  protected static $modules = ['system', 'sql_sanitize_extended', 'user', 'field', 'text', 'filter', 'node', 'datetime'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node']);
    $this->createBundle('plain_bundle');
  }

  private function createBundle(string $bundle): void {
    NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
  }

  private function addTextField(string $fieldName, string $bundle): void {
    if (!FieldStorageConfig::loadByName('node', $fieldName)) {
      FieldStorageConfig::create(['field_name' => $fieldName, 'entity_type' => 'node', 'type' => 'string'])->save();
    }
    FieldConfig::create(['field_name' => $fieldName, 'entity_type' => 'node', 'bundle' => $bundle])->save();
  }

  private function createNode(string $bundle, int $ownerUid, array $values = []): NodeInterface {
    $node = Node::create($values + ['type' => $bundle, 'title' => 'Content', 'uid' => $ownerUid]);
    $node->save();
    return $node;
  }

  public function testEmptiedFieldIsClearedInBundleDataAndRevisions(): void {
    $this->createBundle('bundle_with_emptied_field');
    $this->addTextField('field_emptied', 'bundle_with_emptied_field');
    $node = $this->createNode('bundle_with_emptied_field', self::ADMINISTRATOR_UID, ['field_emptied' => 'private']);

    $this->sanitize(new Configuration(emptyFields: ['node.bundle_with_emptied_field.field_emptied']), [FieldEmptier::class]);

    $this->assertSame([0, 0], [
      $this->rowCount('node__field_emptied', ['entity_id' => $node->id()]),
      $this->rowCount('node_revision__field_emptied', ['entity_id' => $node->id()]),
    ]);
  }

  public function testEmptiedFieldOfEntityTypeWithoutRevisionsIsEmptied(): void {
    $this->installEntitySchema('user');
    FieldStorageConfig::create(['field_name' => 'field_emptied', 'entity_type' => 'user', 'type' => 'string'])->save();
    FieldConfig::create(['field_name' => 'field_emptied', 'entity_type' => 'user', 'bundle' => 'user'])->save();
    $user = $this->createUser('withPrivateField');
    $user->set('field_emptied', 'private')->save();

    $this->sanitize(new Configuration(emptyFields: ['user.user.field_emptied']), [FieldEmptier::class]);

    $this->assertSame(0, $this->rowCount('user__field_emptied', ['entity_id' => $user->id()]));
  }

  public function testMissingConfiguredFieldIsSkippedAndOthersAreEmptied(): void {
    $this->createBundle('bundle_with_emptied_field');
    $this->addTextField('field_emptied', 'bundle_with_emptied_field');
    $node = $this->createNode('bundle_with_emptied_field', self::ADMINISTRATOR_UID, ['field_emptied' => 'private']);

    $this->sanitize(new Configuration(emptyFields: ['node.bundle_with_emptied_field.typo', 'node.bundle_with_emptied_field.field_emptied']), [FieldEmptier::class]);

    $this->assertTrue(Node::load($node->id())->get('field_emptied')->isEmpty());
  }

  public function testEmptiedFieldLeavesOtherBundles(): void {
    $this->createBundle('bundle_with_emptied_field');
    $this->createBundle('bundle_keeping_field');
    $this->addTextField('field_emptied', 'bundle_with_emptied_field');
    $this->addTextField('field_emptied', 'bundle_keeping_field');
    $node = $this->createNode('bundle_keeping_field', self::ADMINISTRATOR_UID, ['field_emptied' => 'public']);

    $this->sanitize(new Configuration(emptyFields: ['node.bundle_with_emptied_field.field_emptied']), [FieldEmptier::class]);

    $this->assertSame('public', Node::load($node->id())->get('field_emptied')->value);
  }

}
