<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Kernel;

use Drupal\sql_sanitize_extended\BatchDeleter;

final class BatchDeleterTest extends SanitizeKernelTestBase {

  protected static $modules = ['system', 'sql_sanitize_extended', 'user'];

  public function testBatchThatRemovesNothingThrowsInsteadOfLooping(): void {
    $userId = (int) $this->createUser('deletedUser')->id();
    $batchDeleter = new BatchDeleter($this->container->get('entity_type.manager'));

    $this->expectException(\RuntimeException::class);

    $batchDeleter->deleteBatches('user', static fn () => [$userId], 'users');
  }

}
