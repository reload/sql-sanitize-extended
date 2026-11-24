<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\user\Entity\User;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\Stage\FieldEmptier;
use Drupal\sql_sanitize_extended\Stage\UnusedFileDeleter;

final class UnusedFilesTest extends SanitizeKernelTestBase {

  private const MODULE_OTHER_THAN_FILE = 'editor';

  protected static $modules = ['system', 'sql_sanitize_extended', 'user', 'field', 'file'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
  }

  private function createFile(int $ownerUid): FileInterface {
    file_put_contents('public://picture.png', 'private');
    $file = File::create(['uri' => 'public://picture.png', 'uid' => $ownerUid, 'status' => 1]);
    $file->save();
    return $file;
  }

  private function addFileField(string $fieldName): void {
    FieldStorageConfig::create(['field_name' => $fieldName, 'entity_type' => 'user', 'type' => 'file'])->save();
    FieldConfig::create(['field_name' => $fieldName, 'entity_type' => 'user', 'bundle' => 'user'])->save();
  }

  private function addFileReferenceField(string $fieldName): void {
    FieldStorageConfig::create(['field_name' => $fieldName, 'entity_type' => 'user', 'type' => 'entity_reference', 'settings' => ['target_type' => 'file']])->save();
    FieldConfig::create(['field_name' => $fieldName, 'entity_type' => 'user', 'bundle' => 'user'])->save();
  }

  public function testFileHeldByEntityReferenceFieldSurvives(): void {
    $this->addFileReferenceField('field_reference_to_file');
    $keptTestAccount = $this->createUser('keptTestAccount');
    $file = $this->createFile((int) $keptTestAccount->id());
    $keptTestAccount->set('field_reference_to_file', $file->id())->save();
    $this->container->get('database')->delete('file_usage')->execute();

    $this->sanitize(new Configuration(keptUserNames: ['keptTestAccount']), [UnusedFileDeleter::class]);

    $this->assertNotNull(File::load($file->id()));
  }

  public function testFileOfEmptiedFieldIsDeleted(): void {
    $this->addFileField('field_emptied_file');
    $keptTestAccount = $this->createUser('keptTestAccount');
    $file = $this->createFile((int) $keptTestAccount->id());
    $keptTestAccount->set('field_emptied_file', $file->id())->save();

    $this->sanitize(new Configuration(keptUserNames: ['keptTestAccount'], emptyFields: ['user.user.field_emptied_file']), [FieldEmptier::class, UnusedFileDeleter::class]);

    $this->assertNull(File::load($file->id()));
  }

  public function testFileHeldByTheSameEntityInAnotherFieldSurvivesEmptyingOneField(): void {
    $this->addFileField('field_emptied_file');
    $this->addFileField('field_kept_file');
    $keptTestAccount = $this->createUser('keptTestAccount');
    $file = $this->createFile((int) $keptTestAccount->id());
    $keptTestAccount->set('field_emptied_file', $file->id())->set('field_kept_file', $file->id())->save();

    $this->sanitize(new Configuration(keptUserNames: ['keptTestAccount'], emptyFields: ['user.user.field_emptied_file']), [FieldEmptier::class, UnusedFileDeleter::class]);

    $this->assertNotNull(File::load($file->id()));
  }

  public function testFileUsedByAnotherModuleSurvivesEmptyingItsField(): void {
    $this->addFileField('field_emptied_file');
    $keptTestAccount = $this->createUser('keptTestAccount');
    $file = $this->createFile((int) $keptTestAccount->id());
    $keptTestAccount->set('field_emptied_file', $file->id())->save();
    $this->container->get('file.usage')->add($file, self::MODULE_OTHER_THAN_FILE, 'node', 1);

    $this->sanitize(new Configuration(keptUserNames: ['keptTestAccount'], emptyFields: ['user.user.field_emptied_file']), [FieldEmptier::class, UnusedFileDeleter::class]);

    $this->assertNotNull(File::load($file->id()));
  }

  public function testFileHeldByFieldSurvivesWithoutUsageRow(): void {
    $this->addFileField('field_kept_file');
    $keptTestAccount = $this->createUser('keptTestAccount');
    $file = $this->createFile((int) $keptTestAccount->id());
    $keptTestAccount->set('field_kept_file', $file->id())->save();
    $this->container->get('database')->delete('file_usage')->execute();

    $this->sanitize(new Configuration(keptUserNames: ['keptTestAccount']), [UnusedFileDeleter::class]);

    $this->assertNotNull(File::load($file->id()));
  }

  public function testFileWithOnlyFileModuleUsageRowIsDeleted(): void {
    $file = $this->createFile((int) $this->createUser('deletedUploader')->id());
    $this->container->get('file.usage')->add($file, 'file', 'node', 1);

    $this->sanitize(stageClasses: [UnusedFileDeleter::class]);

    $this->assertNull(File::load($file->id()));
  }

  public function testFileHeldByFieldOfAdministratorSurvives(): void {
    $this->addFileField('field_file_of_administrator');
    $file = $this->createFile(self::ADMINISTRATOR_UID);
    $administrator = User::load(self::ADMINISTRATOR_UID);
    $administrator->set('field_file_of_administrator', $file->id())->save();

    $this->sanitize(stageClasses: [UnusedFileDeleter::class]);

    $this->assertNotNull(File::load($file->id()));
  }

  public function testFileOfDeletedUserWithoutUsageIsDeleted(): void {
    $file = $this->createFile((int) $this->createUser('deletedUploader')->id());

    $this->sanitize(stageClasses: [UnusedFileDeleter::class]);

    $this->assertNull(File::load($file->id()));
  }

  public function testFileOfDeletedUserUsedByAnotherModuleSurvives(): void {
    $file = $this->createFile((int) $this->createUser('deletedUploader')->id());
    $this->container->get('file.usage')->add($file, self::MODULE_OTHER_THAN_FILE, 'node', 1);

    $this->sanitize(stageClasses: [UnusedFileDeleter::class]);

    $this->assertNotNull(File::load($file->id()));
  }

  public function testFileOfDeletedUserIsRemovedFromDisk(): void {
    $file = $this->createFile((int) $this->createUser('deletedUploader')->id());

    $this->sanitize(stageClasses: [UnusedFileDeleter::class]);

    $this->assertFileDoesNotExist($file->getFileUri());
  }

}
