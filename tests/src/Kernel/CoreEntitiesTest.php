<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\user\Entity\Role;
use Drupal\sql_sanitize_extended\Stage\FieldEmptier;
use Drupal\sql_sanitize_extended\Stage\ReferenceReassigner;
use Drupal\sql_sanitize_extended\Stage\UserDeleter;

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

  private function addReferenceField(string $fieldName, string $bundle, bool $required): void {
    if (!FieldStorageConfig::loadByName('node', $fieldName)) {
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'settings' => ['target_type' => 'user'],
        'cardinality' => 1,
      ])->save();
    }
    FieldConfig::create(['field_name' => $fieldName, 'entity_type' => 'node', 'bundle' => $bundle, 'required' => $required])->save();
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

  public function testNodeOwnedByDeletedUserIsReassignedToAnonymous(): void {
    $deletedAuthor = $this->createUser('deletedAuthor');
    $node = $this->createNode('plain_bundle', (int) $deletedAuthor->id());

    $this->sanitize(stageClasses: [ReferenceReassigner::class]);

    $this->assertSame(self::ANONYMOUS_UID, (int) Node::load($node->id())->getOwnerId());
  }

  public function testRevisionAuthoredByKeptAccountKeepsItsAuthorOnNodeOfDeletedUser(): void {
    $deletedAuthor = $this->createUser('deletedAuthor');
    $keptTestAccount = $this->createUser('keptTestAccount');
    $node = $this->createNode('plain_bundle', (int) $deletedAuthor->id(), ['revision_uid' => $deletedAuthor->id()]);
    $node->setNewRevision();
    $node->setRevisionUserId((int) $keptTestAccount->id());
    $node->save();

    $this->sanitize(new Configuration(keptUserNames: ['keptTestAccount']), [ReferenceReassigner::class]);

    $this->assertSame((int) $keptTestAccount->id(), (int) Node::load($node->id())->getRevisionUserId());
  }

  public function testRevisionAuthoredByDeletedUserIsReassignedOnNodeOfKeptAccount(): void {
    $deletedAuthor = $this->createUser('deletedAuthor');
    $keptTestAccount = $this->createUser('keptTestAccount');
    $node = $this->createNode('plain_bundle', (int) $keptTestAccount->id(), ['revision_uid' => $deletedAuthor->id()]);

    $this->sanitize(new Configuration(keptUserNames: ['keptTestAccount']), [ReferenceReassigner::class]);

    $this->assertSame(self::ANONYMOUS_UID, (int) Node::load($node->id())->getRevisionUserId());
  }

  public function testNodeOwnedByKeptAccountKeepsItsOwner(): void {
    $keptTestAccount = $this->createUser('keptTestAccount');
    $node = $this->createNode('plain_bundle', (int) $keptTestAccount->id());

    $this->sanitize(new Configuration(keptUserNames: ['keptTestAccount']), [ReferenceReassigner::class]);

    $this->assertSame((int) $keptTestAccount->id(), (int) Node::load($node->id())->getOwnerId());
  }

  public function testOptionalReferenceToDeletedUserIsDeletedFromDataAndRevisions(): void {
    $this->createBundle('bundle_with_optional_reference');
    $this->addReferenceField('field_optional_reference', 'bundle_with_optional_reference', FALSE);
    $node = $this->createNode('bundle_with_optional_reference', self::ADMINISTRATOR_UID, ['field_optional_reference' => $this->createUser('deletedReferenced')->id()]);

    $this->sanitize(stageClasses: [ReferenceReassigner::class]);

    $this->assertSame([0, 0], [
      $this->rowCount('node__field_optional_reference', ['entity_id' => $node->id()]),
      $this->rowCount('node_revision__field_optional_reference', ['entity_id' => $node->id()]),
    ]);
  }

  public function testOptionalReferenceToKeptUserSurvives(): void {
    $this->createBundle('bundle_with_optional_reference');
    $this->addReferenceField('field_optional_reference', 'bundle_with_optional_reference', FALSE);
    $keptTestAccount = $this->createUser('keptTestAccount');
    $node = $this->createNode('bundle_with_optional_reference', self::ADMINISTRATOR_UID, ['field_optional_reference' => $keptTestAccount->id()]);

    $this->sanitize(new Configuration(keptUserNames: ['keptTestAccount']), [ReferenceReassigner::class]);

    $this->assertSame((int) $keptTestAccount->id(), (int) Node::load($node->id())->get('field_optional_reference')->target_id);
  }

  public function testRequiredReferenceToDeletedUserIsReassignedToAnonymous(): void {
    $this->createBundle('bundle_with_required_reference');
    $this->addReferenceField('field_required_reference', 'bundle_with_required_reference', TRUE);
    $deletedReferenced = $this->createUser('deletedReferenced');
    $node = $this->createNode('bundle_with_required_reference', self::ADMINISTRATOR_UID, ['field_required_reference' => $deletedReferenced->id()]);

    $this->sanitize(stageClasses: [ReferenceReassigner::class]);

    $this->assertSame(self::ANONYMOUS_UID, (int) Node::load($node->id())->get('field_required_reference')->target_id);
  }

  public function testKeptTestAccountIsNotSanitized(): void {
    $keptTestAccount = $this->createUser('keptTestAccount');
    $passwordHash = $keptTestAccount->getPassword();

    $this->sanitize(new Configuration(keptUserNames: ['keptTestAccount']), [UserDeleter::class]);

    $keptTestAccount = $this->container->get('entity_type.manager')->getStorage('user')->load($keptTestAccount->id());
    $this->assertSame(['keptTestAccount@example.com', $passwordHash], [$keptTestAccount->getEmail(), $keptTestAccount->getPassword()]);
  }

  public function testNodeOwnedByUserWithIgnoredRoleKeepsItsOwner(): void {
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();
    $editor = $this->createUser('editorWithIgnoredRole');
    $editor->addRole('editor')->save();
    $node = $this->createNode('plain_bundle', (int) $editor->id());

    $this->sanitize(new Configuration(ignoredRoles: ['editor']), [ReferenceReassigner::class]);

    $this->assertSame((int) $editor->id(), (int) Node::load($node->id())->getOwnerId());
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
