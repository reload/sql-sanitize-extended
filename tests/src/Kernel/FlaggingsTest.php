<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Kernel;

use Drupal\flag\Entity\Flag;
use Drupal\sql_sanitize_extended\Stage\UserDeleter;

final class FlaggingsTest extends SanitizeKernelTestBase {

  protected static $modules = ['system', 'sql_sanitize_extended', 'user', 'field', 'text', 'flag'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('flagging');
    $this->installSchema('flag', ['flag_counts']);
    $this->installConfig(['flag']);
    Flag::create([
      'id' => 'flagOnUser',
      'label' => 'Flag on user',
      'entity_type' => 'user',
      'bundles' => ['user'],
      'flag_type' => 'entity:user',
      'link_type' => 'reload',
      'flagTypeConfig' => [],
      'linkTypeConfig' => [],
    ])->save();
  }

  private function flagAs(int $uid, int $targetUid): void {
    $this->container->get('entity_type.manager')->getStorage('flagging')->create([
      'flag_id' => 'flagOnUser',
      'entity_type' => 'user',
      'entity_id' => $targetUid,
      'uid' => $uid,
      'global' => FALSE,
    ])->save();
  }

  public function testFlaggingOfDeletedUserIsDeleted(): void {
    $deletedFlagger = $this->createUser('deletedFlagger');
    $this->flagAs((int) $deletedFlagger->id(), self::ADMINISTRATOR_UID);

    $this->sanitize(stageClasses: [UserDeleter::class]);

    $this->assertSame(0, $this->rowCount('flagging'));
  }

  public function testFlaggingOfKeptUserSurvives(): void {
    $this->flagAs(self::ADMINISTRATOR_UID, self::ADMINISTRATOR_UID);

    $this->sanitize(stageClasses: [UserDeleter::class]);

    $this->assertSame(1, $this->rowCount('flagging'));
  }

}
