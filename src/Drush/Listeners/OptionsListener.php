<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Drush\Listeners;

use Drupal\sql_sanitize_extended\Activation;
use Drush\Event\ConsoleDefinitionsEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Adds the module's options to `sql:sanitize`, where `drush.yml` can set them.
 */
#[AsEventListener]
final class OptionsListener {

  private const OPTIONS = [
    Activation::OPTION => 'Set to <info>no</info> to skip the extended sanitizing, its confirmation lines and its guard.',
    'kept-user-names' => 'A comma delimited list of user names, ignoring case. Those users are not deleted.',
    'allowlist-tables' => 'A comma delimited list of tables from the default list to leave alone.',
    'allowlist-entity-types' => 'A comma delimited list of entity types from the default list to leave alone.',
    'empty-tables' => 'A comma delimited list of tables to empty on top of the default list.',
    'empty-entity-types' => 'A comma delimited list of content entity types to empty on top of the default list.',
    'empty-fields' => 'A comma delimited list of fields to empty, each <info><entity type>.<bundle>.<field></info>.',
  ];

  public function __invoke(ConsoleDefinitionsEvent $event): void {
    $application = $event->getApplication();
    if (!$application->has(Activation::COMMAND)) {
      return;
    }
    $command = $application->get(Activation::COMMAND);
    foreach (self::OPTIONS as $name => $description) {
      // Fires again after bootstrap; add each option once.
      if (!$command->getDefinition()->hasOption($name)) {
        $command->addOption($name, '', InputOption::VALUE_REQUIRED, $description);
      }
    }
  }

}
