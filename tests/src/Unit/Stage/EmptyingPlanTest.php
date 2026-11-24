<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Unit\Stage;

use Drupal\sql_sanitize_extended\Stage\EmptyingPlan;
use PHPUnit\Framework\TestCase;

final class EmptyingPlanTest extends TestCase {

  public function testPrefixIsShownOnceInsteadOfEveryTableWithIt(): void {
    $plan = ['emptied' => ['watchdog', 'cache_render', 'cache_page'], 'kept' => [], 'missing' => []];

    $this->assertSame('Empty tables: watchdog, cache_*.', EmptyingPlan::describe('tables', $plan, ['cache_', 'old_']));
  }

  public function testKeptNamesAreListedAsLeftAlone(): void {
    $plan = ['emptied' => ['comment'], 'kept' => ['oauth2_token'], 'missing' => ['webform_submission']];

    $this->assertSame('Empty entity types: comment. Leave alone: oauth2_token.', EmptyingPlan::describe('entity types', $plan));
  }

  public function testNothingToEmptyOrKeepHasNoLine(): void {
    $this->assertNull(EmptyingPlan::describe('tables', ['emptied' => [], 'kept' => [], 'missing' => ['watchdog']]));
  }

}
