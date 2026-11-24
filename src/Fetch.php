<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended;

use Drupal\Core\Database\Query\SelectInterface;

/**
 * Results of select queries without the nullable statement.
 */
final class Fetch {

  /**
   * @return list<int|string>
   */
  public static function column(SelectInterface $query): array {
    return array_values($query->execute()?->fetchCol() ?? []);
  }

  public static function count(SelectInterface $query): int {
    return (int) $query->countQuery()->execute()?->fetchField();
  }

}
