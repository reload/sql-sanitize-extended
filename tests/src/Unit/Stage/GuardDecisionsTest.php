<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Unit\Stage;

use PHPUnit\Framework\TestCase;
use Drupal\sql_sanitize_extended\Stage\Guard;
use Drupal\sql_sanitize_extended\UserReference\Column;

final class GuardDecisionsTest extends TestCase {

  public function testGuardColumnsExemptKeptTables(): void {
    $columns = Guard::guardColumns([], [['table' => 'history', 'column' => 'uid'], ['table' => 'authmap', 'column' => 'uid']], ['history']);

    $this->assertSame(['authmap.uid'], $this->tableColumnNames($columns));
  }

  public function testGuardColumnFoundByNameAndByEntityIsCheckedOnce(): void {
    $columns = Guard::guardColumns([$this->nodeOwnerColumn()], [['table' => 'node_field_data', 'column' => 'uid']], []);

    $this->assertSame(['node_field_data.uid'], $this->tableColumnNames($columns));
  }

  public function testGuardColumnOfEntityKeepsItsBundles(): void {
    $columns = Guard::guardColumns([$this->nodeOwnerColumn()], [], []);

    $this->assertSame(['page'], $columns[0]->bundles);
  }

  public function testGuardColumnsExemptOnlyTheOwnIdOfUsers(): void {
    $columns = Guard::guardColumns([], [['table' => 'users_field_data', 'column' => 'uid'], ['table' => 'users', 'column' => 'uid'], ['table' => 'users_field_data', 'column' => 'invited_by_uid']], []);

    $this->assertSame(['users_field_data.invited_by_uid'], $this->tableColumnNames($columns));
  }

  public function testGuardViolationsNameOnlyColumnsWithRows(): void {
    $violations = Guard::guardViolations([
      ['table' => 'authmap', 'column' => 'uid', 'count' => 2],
      ['table' => 'history', 'column' => 'uid', 'count' => 0],
    ]);

    $this->assertSame(['authmap.uid: 2 rows point to a user that does not exist.'], $violations);
  }

  /**
   * @param list<Column> $columns
   *
   * @return list<string>
   */
  private function tableColumnNames(array $columns): array {
    return array_map(fn (Column $column) => $column->userIdTable . '.' . $column->userIdColumn, $columns);
  }

  private function nodeOwnerColumn(): Column {
    return new Column('node', 'node_field_data', 'uid', Column::REASSIGN, 'nid', 'type', ['page']);
  }

}
