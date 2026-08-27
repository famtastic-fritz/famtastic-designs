<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Durable kill-switch for broad lifecycle processing during an exact-ID pilot.
 *
 * cPanel executes scheduled Drush commands in a fresh environment, so an
 * environment variable used only by the deploy shell is not a safe runtime
 * boundary. The Drupal config value is therefore authoritative once a pilot
 * is enabled. An environment value of exactly "1" is an additive emergency
 * lock for a process that has not yet read configuration; it can never turn a
 * durable lock off.
 */
final class PilotExactDispatchLock {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Returns TRUE when general automation and general outbox dispatch are off.
   */
  public function isActive(): bool {
    return $this->durableConfigEnabled()
      || getenv('FAMTASTIC_PILOT_EXACT_DISPATCH_ONLY') === '1';
  }

  /**
   * Returns TRUE only for the durable Drupal configuration switch.
   */
  public function durableConfigEnabled(): bool {
    $value = $this->configFactory
      ->get('famtastic_pipeline.settings')
      ->get('pilot_exact_dispatch_only');
    if (is_bool($value)) {
      return $value;
    }
    if (is_int($value)) {
      return $value === 1;
    }
    if (is_string($value)) {
      return match (strtolower(trim($value))) {
        '', '0', 'false', 'no', 'off' => FALSE,
        '1', 'true', 'yes', 'on' => TRUE,
        // A corrupted/unexpected config value must not reopen broad mail or
        // automation during an owner-gated pilot.
        default => TRUE,
      };
    }
    // NULL on older installs means the default remains ordinary behavior;
    // all other unexpected types fail closed.
    return $value === NULL ? FALSE : TRUE;
  }

}
