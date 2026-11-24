<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Kernel;

use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\Stage\TableEmptier;

/**
 * Cases that need nothing beyond plain tables.
 */
final class TablesTest extends SanitizeKernelTestBase {

  protected static $modules = ['system', 'sql_sanitize_extended', 'user'];

  protected function setUp(): void {
    parent::setUp();
    foreach (['history', 'table_in_empty_tables'] as $table) {
      $this->container->get('database')->schema()->createTable($table, [
        'fields' => ['uid' => ['type' => 'int', 'not null' => TRUE]],
      ]);
      $this->container->get('database')->insert($table)->fields(['uid' => self::ADMINISTRATOR_UID])->execute();
    }
  }

  public function testDefaultTableIsEmptied(): void {
    $this->sanitize(stageClasses: [TableEmptier::class]);

    $this->assertSame(0, $this->rowCount('history'));
  }

  public function testTableInEmptyTablesIsEmptiedOnTopOfDefaults(): void {
    $this->sanitize(new Configuration(emptyTables: ['table_in_empty_tables']), [TableEmptier::class]);

    $this->assertSame(0, $this->rowCount('table_in_empty_tables'));
  }

}
