<?php
declare(strict_types=1);

/** Read-only gate: a frontend promotion must not invalidate installed senders. */
final class AcquisitionFrontendReleaseGuard {
  public static function check(string $cron, string $home, string $candidate, callable $read, string $date): string {
    if (!preg_match('#^/home/[a-zA-Z0-9_-]+$#D', $home) || !preg_match('/^[a-f0-9]{40}$/D', $candidate)) throw new RuntimeException('release_guard_arguments_invalid');
    if ($date === '2026-10-07' && str_contains($cron, '# FAMTASTIC_OCT7_CATCHUP_20261007')) throw new RuntimeException('release_guard_date_only_catchup_installed');
    $commands = [];$markers = 0;
    foreach (explode("\n", $cron) as $line) {
      if (str_contains($line, 'FAMTASTIC_ACQUISITION_WINDOWS')) {
        if ($line !== '# FAMTASTIC_ACQUISITION_WINDOWS_V1') throw new RuntimeException('release_guard_clock_altered');
        $markers++;
      }
      if (!str_starts_with(ltrim($line), '#') && preg_match('/famtastic:acquisition-window|acquisition-exact-operator\.php|acquisition-window-contact\.php/', $line)) $commands[] = $line;
    }
    if ($markers === 0 && count($commands) === 0) return 'no_normal_clock_installed';
    if ($markers !== 1 || count($commands) !== 1) throw new RuntimeException('release_guard_clock_ambiguous');
    $dir = $home.'/private_files/acquisition-199';
    $receipt = json_decode($read($dir.'/industry-clock-install-receipt.json'), true, 64, JSON_THROW_ON_ERROR);
    $input = $receipt['routine_input'] ?? '';
    if (!is_string($input) || !preg_match('#^'.preg_quote($dir,'#').'/[a-zA-Z0-9_-]{1,80}\.json$#D', $input)) throw new RuntimeException('release_guard_input_invalid');
    $expected = '* * * * * cd '.$home.'/public_html && /usr/local/bin/php '.$home.'/public_html/vendor/bin/drush.php famtastic:acquisition-window --input='.$input.' >'.$home.'/deploy/famtastic-designs/acquisition-window-last-run.log 2>&1';
    if ($commands[0] !== $expected || !str_contains($cron, '# FAMTASTIC_ACQUISITION_WINDOWS_V1'."\n".$expected)) throw new RuntimeException('release_guard_clock_altered');
    $signed = json_decode($read($input), true, 64, JSON_THROW_ON_ERROR);
    $config = $signed['config'] ?? null;$signature = $signed['signature'] ?? '';
    $key = trim($read($dir.'/owner-signing.key'));
    if (!is_array($config) || !is_string($signature) || $key === '' || !hash_equals(hash_hmac('sha256', json_encode($config,JSON_THROW_ON_ERROR), $key), $signature)) throw new RuntimeException('release_guard_signature_invalid');
    if (($config['binding_config']['frontend_sha'] ?? '') !== $candidate) throw new RuntimeException('release_guard_installed_clock_requires_validated_rebind');
    return 'installed_clock_matches_candidate';
  }
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
  try {
    $lines=[];$status=0;exec('crontab -l 2>/dev/null',$lines,$status);
    if ($status !== 0) throw new RuntimeException('release_guard_crontab_unreadable');
    $read = static function(string $path): string { $s=@file_get_contents($path);if($s===false)throw new RuntimeException('release_guard_private_record_unreadable');return $s; };
    $date=(new DateTimeImmutable('now',new DateTimeZone('America/New_York')))->format('Y-m-d');
    echo AcquisitionFrontendReleaseGuard::check(implode("\n",$lines)."\n", $argv[1]??'', $argv[2]??'', $read, $date)."\n";
  } catch (Throwable $e) {
    fwrite(STDERR,"Frontend promotion blocked by the acquisition release guard. Pause the owned sender clocks, deploy and validate the actual destinations, then sign and reinstall the approved schedule for that exact release. No automatic rebind or send occurred.\n");
    exit(1);
  }
}
