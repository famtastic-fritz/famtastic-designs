<?php

declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Pure marker-owned clock contract. Never edits unrelated scheduler lines. */
final class InboundMailboxSchedule {
  public const MARKER = '# FAMTASTIC_INBOUND_MAIL_CRON_V1';
  public static function line(string $home): string {
    if (!preg_match('#^/home/[a-zA-Z0-9_-]+$#D', $home)) throw new \RuntimeException('mail_home_invalid');
    return '*/5 * * * * cd ' . $home . '/public_html && /usr/local/bin/php ' . $home . '/public_html/vendor/bin/drush.php famtastic:mail-tick >' . $home . '/deploy/famtastic-designs/inbound-mail-last-run.log 2>&1';
  }
  public static function inspect(string $cron, string $home): bool {
    $lines = explode("\n", rtrim($cron, "\n")); $markers = 0; $commands = 0;
    foreach ($lines as $i => $line) {
      if (str_contains($line, 'FAMTASTIC_INBOUND_MAIL_CRON')) {
        if ($line !== self::MARKER || ($lines[$i + 1] ?? '') !== self::line($home)) throw new \RuntimeException('mail_clock_altered');
        $markers++;
      }
      if (!str_starts_with(ltrim($line), '#') && preg_match('/famtastic:mail-tick|process-support-maildir|inbound-mail-pipe/', $line)) {
        if ($line !== self::line($home)) throw new \RuntimeException('mail_clock_unowned');
        $commands++;
      }
    }
    if ($markers > 1 || $commands > 1 || $markers !== $commands) throw new \RuntimeException('mail_clock_ambiguous');
    return $markers === 1;
  }
  public static function install(string $cron, string $home): string {
    if (self::inspect($cron, $home)) return $cron;
    return rtrim($cron, "\n") . "\n\n" . self::MARKER . "\n" . self::line($home) . "\n";
  }
  public static function remove(string $cron, string $home): string {
    if (!self::inspect($cron, $home)) return $cron;
    return str_replace(self::MARKER . "\n" . self::line($home) . "\n", '', $cron);
  }
}
