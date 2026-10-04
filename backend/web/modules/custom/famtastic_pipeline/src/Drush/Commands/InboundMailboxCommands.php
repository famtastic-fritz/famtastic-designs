<?php

declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Drush\Commands;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\famtastic_pipeline\Service\InboundMailboxService;

/** Dedicated ingress only: no general Drupal cron or outbound dispatch. */
final class InboundMailboxCommands extends DrushCommands {
  private function service(): InboundMailboxService {
    $home = (string) getenv('HOME');
    if ($home === '' || PHP_SAPI !== 'cli') throw new \RuntimeException('mail_cli_home_required');
    return new InboundMailboxService(\Drupal::state(), \Drupal::lock(), \Drupal::database(),
      \Drupal::service('famtastic_pipeline.lifecycle_operations'), $home . '/mail/famtasticdesigns.com');
  }
  #[CLI\Command(name: 'famtastic:mail-activate')]
  #[CLI\Option(name: 'confirm', description: 'Repeat preserve-existing-mail to snapshot historical mail without importing it.')]
  public function activate(array $options = ['confirm' => '']): int {
    if ($options['confirm'] !== 'preserve-existing-mail') throw new \InvalidArgumentException('mail_activation_confirmation_required');
    $this->io()->writeln(json_encode($this->service()->activate(), JSON_THROW_ON_ERROR));
    return self::EXIT_SUCCESS;
  }
  #[CLI\Command(name: 'famtastic:mail-tick')]
  public function tick(): int {
    $report = $this->service()->tick();
    $this->io()->writeln(json_encode($report, JSON_THROW_ON_ERROR));
    return $report['failed'] ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
  }
  #[CLI\Command(name: 'famtastic:mail-health')]
  public function health(): int {
    $report = $this->service()->health();
    $this->io()->writeln(json_encode($report, JSON_THROW_ON_ERROR));
    return !$report['enabled'] || $report['clock_status'] !== 'recent' || $report['retry_files'] || $report['messages_missing_draft'] ? self::EXIT_FAILURE : self::EXIT_SUCCESS;
  }
  #[CLI\Command(name: 'famtastic:mail-schedule')]
  #[CLI\Option(name: 'install', description: 'Install the one exact ingress clock after activation.')]
  #[CLI\Option(name: 'confirm', description: 'Repeat FAMTASTIC_INBOUND_MAIL_CRON_V1.')]
  public function schedule(array $options = ['install' => FALSE, 'confirm' => '']): int {
    $home = (string) getenv('HOME');
    $class = \Drupal\famtastic_pipeline\Service\InboundMailboxSchedule::class;
    $read = static function (): string {
      $lines = []; $code = 0;
      exec('crontab -l 2>/dev/null', $lines, $code);
      if ($code !== 0) throw new \RuntimeException('mail_crontab_unreadable');
      return implode("\n", $lines) . "\n";
    };
    $before = $read();
    $present = $class::inspect($before, $home);
    if (!$this->service()->health()['enabled']) throw new \RuntimeException('mail_activation_required');
    if (!$options['install']) {
      $this->io()->writeln(json_encode(['clock_installed' => $present, 'broad_dispatch' => 'not_invoked'], JSON_THROW_ON_ERROR));
      return $present ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
    }
    if ($options['confirm'] !== 'FAMTASTIC_INBOUND_MAIL_CRON_V1') throw new \RuntimeException('mail_clock_confirmation_required');
    if ($present) return self::EXIT_SUCCESS;
    $next = $class::install($before, $home);
    $folder = $home . '/deploy/famtastic-designs/cron-backups';
    if (!is_dir($folder) && !mkdir($folder, 0700, TRUE)) throw new \RuntimeException('mail_backup_unavailable');
    $file = $folder . '/inbound-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(4));
    if (file_put_contents($file . '.before', $before) === FALSE) throw new \RuntimeException('mail_backup_failed');
    chmod($file . '.before', 0600);
    if (file_put_contents($file . '.next', $next) === FALSE) throw new \RuntimeException('mail_clock_staging_failed');
    chmod($file . '.next', 0600);
    if (!hash_equals($before, $read())) throw new \RuntimeException('mail_crontab_changed');
    $code = 0; $out = [];
    exec('crontab ' . escapeshellarg($file . '.next'), $out, $code);
    if ($code !== 0 || !hash_equals($next, $read())) throw new \RuntimeException('mail_clock_install_failed');
    $this->io()->writeln(json_encode(['clock_installed' => TRUE, 'backup' => $file . '.before'], JSON_THROW_ON_ERROR));
    return self::EXIT_SUCCESS;
  }

}
