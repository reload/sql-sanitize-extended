<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Stage;

use Psr\Log\LoggerInterface;

/**
 * What a stage empties, keeps and skips because the name matches nothing.
 */
final class EmptyingPlan {

  /**
   * @param list<string> $defaults
   * @param list<string> $kept
   * @param list<string> $configured
   * @param list<string> $existing
   *
   * @return array{emptied: list<string>, kept: list<string>, missing: list<string>}
   */
  public static function sort(array $defaults, array $kept, array $configured, array $existing): array {
    $plan = ['emptied' => [], 'kept' => [], 'missing' => []];
    foreach ($defaults as $name) {
      if (in_array($name, $kept, TRUE)) {
        $plan['kept'][] = $name;
      }
      elseif (in_array($name, $existing, TRUE)) {
        $plan['emptied'][] = $name;
      }
      else {
        $plan['missing'][] = $name;
      }
    }
    foreach ($configured as $name) {
      if (in_array($name, $plan['emptied'], TRUE) || in_array($name, $plan['kept'], TRUE) || in_array($name, $plan['missing'], TRUE)) {
        continue;
      }
      if (in_array($name, $existing, TRUE)) {
        $plan['emptied'][] = $name;
      }
      else {
        $plan['missing'][] = $name;
      }
    }
    return $plan;
  }

  /**
   * The prompt line: what is emptied and what is left alone.
   *
   * @param array{emptied: list<string>, kept: list<string>, missing: list<string>} $plan
   * @param list<string> $prefixes
   *   Shown as `prefix*` instead of every table that has one.
   */
  public static function describe(string $what, array $plan, array $prefixes = []): ?string {
    $emptied = [];
    $patterns = [];
    foreach ($plan['emptied'] as $name) {
      $prefix = array_values(array_filter($prefixes, fn (string $prefix) => str_starts_with($name, $prefix)))[0] ?? NULL;
      if ($prefix === NULL) {
        $emptied[] = $name;
      }
      else {
        $patterns[$prefix] = "$prefix*";
      }
    }
    $emptied = [...$emptied, ...array_values($patterns)];
    if ($emptied === [] && $plan['kept'] === []) {
      return NULL;
    }
    $line = "Empty $what: " . ($emptied === [] ? 'none' : implode(', ', $emptied)) . '.';
    return $plan['kept'] === [] ? $line : "$line Leave alone: " . implode(', ', $plan['kept']) . '.';
  }

  /**
   * @param array{emptied: list<string>, kept: list<string>, missing: list<string>} $plan
   */
  public static function log(LoggerInterface $logger, string $what, array $plan): void {
    $logger->notice('Emptied {what}: {emptied}', ['what' => $what, 'emptied' => implode(', ', $plan['emptied']) ?: 'none']);
    $logger->notice('Kept {what}: {kept}', ['what' => $what, 'kept' => implode(', ', $plan['kept']) ?: 'none']);
    $logger->notice('Skipped {what} that do not exist: {missing}', ['what' => $what, 'missing' => implode(', ', $plan['missing']) ?: 'none']);
  }

}
