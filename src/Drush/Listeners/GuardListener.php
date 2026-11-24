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
 * Checks the final database after every other listener, and throws if a user
 * id dangles, which gives a non-zero exit.
 */
#[AsEventListener(priority: self::PRIORITY)]
final class GuardListener {

  use AutowireTrait;

  public const PRIORITY = -1000;

  public function __construct(
    private readonly SanitizerFactory $factory,
    private readonly LoggerInterface $logger,
  ) {
  }

  public function __invoke(ConsoleTerminateEvent $event): void {
    $options = $event->getInput()->getOptions();
    if (!Activation::shouldRun($event->getCommand()?->getName(), $event->getExitCode(), $options)) {
      return;
    }
    $this->factory->guard(Configuration::fromOptions($options), $this->logger)->run();
  }

}
