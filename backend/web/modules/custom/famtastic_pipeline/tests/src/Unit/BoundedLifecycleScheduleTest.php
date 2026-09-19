<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;
use Drupal\famtastic_pipeline\Service\BoundedLifecycleSchedule as Schedule;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
final class BoundedLifecycleScheduleTest extends TestCase {
  private function old(): string { return "MAILTO=\"\"\n\n" . Schedule::OLD_MARKER . "\n*/5 * * * * cd " . Schedule::ROOT . ' && ' . Schedule::ROOT . "/vendor/bin/drush famtastic:lifecycle-run --limit=50 >/dev/null 2>&1\n# unrelated\n0 1 * * * /usr/bin/true\n"; }
  public function testPreservesUnrelatedEntriesAndUsesExplicitPhpObserveOnly(): void {
    $new = Schedule::transform($this->old());
    self::assertStringContainsString('/usr/local/bin/php', $new);
    self::assertStringContainsString('vendor/bin/drush.php famtastic:automation-tick >>', $new);
    self::assertStringNotContainsString('--dispatch', $new);
    self::assertStringContainsString("# unrelated\n0 1 * * * /usr/bin/true", $new);
    self::assertSame($new, Schedule::transform($new));
    self::assertStringContainsString('--dispatch', Schedule::transform($new, TRUE));
  }
  #[DataProvider('badSchedules')]
  public function testRefusesAmbiguousOrChangedSchedules(string $case): void {
    $old = $this->old();
    $input = match ($case) { 'duplicate' => $old . $old, 'missing' => '', 'changed' => str_replace('--limit=50', '--limit=1', $old), 'unowned' => $old . "* * * * * drush famtastic:jobs-run\n", 'unknown_marker' => $old . "# FAMTASTIC_BOUNDED_WORKER_CRON_V2\n" };
    $this->expectException(\RuntimeException::class); Schedule::transform($input);
  }
  public static function badSchedules(): iterable { foreach (['duplicate', 'missing', 'changed', 'unowned', 'unknown_marker'] as $x) yield $x => [$x]; }
}
