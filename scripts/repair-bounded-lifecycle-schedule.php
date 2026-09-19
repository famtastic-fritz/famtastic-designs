<?php
/** Run via the installed production Drush php:script. No apply by default. */
declare(strict_types=1);
use Drupal\famtastic_pipeline\Service\BoundedLifecycleSchedule as Schedule;
use Drupal\Core\Site\Settings;

if (PHP_SAPI !== 'cli' || dirname(\Drupal::root()) !== Schedule::ROOT || !is_executable('/usr/local/bin/php')) throw new RuntimeException('Exact production CLI root required.');
$dispatch = getenv('FAMTASTIC_BOUNDED_MODE') === 'dispatch';
if ($dispatch && (!Settings::get('famtastic_bounded_dispatch_enabled', FALSE)
  || \Drupal::service('famtastic_pipeline.pilot_exact_dispatch_lock')->isActive())) throw new RuntimeException('Dispatch activation has not passed its separate gates.');
// Exercise only read-only service/DB/schema. Never call lifecycle, mail or job-run.
$health = \Drupal::service('famtastic_pipeline.worker_coordinator')->health();
$read = static function (): string {
  $p = proc_open(['crontab', '-l'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
  if (!is_resource($p)) throw new RuntimeException('Cannot inspect crontab.');
  $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
  fclose($pipes[1]); fclose($pipes[2]);
  if (proc_close($p) !== 0) throw new RuntimeException('Cannot read current crontab; nothing changed.');
  return $out;
};
$before = $read();
$after = Schedule::transform($before, $dispatch);
$hash = hash('sha256', $before);
if (getenv('FAMTASTIC_REPAIR_LIFECYCLE') !== 'apply') {
  print json_encode(['status' => 'preflight_only', 'before_sha256' => $hash, 'mode' => $dispatch ? 'dispatch_one_enrolled_static_job' : 'observe_only', 'command' => Schedule::line($dispatch), 'health' => $health], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
  return;
}
if (!hash_equals($hash, (string) getenv('FAMTASTIC_CRONTAB_CONFIRM_SHA256'))) throw new RuntimeException('Apply must confirm the exact preflight crontab SHA256.');
if ($before === $after) { print "Already matches; no crontab change.\n"; return; }
$dir = '/home/xrdj7j99xhzt/deploy/famtastic-designs/cron-backups';
if (!is_dir($dir) && !mkdir($dir, 0700, TRUE)) throw new RuntimeException('Private backup directory unavailable.');
$backup = $dir . '/bounded-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(5)) . '.txt';
$f = fopen($backup, 'x');
if (!$f) throw new RuntimeException('Cannot reserve backup.');
if (!chmod($backup, 0600) || fwrite($f, $before) !== strlen($before) || !fflush($f)) {
  fclose($f); throw new RuntimeException('Private backup incomplete; no crontab change.');
}
fclose($f);
if (hash_file('sha256', $backup) !== $hash) throw new RuntimeException('Backup verification failed; no crontab change.');
if ($read() !== $before) throw new RuntimeException('Crontab changed; retained backup, no install.');
$process = proc_open(['crontab', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($process)) throw new RuntimeException('Cannot start crontab installer.');
fwrite($pipes[0], $after); fclose($pipes[0]); stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
if (proc_close($process) !== 0 || $read() !== $after) throw new RuntimeException('Crontab install unconfirmed; reconcile without restoring a stale full backup.');
print json_encode(['status' => 'installed', 'backup' => $backup, 'before_sha256' => $hash, 'after_sha256' => hash('sha256', $after), 'dispatch_enabled' => $dispatch, 'log' => Schedule::LOG], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
