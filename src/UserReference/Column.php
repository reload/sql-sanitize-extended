<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\UserReference;

/**
 * A column holding user ids, and what the sanitizer does with it.
 */
final class Column {

  public const REASSIGN = 'reassign';
  public const DELETE = 'delete';
  public const SKIP = 'skip';
  public const BY_NAME = 'by-name';

  /**
   * @param self::REASSIGN|self::DELETE|self::SKIP|self::BY_NAME $action
   * @param string|null $entityTypeId
   *   NULL for columns that no entity owns: by-name columns and the comment
   *   statistics.
   * @param string|null $entityIdColumn
   *   NULL for by-name columns, whose table holds no entity id.
   * @param list<string>|null $bundles
   *   Bundles the rule applies to, or NULL for every row of the table.
   */
  public function __construct(
    public readonly ?string $entityTypeId,
    public readonly string $userIdTable,
    public readonly string $userIdColumn,
    public readonly string $action,
    public readonly ?string $entityIdColumn,
    public readonly ?string $bundleColumn,
    public readonly ?array $bundles,
  ) {
  }

}
