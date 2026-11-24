<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Stage;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\webform\WebformSubmissionStorageInterface;
use Psr\Log\LoggerInterface;
use Drupal\sql_sanitize_extended\BatchDeleter;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\Defaults;
use Drupal\sql_sanitize_extended\SqlContentEntityTypes;

/**
 * Deletes every entity of the types that hold personal data by nature.
 */
final class EntityTypeEmptier implements Stage {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SqlContentEntityTypes $contentEntityTypes,
    private readonly BatchDeleter $batchDeleter,
    private readonly WebformSubmissionEmptier $webformSubmissionEmptier,
    private readonly LoggerInterface $logger,
    private readonly Configuration $configuration,
  ) {
  }

  public function validate(): array {
    return self::problems($this->configuration, array_keys($this->entityTypeManager->getDefinitions()), $this->contentEntityTypes->ids());
  }

  public function confirmation(): ?string {
    return EmptyingPlan::describe('entity types', self::entityTypesToEmpty($this->configuration, $this->contentEntityTypes->ids()));
  }

  public function run(): void {
    $sorted = self::entityTypesToEmpty($this->configuration, $this->contentEntityTypes->ids());
    EmptyingPlan::log($this->logger, 'entity types', $sorted);
    foreach ($sorted['emptied'] as $entityTypeId) {
      $this->emptyEntityType($entityTypeId);
    }
  }

  /**
   * @param list<string> $installedEntityTypeIds
   * @param list<string> $contentEntityTypeIds
   *   Content entity types with SQL storage.
   *
   * @return array{errors: list<string>, warnings: list<string>}
   */
  public static function problems(Configuration $configuration, array $installedEntityTypeIds, array $contentEntityTypeIds): array {
    $errors = [];
    $warnings = [];
    foreach ($configuration->allowlistEntityTypes as $entityTypeId) {
      if (!in_array($entityTypeId, $installedEntityTypeIds, TRUE)) {
        $warnings[] = "--allowlist-entity-types: no entity type \"$entityTypeId\"; ignored.";
      }
      elseif (!in_array($entityTypeId, Defaults::entityTypes(), TRUE)) {
        $errors[] = "--allowlist-entity-types: \"$entityTypeId\" is not in the default list of entity types to empty.";
      }
    }
    foreach ($configuration->emptyEntityTypes as $entityTypeId) {
      if (in_array($entityTypeId, $configuration->allowlistEntityTypes, TRUE)) {
        $errors[] = "\"$entityTypeId\" is in both --allowlist-entity-types and --empty-entity-types.";
      }
      if ($entityTypeId === 'user') {
        $errors[] = '--empty-entity-types: "user" is not supported; users are handled by --kept-user-names and --ignored-roles.';
      }
      elseif (!in_array($entityTypeId, $installedEntityTypeIds, TRUE)) {
        $warnings[] = "--empty-entity-types: no entity type \"$entityTypeId\"; ignored.";
      }
      elseif (!in_array($entityTypeId, $contentEntityTypeIds, TRUE)) {
        $errors[] = "--empty-entity-types: \"$entityTypeId\" is not a content entity type with SQL storage.";
      }
    }
    return ['errors' => $errors, 'warnings' => $warnings];
  }

  /**
   * Sorts entity types into emptied, kept and skipped because not installed.
   *
   * @param list<string> $entityTypeIds
   *
   * @return array{emptied: list<string>, kept: list<string>, missing: list<string>}
   */
  public static function entityTypesToEmpty(Configuration $configuration, array $entityTypeIds): array {
    return EmptyingPlan::sort(Defaults::entityTypes(), $configuration->allowlistEntityTypes, $configuration->emptyEntityTypes, $entityTypeIds);
  }

  private function emptyEntityType(string $entityTypeId): void {
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    if ($entityTypeId === 'webform_submission' && $storage instanceof WebformSubmissionStorageInterface) {
      $count = $this->webformSubmissionEmptier->emptyAll($storage);
    }
    else {
      $idKey = (string) $storage->getEntityType()->getKey('id');
      $count = $this->batchDeleter->deleteBatches(
        $entityTypeId,
        fn () => $storage->getQuery()->accessCheck(FALSE)->sort($idKey)->range(0, BatchDeleter::BATCH_SIZE)->execute(),
        "$entityTypeId entities",
      );
    }
    $this->logger->notice('Emptied entity type {entityType}, {count} entities', ['entityType' => $entityTypeId, 'count' => $count]);
  }

}
