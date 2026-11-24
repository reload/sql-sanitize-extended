<?php

declare(strict_types=1);

namespace Drupal\Tests\sql_sanitize_extended\Unit\Stage;

use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\Stage\KeptAccounts;
use PHPUnit\Framework\TestCase;

final class KeptAccountsTest extends TestCase {

  public function testAnonymousAndAdministratorAreAlwaysKept(): void {
    $this->assertSame([0, 1], KeptAccounts::keptUids(new Configuration(), [], []));
  }

  public function testAccountNamedInOptionIsKeptIgnoringCase(): void {
    $configuration = new Configuration(keptUserNames: ['KeptTestAccount']);

    $this->assertSame([0, 1, 7], KeptAccounts::keptUids($configuration, ['kepttestaccount' => 7], []));
  }

  public function testAccountsOfIgnoredRolesAreKeptOnceEach(): void {
    $configuration = new Configuration(ignoredRoles: ['editor', 'tester']);

    $this->assertSame([0, 1, 5, 9], KeptAccounts::keptUids($configuration, [], ['editor' => [9, 5], 'tester' => [9]]));
  }

  public function testNameThatMatchesNothingKeepsNoExtraAccount(): void {
    $this->assertSame([0, 1], KeptAccounts::keptUids(new Configuration(keptUserNames: ['unknown']), ['kepttestaccount' => 7], []));
  }

  public function testNameInOtherCaseThanStoredIsNotWarnedAbout(): void {
    $this->assertSame(['errors' => [], 'warnings' => []], KeptAccounts::problems(new Configuration(keptUserNames: ['TestEditor']), ['testeditor' => 7]));
  }

  public function testNameThatMatchesNothingIsWarnedAboutAndNotAnError(): void {
    $result = KeptAccounts::problems(new Configuration(keptUserNames: ['typo']), ['kepttestaccount' => 7]);

    $this->assertSame(['--kept-user-names: no user named "typo"; ignored.'], $result['warnings']);
    $this->assertSame([], $result['errors']);
  }

  public function testConfirmationNamesOnlyWhatTheOptionsHold(): void {
    $configuration = new Configuration(keptUserNames: ['testmedlem'], ignoredRoles: ['editor', 'tester']);

    $this->assertSame(
      'Delete 12 users. Keep uid 0; uid 1; users named testmedlem; users with role editor (3); users with role tester (0).',
      KeptAccounts::describe($configuration, 12, ['editor' => 3]),
    );
  }

}
