<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\Php as Uuid;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\famtastic_pipeline\Service\BookingAppointmentService;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;

require_once dirname(__DIR__, 3) . '/src/Service/BookingAppointmentService.php';
require_once dirname(__DIR__, 3) . '/src/Service/CustomerPortalService.php';

/**
 * SQLite proof of the exact-site appointment lifecycle and customer outbox.
 */
#[\PHPUnit\Framework\Attributes\Group('famtastic_pipeline')]
final class BookingAppointmentServiceTest extends UnitTestCase {
  /**
   * In-memory Drupal database connection. */
  private Connection $db;

  /**
   * Appointment service under test. */
  private BookingAppointmentService $service;

  /**
   * Creates the isolated database and service fixture.
   */
  protected function setUp(): void {
    parent::setUp();
    $options = [
      'database' => ':memory:',
      'prefix' => '',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
      'driver' => 'sqlite',
    ];
    $this->db = new Connection(Connection::open($options), $options);
    foreach ([
      'famtastic_booking_request' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, site_key TEXT, service_key TEXT, customer_name TEXT, email TEXT, phone TEXT, requested_window TEXT, message TEXT, status TEXT, created INTEGER, changed INTEGER',
      'famtastic_booking_appointment' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, site_key TEXT, request_id INTEGER, availability_id INTEGER, service_key TEXT, starts_at INTEGER, ends_at INTEGER, proposed_starts_at INTEGER, proposed_ends_at INTEGER, timezone TEXT, status TEXT, revision INTEGER, proposal_token_hash TEXT, proposal_expires_at INTEGER, created_by_uid INTEGER, created INTEGER, changed INTEGER, UNIQUE(site_key, request_id)',
      'famtastic_booking_appointment_event' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, appointment_id INTEGER, site_key TEXT, request_id INTEGER, event_type TEXT, idempotency_key TEXT UNIQUE, actor_uid INTEGER, payload_json TEXT, created INTEGER',
      'famtastic_notification_outbox' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, notification_key TEXT UNIQUE, category TEXT, recipient TEXT, subject TEXT, body TEXT, template_id TEXT, template_version INTEGER, status TEXT, attempts INTEGER, max_attempts INTEGER, available_at INTEGER, created INTEGER, changed INTEGER',
    ] as $table => $columns) {
      $this->db->query('CREATE TABLE ' . $table . ' (' . $columns . ')');
    }
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);
    $portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($portal, 'database'))->setValue($portal, $this->db);
    (new \ReflectionProperty($portal, 'time'))->setValue($portal, $time);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $this->service = new BookingAppointmentService($this->db, $time, new Uuid(), $lock, $portal);
    $this->request(1, 'visitor@example.test');
    $this->request(2, 'second@example.test');
    $this->request(3, 'third@example.test');
  }

  /**
   * Inserts one exact-site booking request fixture.
   */
  private function request(int $id, string $email): void {
    $this->db->insert('famtastic_booking_request')->fields([
      'id' => $id,
      'public_id' => '00000000-0000-4000-8000-' . str_pad((string) $id, 12, '0', STR_PAD_LEFT),
      'site_key' => 'tighten-up-your-locs',
      'service_key' => 'loc-care',
      'customer_name' => 'Client ' . $id,
      'email' => $email,
      'phone' => '',
      'requested_window' => 'Afternoon',
      'message' => '',
      'status' => 'new',
      'created' => 1700000000,
      'changed' => 1700000000,
    ])->execute();
  }

  /**
   * Creates one appointment command payload.
   */
  private function command(string $action, int $requestId, int $start, string $key): array {
    return $this->service->ownerCommand('tighten-up-your-locs', [
      'action' => $action,
      'request_id' => $requestId,
      'starts_at' => $start,
      'ends_at' => $start + 3600,
      'timezone' => 'America/New_York',
      'idempotency_key' => $key,
    ], 99);
  }

  /**
   * Proves confirmation persistence, idempotency, and customer notice.
   */
  public function testConfirmIsDurableIdempotentAndQueuesOneCustomerNotice(): void {
    $first = $this->command('confirm', 1, 1700100000, 'owner:confirm:fixture-one');
    $again = $this->command('confirm', 1, 1700100000, 'owner:confirm:fixture-one');
    $this->assertSame($first['id'], $again['id']);
    $this->assertSame('confirmed', $again['status']);
    $this->assertSame(1, (int) $this->db->select('famtastic_booking_appointment', 'a')->countQuery()->execute()->fetchField());
    $this->assertSame(1, (int) $this->db->select('famtastic_booking_appointment_event', 'e')->countQuery()->execute()->fetchField());
    $this->assertSame(1, (int) $this->db->select('famtastic_notification_outbox', 'n')->countQuery()->execute()->fetchField());
    $this->assertSame('responded', $this->db->select('famtastic_booking_request', 'r')->fields('r', ['status'])->condition('id', 1)->execute()->fetchField());
  }

  /**
   * Proves overlapping slots fail without partial writes.
   */
  public function testOverlappingAppointmentFailsWithoutPartialWrites(): void {
    $this->command('confirm', 1, 1700100000, 'owner:confirm:fixture-two');
    try {
      $this->command('confirm', 2, 1700101800, 'owner:confirm:fixture-three');
      $this->fail('Overlapping appointment was accepted.');
    }
    catch (\RuntimeException $error) {
      $this->assertSame('appointment_slot_conflict', $error->getMessage());
    }
    $this->assertSame(1, (int) $this->db->select('famtastic_booking_appointment', 'a')->countQuery()->execute()->fetchField());
    $this->assertSame('new', $this->db->select('famtastic_booking_request', 'r')->fields('r', ['status'])->condition('id', 2)->execute()->fetchField());
  }

  /**
   * Proves a proposal response is replay-safe and privacy-scoped.
   */
  public function testProposalTokenAcceptsOnceAndNeverReturnsCustomerContact(): void {
    $proposal = $this->command('propose', 3, 1700200000, 'owner:propose:fixture-four');
    $token = $proposal['proposal_token'];
    $snapshot = $this->service->proposalSnapshot($proposal['public_id'], $token);
    $this->assertArrayNotHasKey('customer', $snapshot);
    $accepted = $this->service->respondToProposal($proposal['public_id'], $token, 'accept');
    $again = $this->service->respondToProposal($proposal['public_id'], $token, 'accept');
    $this->assertSame('confirmed', $accepted['status']);
    $this->assertSame($accepted, $again);
    $this->assertArrayNotHasKey('customer', $accepted);
    $this->assertSame(1700200000, $accepted['starts_at']);
  }

  /**
   * Proves stale revisions cannot overwrite a current appointment.
   */
  public function testRevisionMismatchFailsClosed(): void {
    $appointment = $this->command('confirm', 2, 1700300000, 'owner:confirm:fixture-five');
    try {
      $this->service->ownerCommand('tighten-up-your-locs', [
        'action' => 'cancel',
        'appointment_id' => $appointment['id'],
        'expected_revision' => $appointment['revision'] + 1,
        'idempotency_key' => 'owner:cancel:fixture-six',
      ], 99);
      $this->fail('Stale revision was accepted.');
    }
    catch (\RuntimeException $error) {
      $this->assertSame('appointment_revision_conflict', $error->getMessage());
    }
    $this->assertSame('confirmed', $this->service->ownerSnapshot('tighten-up-your-locs')['appointments'][0]['status']);
  }

}
