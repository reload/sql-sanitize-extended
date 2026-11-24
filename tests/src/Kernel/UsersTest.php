<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Kernel;

use Drupal\sql_sanitize_extended\BatchDeleter;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\sql_sanitize_extended\Stage\UserDeleter;

/**
 * Cases that need nothing beyond users.
 */
final class UsersTest extends SanitizeKernelTestBase {

  protected static $modules = ['system', 'sql_sanitize_extended', 'user'];

  public function testMoreThanOneBatchOfUsersDeleted(): void {
    for ($i = 0; $i <= BatchDeleter::BATCH_SIZE; $i++) {
      $this->createUser("deletedUser$i");
    }

    $this->sanitize(stageClasses: [UserDeleter::class]);

    $this->assertSame([self::ADMINISTRATOR_UID], $this->remainingUids());
  }

  public function testUserNotKeptIsDeleted(): void {
    $this->createUser('deletedUser');
    $keptByName = $this->createUser('KeptByName');

    $this->sanitize(new Configuration(keptUserNames: ['keptbyname']), [UserDeleter::class]);

    $this->assertSame([self::ADMINISTRATOR_UID, (int) $keptByName->id()], $this->remainingUids());
  }

  public function testUserWithIgnoredRoleIsKeptWithItsPersonalData(): void {
    Role::create(['id' => 'editor', 'label' => 'Editor'])->save();
    $editor = $this->createUser('editorWithIgnoredRole');
    $editor->addRole('editor')->save();
    $this->createUser('deletedUser');

    $this->sanitize(new Configuration(ignoredRoles: ['editor']), [UserDeleter::class]);

    $this->assertSame([self::ADMINISTRATOR_UID, (int) $editor->id()], $this->remainingUids());
    $this->assertSame('editorWithIgnoredRole@example.com', User::load($editor->id())->getEmail());
  }

  public function testAdministratorKeepsItsName(): void {
    $this->sanitize(stageClasses: [UserDeleter::class]);

    $this->assertSame('administrator', User::load(self::ADMINISTRATOR_UID)->getAccountName());
  }

}
