<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\sql_sanitize_extended\Stage\EntityTypeEmptier;
use Drupal\sql_sanitize_extended\Stage\FieldEmptier;
use Drupal\sql_sanitize_extended\Stage\Guard;
use Drupal\sql_sanitize_extended\Stage\KeptAccounts;
use Drupal\sql_sanitize_extended\Stage\NamedUserIdColumns;
use Drupal\sql_sanitize_extended\Stage\ReferenceReassigner;
use Drupal\sql_sanitize_extended\Stage\TableEmptier;
use Drupal\sql_sanitize_extended\Stage\UnusedFileDeleter;
use Drupal\sql_sanitize_extended\Stage\UserDeleter;
use Drupal\sql_sanitize_extended\Stage\WebformSubmissionEmptier;
use Drupal\sql_sanitize_extended\UserReference\CandidateReader;
use Drupal\sql_sanitize_extended\UserReference\Finder;
use Psr\Log\LoggerInterface;

/**
 * Wires the stages and the guard from Drupal's services.
 */
final class SanitizerFactory {

  public function __construct(
    private readonly Connection $connection,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly FieldTypePluginManagerInterface $fieldTypeManager,
  ) {
  }

  /**
   * @return list<\Drupal\sql_sanitize_extended\Stage\Stage>
   *   In the order they run.
   */
  public function stages(Configuration $configuration, LoggerInterface $logger): array {
    $contentEntityTypes = new SqlContentEntityTypes($this->entityTypeManager);
    $batchDeleter = new BatchDeleter($this->entityTypeManager);
    $userReferenceFinder = new Finder($this->connection, new CandidateReader($this->connection, $contentEntityTypes, $this->entityFieldManager));
    $keptAccounts = new KeptAccounts($this->connection, $configuration);
    return [
      new EntityTypeEmptier($this->entityTypeManager, $contentEntityTypes, $batchDeleter, new WebformSubmissionEmptier($this->entityTypeManager, $this->connection, $batchDeleter), $logger, $configuration),
      new FieldEmptier($this->connection, $this->entityFieldManager, $contentEntityTypes, $logger, $configuration),
      new ReferenceReassigner($this->connection, $this->entityTypeManager, $contentEntityTypes, $userReferenceFinder, $keptAccounts, $logger),
      new UserDeleter($this->connection, $this->entityTypeManager, $contentEntityTypes, $batchDeleter, $userReferenceFinder, $keptAccounts, $logger),
      new UnusedFileDeleter($this->connection, $this->entityTypeManager, $this->entityFieldManager, $this->fieldTypeManager, $contentEntityTypes, $logger),
      new TableEmptier($this->connection, $logger, $configuration),
    ];
  }

  public function guard(Configuration $configuration, LoggerInterface $logger): Guard {
    $contentEntityTypes = new SqlContentEntityTypes($this->entityTypeManager);
    return new Guard(
      $this->connection,
      new Finder($this->connection, new CandidateReader($this->connection, $contentEntityTypes, $this->entityFieldManager)),
      new NamedUserIdColumns($this->connection, $this->moduleHandler, $this->entityFieldManager, $contentEntityTypes),
      $logger,
      $configuration,
    );
  }

}
