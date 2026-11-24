<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Unit\Stage;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Drupal\sql_sanitize_extended\Stage\NamedUserIdColumns;

final class NamedUserIdColumnsTest extends TestCase {

  /**
   * @return array<string, array{string, bool}>
   */
  public static function userIdColumnNames(): array {
    return [
      'uid' => ['uid', TRUE],
      'suffix _uid' => ['revision_uid', TRUE],
      'user_id' => ['user_id', TRUE],
      'uuid' => ['uuid', FALSE],
    ];
  }

  #[DataProvider('userIdColumnNames')]
  public function testUserIdColumnNameIsRecognized(string $column, bool $isUserIdColumn): void {
    $this->assertSame($isUserIdColumn, NamedUserIdColumns::isUserIdColumnName($column));
  }

}
