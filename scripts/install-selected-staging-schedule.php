<?php
/** Run with the installed production Drush php:script; default is read-only. */
declare(strict_types=1);

$apply = getenv('FAMTASTIC_INSTALL_SELECTED_SCHEDULE') === 'apply';
$root = dirname(\Drupal::root());
if ($root !== '/home/xrdj7j99xhzt/public_html' || PHP_SAPI !== 'cli') {
  throw new \RuntimeException('Selected schedule requires the exact production Drupal CLI root.');
}
$endpoint = (string) \Drupal::config('famtastic_pipeline.settings')->get('site_studio_staging_url');
if ($endpoint !== 'http://127.0.0.1:3417/api/pipeline/staging/accept'
  || !\Drupal\Core\Site\Settings::get('site_studio_staging_dispatch_secret')
  || !\Drupal\Core\Site\Settings::get('site_studio_callback_secret')
  || \Drupal::service('famtastic_pipeline.pilot_exact_dispatch_lock')->isActive()) {
  throw new \RuntimeException('Selected dispatch endpoint, credentials and policy must be configured first.');
}
$php = '/usr/local/bin/php';
if (!is_executable($php) || !is_file($root . '/vendor/bin/drush.php')) {
  throw new \RuntimeException('Explicit production CLI PHP/Drush is missing.');
}
$marker = '# FAMTASTIC_SELECTED_STAGING_CRON_V1';
$log = '/home/xrdj7j99xhzt/deploy/famtastic-designs/selected-staging-worker.log';
$line = "*/2 * * * * cd $root && $php $root/vendor/bin/drush.php famtastic:jobs-run --type=site_studio_staging_prepare --limit=10 >>$log 2>&1";
$read = static function (): string {
  $rows = []; $exit = 0; exec('crontab -l 2>&1', $rows, $exit);
  if ($exit !== 0) throw new \RuntimeException('Cannot read existing crontab; nothing changed.');
  return implode("\n", $rows) . "\n";
};
$before = $read();
$lines = explode("\n", $before);
$markers = array_keys($lines, $marker, TRUE);
if (count($markers) > 1) throw new \RuntimeException('Duplicate selected scheduler markers require reconciliation.');
if ($markers) {
  if (($lines[$markers[0] + 1] ?? '') !== $line) throw new \RuntimeException('Existing selected scheduler differs; preserve and reconcile it.');
  print "Selected-staging-only scheduler already matches.\n";
  return;
}
foreach ($lines as $existing) {
  if (!str_starts_with(ltrim($existing), '#') && str_contains($existing, '--type=site_studio_staging_prepare')) {
    throw new \RuntimeException('An unowned selected scheduler already exists.');
  }
}
if (!$apply) {
  print "Preflight passed. Apply adds only the selected-staging type schedule. Broad lifecycle/mail schedule and failed legacy jobs remain untouched.\n";
  print $marker . "\n" . $line . "\n";
  return;
}
$backupDir = '/home/xrdj7j99xhzt/deploy/famtastic-designs/cron-backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0700, TRUE)) throw new \RuntimeException('Cannot create private backup directory.');
$backup = $backupDir . '/before-selected-staging-' . gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(4)) . '.txt';
$fd = fopen($backup, 'x');
if (!$fd) throw new \RuntimeException('Cannot reserve crontab backup.');
chmod($backup, 0600); fwrite($fd, $before); fflush($fd); fclose($fd);
if ($read() !== $before) throw new \RuntimeException('Crontab changed; backup retained, nothing installed.');
$stage = $backup . '.next';
file_put_contents($stage, rtrim($before, "\n") . "\n\n$marker\n$line\n", LOCK_EX); chmod($stage, 0600);
$output = []; $exit = 0; exec('crontab ' . escapeshellarg($stage) . ' 2>&1', $output, $exit);
if ($exit !== 0) throw new \RuntimeException('Crontab install failed; exact backup and proposal retained.');
if ($read() !== file_get_contents($stage)) throw new \RuntimeException('Installed crontab differs; preserve and reconcile, do not restore a stale whole crontab.');
print json_encode(['status' => 'installed', 'scope' => 'site_studio_staging_prepare', 'backup' => $backup, 'log' => $log], JSON_UNESCAPED_SLASHES) . "\n";
