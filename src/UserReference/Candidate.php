<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\UserReference;

/**
 * One stored column of one bundle's entity reference field, as plain data.
 */
final class Candidate {

  public function __construct(
    public readonly string $entityTypeId,
    public readonly string $bundle,
    public readonly string $fieldName,
    public readonly bool $isBaseField,
    public readonly bool $isRequired,
    public readonly string $userIdTable,
    public readonly string $userIdColumn,
    public readonly string $idFieldName,
    public readonly string $idColumn,
    public readonly ?string $bundleColumn,
  ) {
  }

}
