<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Kernel;

use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupType;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use Drupal\sql_sanitize_extended\Stage\ReferenceReassigner;
use Drupal\sql_sanitize_extended\Stage\UserDeleter;

#[TestGroup('sanitizer')]
final class GroupMembershipsTest extends SanitizeKernelTestBase {

  protected static $modules = ['system', 'sql_sanitize_extended', 'user', 'field', 'text', 'options', 'entity', 'flexible_permissions', 'group'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('group');
    $this->installEntitySchema('group_type');
    $this->installEntitySchema('group_relationship');
    $this->installEntitySchema('group_relationship_type');
    $this->installConfig(['group']);
    GroupType::create(['id' => 'noCreatorMembership', 'label' => 'Group type without creator membership', 'creator_membership' => FALSE])->save();
  }

  private function createGroup(UserInterface $owner): Group {
    $group = Group::create(['type' => 'noCreatorMembership', 'label' => 'Group', 'uid' => $owner->id()]);
    $group->save();
    return $group;
  }

  public function testMembershipOfDeletedUserIsDeleted(): void {
    $deletedMember = $this->createUser('deletedMember');
    $this->createGroup($this->administrator)->addMember($deletedMember);

    $this->sanitize(stageClasses: [UserDeleter::class]);

    $this->assertSame(0, $this->rowCount('group_relationship_field_data', ['plugin_id' => 'group_membership']));
  }

  public function testGroupOwnedByDeletedUserIsReassignedToAnonymous(): void {
    $group = $this->createGroup($this->createUser('deletedOwner'));

    $this->sanitize(stageClasses: [ReferenceReassigner::class]);

    $this->assertSame(self::ANONYMOUS_UID, (int) Group::load($group->id())->getOwnerId());
  }

  public function testMembershipOfLongGoneUserRemoved(): void {
    $deletedMember = $this->createUser('deletedMember');
    $this->createGroup($this->administrator)->addMember($deletedMember);
    $database = $this->container->get('database');
    $database->delete('users_field_data')->condition('uid', $deletedMember->id())->execute();
    $database->delete('users')->condition('uid', $deletedMember->id())->execute();
    $this->container->get('entity_type.manager')->getStorage('group_relationship')->resetCache();

    $this->sanitize(stageClasses: [UserDeleter::class]);

    $this->assertSame(0, $this->rowCount('group_relationship_field_data', ['plugin_id' => 'group_membership']));
  }

  public function testMembershipOfKeptAdministratorSurvives(): void {
    $this->createGroup($this->administrator)->addMember($this->administrator);

    $this->sanitize(stageClasses: [UserDeleter::class]);

    $this->assertSame(1, $this->rowCount('group_relationship_field_data', ['plugin_id' => 'group_membership']));
  }

}
