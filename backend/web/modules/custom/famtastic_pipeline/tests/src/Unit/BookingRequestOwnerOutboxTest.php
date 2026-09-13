<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\Php as Uuid;
use Drupal\famtastic_pipeline\Service\BookingRequestService;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;

require_once dirname(__DIR__, 3) . '/src/Service/BookingRequestService.php';
require_once dirname(__DIR__, 3) . '/src/Service/CustomerPortalService.php';

/** Local SQLite integration of real request + outbox transaction behavior. */
#[\PHPUnit\Framework\Attributes\Group('famtastic_pipeline')]
final class BookingRequestOwnerOutboxTest extends UnitTestCase {
  private Connection $db;
  private BookingRequestService $service;
  private CustomerPortalService $portal;

  protected function setUp(): void {
    parent::setUp();
    $options = ['database' => ':memory:', 'prefix' => '', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite', 'driver' => 'sqlite'];
    $this->db = new Connection(Connection::open($options), $options);
    foreach ([
      'famtastic_booking_request' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, site_key TEXT, service_key TEXT, customer_name TEXT, email TEXT, email_hash TEXT, phone TEXT, requested_window TEXT, message TEXT, consent INTEGER, status TEXT, source TEXT, created INTEGER, changed INTEGER',
      'famtastic_booking_site_owner' => 'site_key TEXT PRIMARY KEY, customer_id INTEGER, organization_id INTEGER, website_request_id INTEGER, status TEXT',
      'famtastic_customer' => 'id INTEGER PRIMARY KEY, email TEXT, verified_at INTEGER',
      'famtastic_membership' => 'customer_id INTEGER, organization_id INTEGER, status TEXT',
      'famtastic_project_request' => 'id INTEGER PRIMARY KEY, customer_id INTEGER, organization_id INTEGER, status TEXT, business_name TEXT',
      'famtastic_notification_outbox' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, notification_key TEXT UNIQUE, category TEXT, recipient TEXT, subject TEXT, body TEXT, template_id TEXT, template_version INTEGER, status TEXT, attempts INTEGER, max_attempts INTEGER, available_at INTEGER, created INTEGER, changed INTEGER',
    ] as $table => $columns) $this->db->query('CREATE TABLE ' . $table . ' (' . $columns . ')');
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);
    $this->portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($this->portal, 'database'))->setValue($this->portal, $this->db);
    (new \ReflectionProperty($this->portal, 'time'))->setValue($this->portal, $time);
    $this->service = new BookingRequestService($this->db, $time, new Uuid(), $this->portal);
  }

  private function bind(): void {
    $this->db->insert('famtastic_customer')->fields(['id' => 11, 'email' => 'owner@example.test', 'verified_at' => 1])->execute();
    $this->db->insert('famtastic_membership')->fields(['customer_id' => 11, 'organization_id' => 7, 'status' => 'active'])->execute();
    $this->db->insert('famtastic_project_request')->fields(['id' => 12, 'customer_id' => 11, 'organization_id' => 7, 'status' => 'converted', 'business_name' => 'Local fixture'])->execute();
    $this->db->insert('famtastic_booking_site_owner')->fields(['site_key' => 'site-fixture', 'customer_id' => 11, 'organization_id' => 7, 'website_request_id' => 12, 'status' => 'active'])->execute();
  }

  private function capture(string $site = 'site-fixture'): array {
    return $this->service->create($site, ['name' => 'Visitor', 'email' => 'visitor@example.test', 'requested_window' => 'Friday', 'message' => 'Private visitor text', 'consent' => TRUE], 'local-unit-fixture');
  }

  private function rowCount(string $table): int {
    return (int) $this->db->select($table, 't')->countQuery()->execute()->fetchField();
  }

  public function testLegacyUnboundCaptureStillWorksWithoutNotification(): void {
    $this->assertSame('received', $this->capture('noise-cuts')['status']);
    $this->assertSame(1, $this->rowCount('famtastic_booking_request'));
    $this->assertSame(0, $this->rowCount('famtastic_notification_outbox'));
  }

  public function testBoundCaptureQueuesOneExactOwnerAlertAndDeduplicates(): void {
    $this->bind();
    $receipt = $this->capture();
    (new \ReflectionMethod($this->service, 'queueBoundOwnerAlert'))->invoke($this->service, 'site-fixture', $receipt['reference']);
    $this->assertSame(1, $this->rowCount('famtastic_notification_outbox'));
    $row = $this->db->select('famtastic_notification_outbox', 'n')->fields('n')->execute()->fetchAssoc();
    $this->assertSame('owner@example.test', $row['recipient']);
    $this->assertSame('queued', $row['status']);
    $this->assertSame('transactional', $row['category']);
    $this->assertNotEmpty($row['template_id']);
    $this->assertStringNotContainsString('visitor@example.test', $row['body']);
    $this->assertStringNotContainsString('Private visitor text', $row['body']);
  }

  public function testOutboxFailureRollsBackSavedRequest(): void {
    $this->bind();
    $this->db->query('DROP TABLE famtastic_notification_outbox');
    try { $this->capture(); $this->fail('Missing outbox must not leave an unseen request.'); }
    catch (\Throwable $e) { $this->assertSame(0, $this->rowCount('famtastic_booking_request')); }
  }

  public function testRevokedMembershipFailsClosedAndRollsBack(): void {
    $this->bind();
    $this->db->update('famtastic_membership')->fields(['status' => 'removed'])->execute();
    try { $this->capture(); $this->fail('Removed membership must not receive an alert.'); }
    catch (\RuntimeException $e) { $this->assertSame('booking_owner_notification_unavailable', $e->getMessage()); }
    $this->assertSame(0, $this->rowCount('famtastic_booking_request'));
    $this->assertSame(0, $this->rowCount('famtastic_notification_outbox'));
  }

  public function testWorkspaceDiscoveryDoesNotLeakAnotherOwnersSite(): void {
    $this->bind();
    $this->assertSame([['site_key' => 'site-fixture', 'business_name' => 'Local fixture']], $this->portal->bookingSites(11, 7));
    $this->assertSame([], $this->portal->bookingSites(11, 8));
    $this->assertSame([], $this->portal->bookingSites(99, 7));
  }

  public function testCrossSiteStatusMutationDoesNotChangeCapturedRequest(): void {
    $this->capture('noise-cuts');
    $id = (int) $this->db->select('famtastic_booking_request', 'r')->fields('r', ['id'])->execute()->fetchField();
    try { $this->service->updateStatus('another-site', $id, 'closed'); $this->fail('Cross-site update accepted.'); }
    catch (\RuntimeException $e) { $this->assertSame('booking_request_not_found', $e->getMessage()); }
    $this->assertSame('new', $this->db->select('famtastic_booking_request', 'r')->fields('r', ['status'])->execute()->fetchField());
  }

  public function testUnverifiedBoundOwnerCannotReceiveOrDiscoverRequests(): void {
    $this->bind();
    $this->db->update('famtastic_customer')->fields(['verified_at' => 0])->execute();
    try { $this->capture(); $this->fail('Unverified owner accepted.'); }
    catch (\RuntimeException $e) { $this->assertSame('booking_owner_notification_unavailable', $e->getMessage()); }
    $this->assertSame(0, $this->rowCount('famtastic_booking_request'));
    $this->assertSame([], $this->portal->bookingSites(11, 7));
  }
}
