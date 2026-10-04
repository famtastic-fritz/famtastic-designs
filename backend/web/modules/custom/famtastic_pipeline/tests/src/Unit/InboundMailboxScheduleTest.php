<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;
use Drupal\famtastic_pipeline\Service\InboundMailboxSchedule as Schedule;
use PHPUnit\Framework\TestCase;
final class InboundMailboxScheduleTest extends TestCase {
  public function testPreservesExistingClockAndRemovalIsSurgical(): void {
    $old = "MAILTO=\"\"\n# other\n*/5 * * * * drush famtastic:automation-tick --dispatch\n";
    $new = Schedule::install($old, '/home/test');
    self::assertStringStartsWith($old, $new);
    self::assertTrue(Schedule::inspect($new, '/home/test'));
    self::assertSame($new, Schedule::install($new, '/home/test'));
    self::assertSame(rtrim($old, "\n"), rtrim(Schedule::remove($new, '/home/test'), "\n"));
  }
  public function testRefusesUnownedLegacyImporter(): void {
    $this->expectException(\RuntimeException::class);
    Schedule::install('*/5 * * * * /path/process-support-maildir.sh', '/home/test');
  }
  public function testRefusesAlteredOrDuplicatedClock(): void {
    $new = Schedule::install('', '/home/test');
    $this->expectException(\RuntimeException::class);
    Schedule::inspect($new . $new, '/home/test');
  }
}
