<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Drush\Listeners;

use Drupal\sql_sanitize_extended\Activation;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\SanitizerFactory;
use Drush\Commands\AutowireTrait;
use Drush\Event\SanitizeConfirmsEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Checks the options and lists the steps before the prompt.
 *
 * An invalid option throws here, before the prompt and before core changes
 * anything.
 */
#[AsEventListener]
final class ConfirmListener {

  use AutowireTrait;

  public function __construct(
    private readonly SanitizerFactory $factory,
    private readonly LoggerInterface $logger,
  ) {
  }

  public function __invoke(SanitizeConfirmsEvent $event): void {
    $options = $event->getInput()->getOptions();
    if (!Activation::isEnabled($options)) {
      return;
    }
    $stages = $this->factory->stages(Configuration::fromOptions($options), $this->logger);
    $errors = [];
    foreach ($stages as $stage) {
      ['errors' => $stageErrors, 'warnings' => $warnings] = $stage->validate();
      foreach ($warnings as $warning) {
        $this->logger->warning($warning);
      }
      $errors = [...$errors, ...$stageErrors];
    }
    if ($errors !== []) {
      throw new \RuntimeException("Invalid sql:sanitize options:\n" . implode("\n", $errors));
    }
    foreach ($stages as $stage) {
      $confirmation = $stage->confirmation();
      if ($confirmation !== NULL) {
        $event->addMessage($confirmation);
      }
    }
  }

}
