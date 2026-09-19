<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\Core\Site\Settings;

/** Observe-only schedule and explicit exact-job enrollment. Never a queue drain. */
final class BoundedWorkerCommands extends DrushCommands {
  #[CLI\Command(name: 'famtastic:automation-tick')]
  #[CLI\Option(name: 'dispatch', description: 'Run at most one explicitly enrolled static handoff; default is observe-only.')]
  public function tick(array $options = ['dispatch' => FALSE]): int {
    if (PHP_SAPI !== 'cli') return self::EXIT_FAILURE;
    $coordinator = \Drupal::service('famtastic_pipeline.worker_coordinator');
    $report = $coordinator->health();
    if (empty($options['dispatch'])) {
      $this->io()->writeln(json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
      return self::EXIT_SUCCESS;
    }
    if (!Settings::get('famtastic_bounded_dispatch_enabled', FALSE) || \Drupal::service('famtastic_pipeline.pilot_exact_dispatch_lock')->isActive()) {
      $this->logger()->error('Bounded dispatch not enabled or pilot lock active; no claim made.');
      return self::EXIT_FAILURE;
    }
    $claim = $coordinator->claim('drupal-static-dispatch');
    if (!$claim) {
      $this->io()->writeln(json_encode($report + ['dispatch_status' => 'no_enrolled_work'], JSON_THROW_ON_ERROR));
      return self::EXIT_SUCCESS;
    }
    try {
      $packet = json_decode($claim['payload_wire'], TRUE, flags: JSON_THROW_ON_ERROR)['packet'];
      \Drupal::service('famtastic_pipeline.customer_portal')->assertCurrentSelectedStagingPacket($packet);
      $result = \Drupal::service('famtastic_pipeline.site_studio_staging_client')->dispatch($packet);
      $receipt = $coordinator->finish($claim['job_id'], 'drupal-static-dispatch', $claim['lease_token'], $result);
      $this->io()->writeln(json_encode($receipt + ['job_id' => $claim['job_id'], 'staging_ready' => FALSE], JSON_THROW_ON_ERROR));
      return self::EXIT_SUCCESS;
    } catch (\Throwable) {
      try { $coordinator->fail($claim['job_id'], 'drupal-static-dispatch', $claim['lease_token']); } catch (\Throwable) {}
      $this->logger()->error('Bounded handoff unconfirmed; exact packet retained for reconciliation/retry.');
      return self::EXIT_FAILURE;
    }
  }

  #[CLI\Command(name: 'famtastic:automation-health')]
  public function health(): int {
    if (PHP_SAPI !== 'cli') return self::EXIT_FAILURE;
    $this->io()->writeln(json_encode(\Drupal::service('famtastic_pipeline.worker_coordinator')->health(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    return self::EXIT_SUCCESS;
  }

  #[CLI\Command(name: 'famtastic:worker-enroll')]
  #[CLI\Argument(name: 'jobId', description: 'One exact fresh selected-staging job id.')]
  #[CLI\Option(name: 'key', description: 'Exact immutable job key.')]
  #[CLI\Option(name: 'sha256', description: 'SHA256 of the stored payload bytes.')]
  #[CLI\Option(name: 'reserve-cents', description: 'Conservative attempt cost hold, 25 to 250 cents.')]
  #[CLI\Option(name: 'confirm', description: 'Must exactly repeat the job key.')]
  public function enroll(int $jobId, array $options = ['key' => '', 'sha256' => '', 'reserve-cents' => 0, 'confirm' => '']): int {
    if (!$options['key'] || !hash_equals($options['key'], $options['confirm'])) throw new \InvalidArgumentException('Exact job-key confirmation required.');
    if (\Drupal::service('famtastic_pipeline.pilot_exact_dispatch_lock')->isActive()) throw new \RuntimeException('Pilot lock prevents enrollment.');
    $result = \Drupal::service('famtastic_pipeline.worker_coordinator')->enroll($jobId, $options['key'], $options['sha256'], (int) $options['reserve-cents']);
    $this->io()->writeln(json_encode($result, JSON_THROW_ON_ERROR));
    return self::EXIT_SUCCESS;
  }
}
