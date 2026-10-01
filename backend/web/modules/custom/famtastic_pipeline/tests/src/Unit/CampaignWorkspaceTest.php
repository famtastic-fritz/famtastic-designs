<?php

declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\famtastic_pipeline\Service\CampaignWorkspace;
use Drupal\famtastic_pipeline\Service\PostizChannelsService;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;

require_once dirname(__DIR__, 3) . '/src/Service/CampaignWorkspace.php';
require_once dirname(__DIR__, 3) . '/src/Service/PostizChannelsService.php';
require_once dirname(__DIR__, 3) . '/src/Utility/CampaignFileLocator.php';

#[\PHPUnit\Framework\Attributes\Group('famtastic_pipeline')]
final class CampaignWorkspaceTest extends UnitTestCase {
  private Connection $db;
  private CampaignWorkspace $workspace;
  protected function setUp(): void {
    parent::setUp();
    $options = ['database' => ':memory:', 'prefix' => '', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite', 'driver' => 'sqlite'];
    $this->db = new Connection(Connection::open($options), $options);
    $this->db->query('CREATE TABLE famtastic_campaign (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_key TEXT UNIQUE, name TEXT, status TEXT, channel TEXT, source_filter TEXT, created INTEGER, changed INTEGER, plan_json TEXT, revision INTEGER DEFAULT 0)');
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1800000000);
    $this->workspace = new CampaignWorkspace($this->db, $time);
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
    $container->setParameter('app.root', dirname(__DIR__, 6));
    \Drupal::setContainer($container);
  }
  public function testDraftEditDuplicateArchiveRestoreAndConflictIsolation(): void {
    $first = ['campaign_key' => 'first-plan', 'name' => 'First', 'plan' => ['channels' => ['email'], 'start_date' => '2026-10-01', 'end_date' => '2026-10-03', 'content_items' => [['date' => '2026-10-02', 'channel' => 'email', 'copy' => '<b>Draft</b>']]]];
    $this->workspace->save($first);
    $second = $first; $second['campaign_key'] = 'second-plan'; $second['plan']['end_date'] = '2026-11-30'; $second['plan']['channels'] = ['instagram'];
    $this->workspace->save($second);
    $this->assertSame('draft', $this->workspace->get('first-plan')['status']);
    $this->assertCount(1, $this->workspace->items('first-plan'));
    $this->workspace->save($first + [], 1);
    $this->assertSame(2, (int) $this->workspace->get('first-plan')['revision']);
    $this->workspace->changeStatus('first-plan', 2, TRUE);
    $this->assertSame('archived', $this->workspace->get('first-plan')['status']);
    $this->workspace->changeStatus('first-plan', 3, FALSE);
    $this->assertSame('draft', $this->workspace->get('first-plan')['status']);
    $this->assertSame('2026-11-30', $this->workspace->get('second-plan')['plan']['end_date']);
    $this->expectException(\RuntimeException::class);
    $this->workspace->save($first, 1);
  }
  public function testSharedValidationRejectsUnsafeKeysAndInvalidDates(): void {
    $this->assertFalse(CampaignWorkspace::validKey('../escape'));
    $this->assertFalse(CampaignWorkspace::validKey('two--dashes'));
    $this->assertFalse(CampaignWorkspace::validKey('under_score'));
    $errors = CampaignWorkspace::validate(['campaign_key' => 'ok', 'name' => 'Good', 'plan' => ['start_date' => '2026-10-10', 'end_date' => '2026-10-01', 'channels' => ['invented']]]);
    $this->assertArrayHasKey('end_date', $errors);
    $this->assertArrayHasKey('channels', $errors);
  }
  public function testPostizHostAndFullApiPathDoNotDoubleAppend(): void {
    $this->assertSame('https://example.test/api/public/v1', PostizChannelsService::apiBaseUrl('https://example.test'));
    $this->assertSame('https://example.test/api/public/v1', PostizChannelsService::apiBaseUrl('https://example.test/api/public/v1/'));
  }
  public function testScheduleDiscoveryUnionsRootsAndKeepsCorruptStateUnknown(): void {
    $base = sys_get_temp_dir() . '/campaign-locator-' . bin2hex(random_bytes(5));
    mkdir($base . '/backend/web', 0700, TRUE);
    mkdir($base . '/marketing/campaigns/first', 0700, TRUE);
    mkdir($base . '/backend/marketing/campaigns/second', 0700, TRUE);
    file_put_contents($base . '/marketing/campaigns/first/posting-schedule.json', '{"drops":[]}');
    file_put_contents($base . '/backend/marketing/campaigns/second/posting-schedule.json', 'corrupt');
    $container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
    $container->setParameter('app.root', $base . '/backend/web');
    \Drupal::setContainer($container);
    try {
      $this->assertSame(['first', 'second'], \Drupal\famtastic_pipeline\Utility\CampaignFileLocator::listCampaignSlugs());
      $this->assertNull(\Drupal\famtastic_pipeline\Utility\CampaignFileLocator::readJson('second', 'posting-schedule.json'));
      $this->assertNull(\Drupal\famtastic_pipeline\Utility\CampaignFileLocator::readJson('../first', 'posting-schedule.json'));
    }
    finally {
      unlink($base . '/marketing/campaigns/first/posting-schedule.json');
      unlink($base . '/backend/marketing/campaigns/second/posting-schedule.json');
      foreach (['/marketing/campaigns/first', '/marketing/campaigns', '/marketing', '/backend/marketing/campaigns/second', '/backend/marketing/campaigns', '/backend/marketing', '/backend/web', '/backend', ''] as $dir) rmdir($base . $dir);
    }
  }

  public function testPostiz404NeverReportsConnectedOrLeaksException(): void {
    new \Drupal\Core\Site\Settings(['famtastic_postiz_base_url' => 'https://example.test/api/public/v1', 'famtastic_postiz_api_key' => 'test-only']);
    $http = $this->createMock(\GuzzleHttp\ClientInterface::class);
    $http->expects($this->once())->method('request')->with('GET', 'https://example.test/api/public/v1/integrations', $this->callback(static fn(array $options): bool => $options['allow_redirects'] === FALSE))->willReturn(new \GuzzleHttp\Psr7\Response(404));
    $time = $this->createMock(TimeInterface::class);
    $result = (new PostizChannelsService($http, $time))->channels();
    $this->assertFalse($result['reachable']);
    $this->assertSame([], $result['platforms']);
    $this->assertStringContainsString('HTTP 404', $result['error']);
  }
}
