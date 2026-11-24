<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended;

/**
 * What is emptied unless an option says otherwise, grouped by the module that
 * provides it.
 *
 * An entry applies when its table exists or its entity type is defined, not
 * when the module is installed: that also catches a table a crashed uninstall
 * left behind.
 */
final class Defaults {

  /**
   * @var array<string, array{tables: list<string>, entityTypes: list<string>}>
   */
  public const BY_MODULE = [
    'system' => ['tables' => ['flood', 'sessions', 'key_value_expire', 'queue', 'batch'], 'entityTypes' => []],
    'dblog' => ['tables' => ['watchdog'], 'entityTypes' => []],
    'history' => ['tables' => ['history'], 'entityTypes' => []],
    'search' => ['tables' => ['search_index', 'search_dataset', 'search_total'], 'entityTypes' => []],
    'comment' => ['tables' => [], 'entityTypes' => ['comment']],
    'webform' => ['tables' => ['webform_submission_log'], 'entityTypes' => ['webform_submission']],
    'simple_oauth' => ['tables' => [], 'entityTypes' => ['oauth2_token']],
    'queue_unique' => ['tables' => ['queue_unique'], 'entityTypes' => []],
    'purge' => ['tables' => ['purge_queue'], 'entityTypes' => []],
    'honeypot' => ['tables' => ['honeypot_user'], 'entityTypes' => []],
    'captcha' => ['tables' => ['captcha_sessions'], 'entityTypes' => []],
    'poll' => ['tables' => ['poll_vote'], 'entityTypes' => []],
  ];

  /**
   * Caches, and the data of deleted fields that Drupal purges later.
   */
  public const TABLE_PREFIXES = ['cache_', 'old_', 'field_deleted_data_', 'field_deleted_revision_'];

  /**
   * @return list<string>
   */
  public static function tables(): array {
    return array_merge(...array_column(self::BY_MODULE, 'tables'));
  }

  /**
   * @return list<string>
   */
  public static function entityTypes(): array {
    return array_merge(...array_column(self::BY_MODULE, 'entityTypes'));
  }

}
