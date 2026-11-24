<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Kernel;

use Drupal\sql_sanitize_extended\Stage\EntityTypeEmptier;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

final class WebformSubmissionsTest extends SanitizeKernelTestBase {

  protected static $modules = ['system', 'sql_sanitize_extended', 'user', 'field', 'file', 'webform', 'sanitizer_recording_handler'];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('webform_submission');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('webform', ['webform']);
    $this->installConfig(['webform']);
  }

  private function createWebformWithRecordingHandler(string $id): Webform {
    $webform = Webform::create(['id' => $id, 'elements' => "name:\n  '#type': textfield\nattachment:\n  '#type': managed_file\n"]);
    $handler = $this->container->get('plugin.manager.webform.handler')->createInstance('sanitizer_record_delete');
    $handler->setHandlerId('record_delete');
    $webform->addWebformHandler($handler);
    $webform->save();
    return $webform;
  }

  private function submit(string $webformId, int $uid, array $data = ['name' => 'Ada']): WebformSubmission {
    $submission = WebformSubmission::create(['webform_id' => $webformId, 'uid' => $uid, 'data' => $data]);
    $submission->save();
    return $submission;
  }

  private function handlerDeleteCount(): int {
    return (int) $this->container->get('state')->get('sanitizer_recording_handler.deletes', 0);
  }

  public function testSanitizingRunsNoWebformHandler(): void {
    $this->createWebformWithRecordingHandler('webform_with_handler');
    $this->createWebformWithRecordingHandler('another_webform_with_handler');
    $this->submit('webform_with_handler', self::ADMINISTRATOR_UID);
    $this->submit('another_webform_with_handler', self::ADMINISTRATOR_UID);

    $this->sanitize(stageClasses: [EntityTypeEmptier::class]);

    $this->assertSame(0, $this->handlerDeleteCount());
  }

  public function testSanitizingRunsNoHandlerOfWebformEvictedFromCache(): void {
    $this->createWebformWithRecordingHandler('webform_with_handler');
    $this->submit('webform_with_handler', self::ADMINISTRATOR_UID);
    $this->submit('webform_with_handler', self::ADMINISTRATOR_UID);
    $this->container->get('state')->set('sanitizer_recording_handler.evict_webforms', TRUE);

    $this->sanitize(stageClasses: [EntityTypeEmptier::class]);

    $this->assertSame(0, $this->handlerDeleteCount());
  }

  public function testSanitizingLeavesWebformHandlersEnabledInConfiguration(): void {
    $this->createWebformWithRecordingHandler('webform_with_handler');
    $this->submit('webform_with_handler', self::ADMINISTRATOR_UID);

    $this->sanitize(stageClasses: [EntityTypeEmptier::class]);

    $this->container->get('entity_type.manager')->getStorage('webform')->resetCache();
    $webform = Webform::load('webform_with_handler');
    $this->assertTrue($webform->getHandler('record_delete')->isEnabled());
  }

  public function testSubmissionsAreDeleted(): void {
    $this->createWebformWithRecordingHandler('webform_with_handler');
    $this->submit('webform_with_handler', self::ADMINISTRATOR_UID);

    $this->sanitize(stageClasses: [EntityTypeEmptier::class]);

    $this->assertSame(0, $this->rowCount('webform_submission'));
  }

  public function testSubmissionAndItsDataOfDeletedWebformAreDeleted(): void {
    $this->createWebformWithRecordingHandler('webform_with_handler');
    $this->submit('webform_with_handler', self::ADMINISTRATOR_UID);
    $this->config('webform.webform.webform_with_handler')->delete();
    $this->container->get('entity_type.manager')->getStorage('webform')->resetCache();

    $this->sanitize(stageClasses: [EntityTypeEmptier::class]);

    $this->assertSame([0, 0], [$this->rowCount('webform_submission'), $this->rowCount('webform_submission_data')]);
  }

}
