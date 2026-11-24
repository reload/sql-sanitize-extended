<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended;

/**
 * The module's options and the core option that decides who is kept.
 *
 * Lists are comma-separated strings parsed as core's `sql:sanitize` parses its
 * own, so the module and core agree on who is kept.
 */
final class Configuration {

  /**
   * @param list<string> $keptUserNames
   * @param list<string> $ignoredRoles
   * @param list<string> $allowlistTables
   * @param list<string> $allowlistEntityTypes
   * @param list<string> $emptyTables
   * @param list<string> $emptyEntityTypes
   * @param list<string> $emptyFields
   *   Entries are `<entity type>.<bundle>.<field>`.
   */
  public function __construct(
    public readonly array $keptUserNames = [],
    public readonly array $ignoredRoles = [],
    public readonly array $allowlistTables = [],
    public readonly array $allowlistEntityTypes = [],
    public readonly array $emptyTables = [],
    public readonly array $emptyEntityTypes = [],
    public readonly array $emptyFields = [],
  ) {
  }

  /**
   * @param array<string, mixed> $options
   *   The options of `sql:sanitize` by name.
   */
  public static function fromOptions(array $options): self {
    return new self(
      self::parseList($options['kept-user-names'] ?? NULL),
      self::parseList($options['ignored-roles'] ?? NULL),
      self::parseList($options['allowlist-tables'] ?? NULL),
      self::parseList($options['allowlist-entity-types'] ?? NULL),
      self::parseList($options['empty-tables'] ?? NULL),
      self::parseList($options['empty-entity-types'] ?? NULL),
      self::parseList($options['empty-fields'] ?? NULL),
    );
  }

  /**
   * @return list<string>
   */
  private static function parseList(?string $value): array {
    return $value === NULL || $value === '' ? [] : explode(',', $value);
  }

}
