<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Unit\UserReference;

use PHPUnit\Framework\TestCase;
use Drupal\sql_sanitize_extended\UserReference\Candidate;
use Drupal\sql_sanitize_extended\UserReference\Column;
use Drupal\sql_sanitize_extended\UserReference\Finder;

final class FinderTest extends TestCase {

  public function testBaseFieldIsReassignedEvenWhenNotRequired(): void {
    $columns = Finder::columns([$this->candidate(['isBaseField' => TRUE])], ['node' => ['page']]);

    $this->assertSame(Column::REASSIGN, $columns[0]->action);
  }

  public function testRequiredConfigurableFieldIsReassigned(): void {
    $columns = Finder::columns([$this->candidate(['isRequired' => TRUE])], ['node' => ['page']]);

    $this->assertSame(Column::REASSIGN, $columns[0]->action);
  }

  public function testOptionalConfigurableFieldHasRowsDeleted(): void {
    $columns = Finder::columns([$this->candidate()], ['node' => ['page']]);

    $this->assertSame(Column::DELETE, $columns[0]->action);
  }

  public function testFieldRequiredInOneBundleOnlyIsSplitPerBundle(): void {
    $candidates = [
      $this->candidate(['bundle' => 'page', 'isRequired' => TRUE]),
      $this->candidate(['bundle' => 'article']),
    ];

    $columns = Finder::columns($candidates, ['node' => ['page', 'article']]);

    $this->assertCount(2, $columns);
    $this->assertSame([Column::REASSIGN, ['page']], [$columns[0]->action, $columns[0]->bundles]);
    $this->assertSame([Column::DELETE, ['article']], [$columns[1]->action, $columns[1]->bundles]);
  }

  public function testFieldOnEveryBundleHasNoBundleCondition(): void {
    $candidates = [$this->candidate(['bundle' => 'page']), $this->candidate(['bundle' => 'article'])];

    $columns = Finder::columns($candidates, ['node' => ['page', 'article']]);

    $this->assertCount(1, $columns);
    $this->assertNull($columns[0]->bundles);
  }

  public function testUserIdFieldOfUserEntityIsNotReference(): void {
    $candidate = $this->candidate(['entityTypeId' => 'user', 'fieldName' => 'uid', 'idFieldName' => 'uid', 'isBaseField' => TRUE]);

    $this->assertSame([], Finder::columns([$candidate], ['user' => ['user']]));
  }

  public function testReferencesDeletedWithTheUserAreSkippedForUserBundlesOnly(): void {
    $candidate = $this->candidate([
      'entityTypeId' => 'group_relationship',
      'bundle' => 'group-group_membership',
      'fieldName' => 'entity_id',
      'isBaseField' => TRUE,
      'userIdTable' => 'group_relationship_field_data',
      'userIdColumn' => 'entity_id',
      'bundleColumn' => 'type',
    ]);

    $columns = Finder::columns([$candidate], ['group_relationship' => ['group-group_membership', 'group-node-article']]);

    $this->assertSame(Column::SKIP, $columns[0]->action);
    $this->assertSame(['group-group_membership'], $columns[0]->bundles);
  }

  public function testFlaggingOwnerIsSkipped(): void {
    $candidate = $this->candidate(['entityTypeId' => 'flagging', 'fieldName' => 'uid', 'isBaseField' => TRUE, 'userIdTable' => 'flagging', 'userIdColumn' => 'uid']);

    $columns = Finder::columns([$candidate], ['flagging' => ['like']]);

    $this->assertSame(Column::SKIP, $columns[0]->action);
  }

  public function testCommentOnUserIsSkipped(): void {
    $candidate = $this->candidate(['entityTypeId' => 'comment', 'fieldName' => 'entity_id', 'isBaseField' => TRUE, 'userIdTable' => 'comment_field_data', 'userIdColumn' => 'entity_id', 'idFieldName' => 'cid']);

    $columns = Finder::columns([$candidate], ['comment' => ['page']]);

    $this->assertSame(Column::SKIP, $columns[0]->action);
  }

  public function testColumnOfSomeBundlesInTableWithoutBundleColumnFailsJob(): void {
    $candidates = [$this->candidate(['bundle' => 'page', 'bundleColumn' => NULL])];

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('node__field_reviewer.field_reviewer_target_id');

    Finder::columns($candidates, ['node' => ['page', 'article']]);
  }

  /**
   * A configurable, optional reference to a user on node page.
   *
   * @param array<string, mixed> $overrides
   */
  private function candidate(array $overrides = []): Candidate {
    $values = $overrides + [
      'entityTypeId' => 'node',
      'bundle' => 'page',
      'fieldName' => 'field_reviewer',
      'isBaseField' => FALSE,
      'isRequired' => FALSE,
      'userIdTable' => 'node__field_reviewer',
      'userIdColumn' => 'field_reviewer_target_id',
      'idFieldName' => 'nid',
      'idColumn' => 'entity_id',
      'bundleColumn' => 'bundle',
    ];
    return new Candidate(...$values);
  }

}
