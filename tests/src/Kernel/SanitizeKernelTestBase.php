<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\SanitizerFactory;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Builds a fixture as entities, then runs the steps on the container.
 */
abstract class SanitizeKernelTestBase extends KernelTestBase {

  protected const ANONYMOUS_UID = 0;

  protected const ADMINISTRATOR_UID = 1;

  protected const UID_WITHOUT_ACCOUNT = 999;

  protected UserInterface $administrator;

  protected RecordingLogger $logger;

  protected function setUp(): void {
    parent::setUp();
    $this->logger = new RecordingLogger();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->administrator = $this->createUser('administrator', self::ADMINISTRATOR_UID);
  }

  protected function createUser(string $name, ?int $uid = NULL): UserInterface {
    $user = User::create([
      'uid' => $uid,
      'name' => $name,
      'mail' => "$name@example.com",
      'init' => "$name@example.com",
      'pass' => "password-of-$name",
      'status' => 1,
    ]);
    $user->save();
    return $user;
  }

  /**
   * @param list<class-string<\Drupal\sql_sanitize_extended\Stage\Stage>>|null $stageClasses
   *   The stages to run, in pipeline order; all of them when NULL.
   */
  protected function sanitize(?Configuration $configuration = NULL, ?array $stageClasses = NULL): void {
    foreach ($this->factory()->stages($configuration ?? new Configuration(), $this->logger) as $stage) {
      if ($stageClasses === NULL || in_array($stage::class, $stageClasses, TRUE)) {
        $stage->run();
      }
    }
  }

  protected function guard(?Configuration $configuration = NULL): void {
    $this->factory()->guard($configuration ?? new Configuration(), $this->logger)->run();
  }

  protected function factory(): SanitizerFactory {
    return $this->container->get(SanitizerFactory::class);
  }

  /**
   * @param array<string, mixed> $conditions
   */
  protected function rowCount(string $table, array $conditions = []): int {
    $query = $this->container->get('database')->select($table, 't');
    foreach ($conditions as $column => $value) {
      $query->condition($column, $value);
    }
    return (int) $query->countQuery()->execute()->fetchField();
  }

  /**
   * @return list<int>
   */
  protected function remainingUids(): array {
    $uids = array_map('intval', $this->container->get('database')->select('users', 'u')->fields('u', ['uid'])->execute()->fetchCol());
    sort($uids);
    return $uids;
  }

}
