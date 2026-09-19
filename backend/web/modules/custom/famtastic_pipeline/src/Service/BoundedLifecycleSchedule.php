<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Pure exact-marker crontab transform; unknown work is never guessed away. */
final class BoundedLifecycleSchedule {
  public const ROOT = '/home/xrdj7j99xhzt/public_html';
  public const LOG = '/home/xrdj7j99xhzt/deploy/famtastic-designs/bounded-worker.log';
  public const OLD_MARKER = '# FAMTASTIC_LIFECYCLE_CRON_V1';
  public const MARKER = '# FAMTASTIC_BOUNDED_WORKER_CRON_V1';
  public static function line(bool $dispatch = FALSE): string {
    return '*/5 * * * * cd ' . self::ROOT . ' && /usr/local/bin/php ' . self::ROOT
      . '/vendor/bin/drush.php famtastic:automation-tick' . ($dispatch ? ' --dispatch' : '') . ' >>' . self::LOG . ' 2>&1';
  }
  public static function transform(string $before, bool $dispatch = FALSE): string {
    $lines = explode("\n", $before);
    $matches = [];
    $old = '*/5 * * * * cd ' . self::ROOT . ' && ' . self::ROOT . '/vendor/bin/drush famtastic:lifecycle-run --limit=50 >/dev/null 2>&1';
    foreach ($lines as $i => $line) {
      if (in_array($line, [self::OLD_MARKER, self::MARKER], TRUE)) $matches[] = $i;
      elseif (preg_match('/FAMTASTIC_(LIFECYCLE|BOUNDED_WORKER)_CRON/', $line)) throw new \RuntimeException('Unknown lifecycle marker requires reconciliation.');
      if (trim($line) !== '' && !str_starts_with(ltrim($line), '#')
        && preg_match('/famtastic:(?:lifecycle-run|jobs-run|automation-tick)|\bdrush(?:\.php)?\s+(?:cron|fjr|flr|ev|php:eval|php:script)\b|automation[_:-]?worker/i', $line)
        && !in_array($lines[$i - 1] ?? '', [self::OLD_MARKER, self::MARKER], TRUE)) throw new \RuntimeException('Unowned automation schedule requires reconciliation.');
    }
    if (count($matches) !== 1) throw new \RuntimeException('Exactly one owned lifecycle marker is required.');
    $i = $matches[0];
    if (!in_array($lines[$i + 1] ?? '', [$old, self::line(FALSE), self::line(TRUE)], TRUE)) throw new \RuntimeException('Owned marker command changed; refusing overwrite.');
    $lines[$i] = self::MARKER;
    $lines[$i + 1] = self::line($dispatch);
    return implode("\n", $lines);
  }
}
