<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Stage;

use Drupal\Core\Database\Connection;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\Fetch;

/**
 * The users that are not deleted: anonymous, uid 1, the users named in
 * `--kept-user-names` and the users with a role in `--ignored-roles`.
 */
final class KeptAccounts {

  public function __construct(
    private readonly Connection $connection,
    private readonly Configuration $configuration,
  ) {
  }

  /**
   * @return list<int>
   */
  public function uids(): array {
    return self::keptUids($this->configuration, $this->uidsByUserName(), $this->uidsByRole());
  }

  /**
   * @return array{errors: list<string>, warnings: list<string>}
   */
  public function validate(): array {
    return self::problems($this->configuration, $this->uidsByUserName());
  }

  /**
   * What the prompt says about users: what is kept, and how many go.
   *
   * Only what the options hold is named, never a user name read from the
   * database: members of a kept role can be real people.
   */
  public function confirmation(): string {
    $deletedCount = Fetch::count($this->connection->select('users', 'u')->condition('u.uid', $this->uids(), 'NOT IN'));
    $roleUserCounts = array_map('count', $this->uidsByRole());
    return self::describe($this->configuration, $deletedCount, $roleUserCounts);
  }

  /**
   * @param array<string, int> $uidsByUserName
   *   Existing accounts, keyed by lowercased user name.
   *
   * @return array{errors: list<string>, warnings: list<string>}
   */
  public static function problems(Configuration $configuration, array $uidsByUserName): array {
    $warnings = [];
    foreach ($configuration->keptUserNames as $userName) {
      if (!isset($uidsByUserName[mb_strtolower($userName)])) {
        $warnings[] = "--kept-user-names: no user named \"$userName\"; ignored.";
      }
    }
    return ['errors' => [], 'warnings' => $warnings];
  }

  /**
   * @param array<string, int> $roleUserCounts
   *   Users per ignored role; a role that is absent has none.
   */
  public static function describe(Configuration $configuration, int $deletedCount, array $roleUserCounts): string {
    $kept = ['uid 0', 'uid 1'];
    if ($configuration->keptUserNames !== []) {
      $kept[] = 'users named ' . implode(', ', $configuration->keptUserNames);
    }
    foreach ($configuration->ignoredRoles as $role) {
      $kept[] = sprintf('users with role %s (%d)', $role, $roleUserCounts[$role] ?? 0);
    }
    return sprintf('Delete %d users. Keep %s.', $deletedCount, implode('; ', $kept));
  }

  /**
   * @param array<string, int> $uidsByUserName
   *   Existing accounts, keyed by lowercased user name.
   * @param array<string, list<int>> $uidsByRole
   *   The users of each ignored role.
   *
   * @return list<int>
   */
  public static function keptUids(Configuration $configuration, array $uidsByUserName, array $uidsByRole): array {
    $uids = [0, 1, ...array_merge([], ...array_values($uidsByRole))];
    foreach ($configuration->keptUserNames as $userName) {
      if (isset($uidsByUserName[mb_strtolower($userName)])) {
        $uids[] = $uidsByUserName[mb_strtolower($userName)];
      }
    }
    $uids = array_unique($uids);
    sort($uids);
    return $uids;
  }

  /**
   * @return array<string, int>
   */
  private function uidsByUserName(): array {
    if ($this->configuration->keptUserNames === []) {
      return [];
    }
    $uidsByUserName = [];
    $rows = $this->connection->select('users_field_data', 'u')
      ->fields('u', ['name', 'uid'])
      ->condition('name', $this->configuration->keptUserNames, 'IN')
      ->execute();
    foreach ($rows ?? [] as $row) {
      $uidsByUserName[mb_strtolower($row->name)] = (int) $row->uid;
    }
    return $uidsByUserName;
  }

  /**
   * @return array<string, list<int>>
   */
  private function uidsByRole(): array {
    if ($this->configuration->ignoredRoles === []) {
      return [];
    }
    $uidsByRole = [];
    $rows = $this->connection->select('user__roles', 'r')
      ->fields('r', ['roles_target_id', 'entity_id'])
      ->condition('roles_target_id', $this->configuration->ignoredRoles, 'IN')
      ->execute();
    foreach ($rows ?? [] as $row) {
      $uidsByRole[$row->roles_target_id][] = (int) $row->entity_id;
    }
    return $uidsByRole;
  }

}
