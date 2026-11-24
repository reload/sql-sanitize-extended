<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Functional;

use Drupal\Component\Serialization\Yaml;
use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Runs `drush sql:sanitize` against the test site, as a separate process.
 */
#[Group('sql_sanitize_extended')]
#[RunTestsInSeparateProcesses]
final class SanitizeExtendedTest extends BrowserTestBase {

  use DrushTestTrait;

  private const UID_WITHOUT_ACCOUNT = 999;

  protected static $modules = ['sql_sanitize_extended'];

  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateUser([], 'leaver');
    $this->drupalCreateUser([], 'keptByName');
    $this->drupalCreateRole([], 'editor');
    $keptByRole = $this->drupalCreateUser([], 'keptByRole');
    $keptByRole->addRole('editor')->save();
  }

  /**
   * No web server runs, so skip the front page visit.
   */
  protected function initFrontPage(): void {
  }

  public function testRunsByDefault(): void {
    $this->drush('sql:sanitize');

    $this->assertSame([], $this->userNamesAboveUidOne());
  }

  public function testOptionsInDrushYmlTakeEffect(): void {
    $this->configureOptions(['ignored-roles' => 'editor', 'kept-user-names' => 'keptByName']);

    $this->drush('sql:sanitize');

    $this->assertSame(['keptByName', 'keptByRole'], $this->userNamesAboveUidOne());
  }

  public function testSanitizeExtendedNoSkipsEverything(): void {
    // Invalid, so the run only passes if nothing is validated.
    $this->drush('sql:sanitize', [], ['sanitize-extended' => 'no', 'empty-fields' => 'malformed']);

    $this->assertSame(['leaver', 'keptByName', 'keptByRole'], $this->userNamesAboveUidOne());
  }

  public function testSanitizeExtendedNoInDrushYmlSkipsEverything(): void {
    $this->configureOptions(['sanitize-extended' => 'no', 'empty-fields' => 'malformed']);

    $this->drush('sql:sanitize');

    $this->assertSame(['leaver', 'keptByName', 'keptByRole'], $this->userNamesAboveUidOne());
  }

  public function testDefaultTableIsEmptied(): void {
    \Drupal::flood()->register('planted_event', 60, 'planted-identifier');
    $this->assertSame(1, $this->rowCount('flood'));

    $this->drush('sql:sanitize');

    $this->assertSame(0, $this->rowCount('flood'));
  }

  public function testOtherCommandsIgnoreDanglingUserIds(): void {
    $this->plantDanglingUserId();

    $this->drush('status');

    $this->assertSame(0, $this->process->getExitCode());
  }

  public function testAnsweringNoExitsWithOne(): void {
    $this->plantDanglingUserId();

    $this->drush('sql:sanitize', [], ['no' => NULL], NULL, NULL, 1);

    $this->assertStringContainsString('Cancelled.', $this->getErrorOutput());
  }

  public function testAnsweringNoLeavesDatabaseUntouched(): void {
    $this->plantDanglingUserId();

    $this->drush('sql:sanitize', [], ['no' => NULL], NULL, NULL, 1);

    $this->assertSame(['leaver', 'keptByName', 'keptByRole'], $this->userNamesAboveUidOne());
  }

  public function testAnsweringNoLeavesGuardSilent(): void {
    $this->plantDanglingUserId();

    $this->drush('sql:sanitize', [], ['no' => NULL], NULL, NULL, 1);

    $this->assertStringNotContainsString('Personal data may remain', $this->getErrorOutput());
  }

  public function testInvalidOptionExitsWithOne(): void {
    $this->drush('sql:sanitize', [], ['empty-fields' => 'malformed'], NULL, NULL, 1);

    $this->assertSame(1, $this->process->getExitCode());
  }

  public function testInvalidOptionLeavesDatabaseUntouched(): void {
    $this->drush('sql:sanitize', [], ['empty-fields' => 'malformed'], NULL, NULL, 1);

    $this->assertSame(['leaver', 'keptByName', 'keptByRole'], $this->userNamesAboveUidOne());
  }

  /**
   * @param array<string, string> $options
   */
  private function configureOptions(array $options): void {
    $drushYml = Yaml::encode(['command' => ['sql' => ['sanitize' => ['options' => $options]]]]);
    file_put_contents(DRUPAL_ROOT . '/' . $this->siteDirectory . '/drush.yml', $drushYml);
  }

  /**
   * @return list<string>
   */
  private function userNamesAboveUidOne(): array {
    return array_values(\Drupal::database()->query('SELECT name FROM {users_field_data} WHERE uid > 1 ORDER BY uid')->fetchCol());
  }

  private function rowCount(string $table): int {
    return (int) \Drupal::database()->select($table)->countQuery()->execute()->fetchField();
  }

  private function plantDanglingUserId(): void {
    \Drupal::database()->insert('users_data')
      ->fields(['uid' => self::UID_WITHOUT_ACCOUNT, 'module' => 'planted', 'name' => 'planted', 'value' => 'x', 'serialized' => 0])
      ->execute();
  }

}
