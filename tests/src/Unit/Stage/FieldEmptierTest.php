<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Unit\Stage;

use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\Stage\FieldEmptier;
use PHPUnit\Framework\TestCase;

final class FieldEmptierTest extends TestCase {

  public function testBaseFieldIsRejectedWithMessage(): void {
    $result = FieldEmptier::problems(new Configuration(emptyFields: ['node.page.title']), ['node.page.title' => FieldEmptier::BASE_FIELD]);

    $this->assertSame(['--empty-fields: "node.page.title" is a base field; only configurable fields are supported.'], $result['errors']);
  }

  public function testFieldWithoutOwnTablesIsRejectedWithMessage(): void {
    $result = FieldEmptier::problems(new Configuration(emptyFields: ['node.page.field_computed']), ['node.page.field_computed' => FieldEmptier::WITHOUT_OWN_TABLES]);

    $this->assertSame(['--empty-fields: "node.page.field_computed" is computed or has custom storage, so it has no tables of its own to empty.'], $result['errors']);
  }

  public function testEntryThatIsNotThreePartsIsRejectedWithMessage(): void {
    $result = FieldEmptier::problems(new Configuration(emptyFields: ['node.field_body']), []);

    $this->assertSame(['--empty-fields: "node.field_body" must be <entity type>.<bundle>.<field>.'], $result['errors']);
  }

  public function testNameThatMatchesNothingIsWarnedAboutAndNotAnError(): void {
    $result = FieldEmptier::problems(new Configuration(emptyFields: ['node.page.typo']), ['node.page.field_body' => FieldEmptier::WITH_OWN_TABLES]);

    $this->assertSame(['--empty-fields: no field "node.page.typo"; ignored.'], $result['warnings']);
    $this->assertSame([], $result['errors']);
  }

  public function testValidConfigurationHasNoErrorsOrWarnings(): void {
    $result = FieldEmptier::problems(new Configuration(emptyFields: ['node.page.field_body']), ['node.page.field_body' => FieldEmptier::WITH_OWN_TABLES]);

    $this->assertSame(['errors' => [], 'warnings' => []], $result);
  }

}
