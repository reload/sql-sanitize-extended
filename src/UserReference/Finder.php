<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\UserReference;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\ConditionInterface;

/**
 * Finds the columns holding user ids, and decides what happens to each.
 */
final class Finder {

  /**
   * References deleted with the user, so reassigning would keep them alive.
   */
  public const SKIPPED_REFERENCES = [
    'group_relationship.entity_id',
    // Group 2 names the entity type group_content.
    'group_content.entity_id',
    'flagging.uid',
    'comment.entity_id',
  ];

  public function __construct(
    private readonly Connection $connection,
    private readonly CandidateReader $candidateReader,
  ) {
  }

  /**
   * The entity columns, then the extra ones whose table has the column.
   *
   * @return list<Column>
   */
  public function all(): array {
    $schema = $this->connection->schema();
    $extraColumns = array_values(array_filter(self::extraReassignColumns(), fn (Column $column) => $schema->fieldExists($column->userIdTable, $column->userIdColumn)));
    $bundlesByEntityTypeId = $this->candidateReader->bundlesByEntityTypeId();
    return [...self::columns($this->candidateReader->candidates($bundlesByEntityTypeId), $bundlesByEntityTypeId), ...$extraColumns];
  }

  /**
   * @param string $action
   *   One of the Column action constants.
   *
   * @return list<Column>
   */
  public function withAction(string $action): array {
    return array_values(array_filter($this->all(), fn (Column $column) => $column->action === $action));
  }

  /**
   * @template T of ConditionInterface
   *
   * @param T $query
   *
   * @return T
   */
  public static function restrictToBundles(ConditionInterface $query, Column $column): ConditionInterface {
    if ($column->bundles !== NULL && $column->bundleColumn !== NULL) {
      $query->condition($column->bundleColumn, $column->bundles, 'IN');
    }
    return $query;
  }

  /**
   * Columns holding user ids that no entity field definition describes.
   *
   * @return list<Column>
   */
  public static function extraReassignColumns(): array {
    return [
      new Column(NULL, 'comment_entity_statistics', 'last_comment_uid', Column::REASSIGN, 'entity_id', NULL, NULL),
    ];
  }

  /**
   * Groups the candidates into columns, and decides the action of each.
   *
   * Base fields are always reassigned; configurable fields are reassigned
   * when required and have their rows deleted when optional.
   *
   * @param list<Candidate> $candidates
   * @param array<string, list<string>> $bundlesByEntityTypeId
   *
   * @return list<Column>
   *
   * @throws \RuntimeException
   *   When a column covers some bundles of a table without a bundle column.
   */
  public static function columns(array $candidates, array $bundlesByEntityTypeId): array {
    $groups = [];
    foreach ($candidates as $candidate) {
      if ($candidate->fieldName === $candidate->idFieldName) {
        continue;
      }
      $action = match (TRUE) {
        in_array($candidate->entityTypeId . '.' . $candidate->fieldName, self::SKIPPED_REFERENCES, TRUE) => Column::SKIP,
        $candidate->isBaseField, $candidate->isRequired => Column::REASSIGN,
        default => Column::DELETE,
      };
      $key = implode("\0", [$candidate->userIdTable, $candidate->userIdColumn, $action]);
      $groups[$key]['candidate'] = $candidate;
      $groups[$key]['action'] = $action;
      $groups[$key]['bundles'][] = $candidate->bundle;
    }

    $columns = [];
    foreach ($groups as $group) {
      $candidate = $group['candidate'];
      $allBundles = $bundlesByEntityTypeId[$candidate->entityTypeId] ?? [];
      $bundles = array_values(array_unique($group['bundles']));
      $coversEntityType = array_diff($allBundles, $bundles) === [];
      if (!$coversEntityType && $candidate->bundleColumn === NULL) {
        throw new \RuntimeException("$candidate->userIdTable.$candidate->userIdColumn holds user ids for some bundles of $candidate->entityTypeId only, but the table has no bundle column to tell them apart.");
      }
      $columns[] = new Column(
        $candidate->entityTypeId,
        $candidate->userIdTable,
        $candidate->userIdColumn,
        $group['action'],
        $candidate->idColumn,
        $candidate->bundleColumn,
        $coversEntityType ? NULL : $bundles,
      );
    }
    return $columns;
  }

}
