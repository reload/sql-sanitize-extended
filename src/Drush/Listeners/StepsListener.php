<?php

declare(strict_types=1);

namespace Drupal\sql_sanitize_extended\Drush\Listeners;

use Drupal\sql_sanitize_extended\Activation;
use Drupal\sql_sanitize_extended\Configuration;
use Drupal\sql_sanitize_extended\SanitizerFactory;
use Drush\Commands\AutowireTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Runs the steps after core's sanitize plugins, before the sites' listeners.
 */
#[AsEventListener(priority: self::PRIORITY)]
final class StepsListener {

  use AutowireTrait;

  public const PRIORITY = 100;

  public function __construct(
    private readonly SanitizerFactory $factory,
    private readonly LoggerInterface $logger,
  ) {
  }

  public function __invoke(ConsoleTerminateEvent $event): void {
    $options = $event->getInput()->getOptions();
    if (!Activation::shouldRun($event->getCommand()?->getName(), $event->getExitCode(), $event->getInterruptingSignal(), $options)) {
      return;
    }
    foreach ($this->factory->stages(Configuration::fromOptions($options), $this->logger) as $stage) {
      $stage->run();
    }
  }

}
