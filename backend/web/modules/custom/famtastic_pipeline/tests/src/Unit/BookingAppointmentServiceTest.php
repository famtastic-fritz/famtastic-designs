<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\Php as Uuid;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\famtastic_pipeline\Controller\BookingAppointmentController;
use Drupal\famtastic_pipeline\Service\BookingAppointmentService;
use Drupal\famtastic_pipeline\Service\BookingAvailabilityService;
use Drupal\famtastic_pipeline\Service\CustomerPortalService;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__, 3) . '/src/Service/BookingAppointmentService.php';
require_once dirname(__DIR__, 3) . '/src/Service/CustomerPortalService.php';
require_once dirname(__DIR__, 3) . '/src/Service/BookingAvailabilityService.php';
require_once dirname(__DIR__, 3) . '/src/Controller/BookingAppointmentController.php';

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
  private LockBackendInterface $lock;
  private TimeInterface $time;

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
      'famtastic_booking_availability' => 'id INTEGER PRIMARY KEY AUTOINCREMENT, public_id TEXT UNIQUE, site_key TEXT, label TEXT, starts_at INTEGER, ends_at INTEGER, service_keys_json TEXT, status TEXT, created INTEGER, changed INTEGER',
    ] as $table => $columns) {
      $this->db->query('CREATE TABLE ' . $table . ' (' . $columns . ')');
    }
    $time = $this->createMock(TimeInterface::class);
    $this->time = $time;
    $time->method('getRequestTime')->willReturn(1700000000);
    $portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($portal, 'database'))->setValue($portal, $this->db);
    (new \ReflectionProperty($portal, 'time'))->setValue($portal, $time);
    $lock = $this->createMock(LockBackendInterface::class);
    $this->lock = $lock;
    $lock->method('acquire')->willReturnCallback(function (): bool {
      if ($this->onLock !== NULL) {
        ($this->onLock)();
        $this->onLock = NULL;
      }
      return TRUE;
    });
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
    $token = $this->proposalToken($proposal);
    $this->assertArrayNotHasKey('proposal_token', $proposal);
    $snapshot = $this->service->proposalSnapshot($proposal['public_id'], $token);
    $this->assertArrayNotHasKey('customer', $snapshot);
    $accepted = $this->service->respondToProposal($proposal['public_id'], $token, 'accept');
    $again = $this->service->respondToProposal($proposal['public_id'], $token, 'accept');
    $this->assertSame('confirmed', $accepted['status']);
    $this->assertSame($accepted, $again);
    $this->assertArrayNotHasKey('customer', $accepted);
    $this->assertSame(1700200000, $accepted['starts_at']);
    $this->assertFalse($this->service->proposalSnapshot($proposal['public_id'], $token)['proposal_available']);
    $this->assertSame(2, (int) $this->db->select('famtastic_notification_outbox', 'n')->countQuery()->execute()->fetchField());
  }

  private ?\Closure $onLock = NULL;

  private function proposalToken(array $proposal): string {
    $body = $this->db->select('famtastic_notification_outbox', 'n')->fields('n', ['body'])
      ->condition('notification_key', 'booking-appointment:' . $proposal['public_id'] . ':' . $proposal['revision'])->execute()->fetchField();
    preg_match('/#token=([A-Za-z0-9_-]+)/', (string) $body, $matches);
    $this->assertNotEmpty($matches[1] ?? NULL);
    return $matches[1];
  }

  public function testIdempotencyRejectsDifferentPayloadAndIsSiteScoped(): void {
    $first = $this->command('confirm', 1, 1700100000, 'same-key-across-tenants');
    $this->db->update('famtastic_booking_request')->fields(['site_key' => 'second-barber'])->condition('id', 2)->execute();
    $second = $this->service->ownerCommand('second-barber', [
      'action' => 'confirm', 'request_id' => 2, 'starts_at' => 1700100000, 'ends_at' => 1700103600,
      'timezone' => 'America/New_York', 'idempotency_key' => 'same-key-across-tenants',
    ], 99);
    $this->assertNotSame($first['id'], $second['id']);
    $this->expectExceptionMessage('appointment_idempotency_conflict');
    $this->command('confirm', 1, 1700300000, 'same-key-across-tenants');
  }

  public function testReplayReturnsOriginalReceiptAfterLaterStateChange(): void {
    $first = $this->command('confirm', 1, 1700100000, 'stable-command-receipt');
    $this->service->ownerCommand('tighten-up-your-locs', [
      'action' => 'cancel', 'appointment_id' => $first['id'], 'expected_revision' => 1, 'idempotency_key' => 'later-cancel-receipt',
    ], 99);
    $this->assertSame($first, $this->command('confirm', 1, 1700100000, 'stable-command-receipt'));
    $this->assertSame('cancelled', $this->service->ownerSnapshot('tighten-up-your-locs')['appointments'][0]['status']);
  }

  public function testOutboxFailureRollsBackAppointmentRequestAndEvent(): void {
    $this->db->getClientConnection()->exec("CREATE TRIGGER reject_notice BEFORE INSERT ON famtastic_notification_outbox BEGIN SELECT RAISE(ABORT, 'outbox unavailable'); END");
    try {
      $this->command('confirm', 1, 1700100000, 'retry-after-outbox-fails');
      $this->fail('Outbox failure was silently ignored.');
    }
    catch (\Exception $error) {
      $this->assertStringContainsString('outbox unavailable', $error->getMessage());
    }
    $this->assertSame(0, (int) $this->db->select('famtastic_booking_appointment', 'a')->countQuery()->execute()->fetchField());
    $this->assertSame(0, (int) $this->db->select('famtastic_booking_appointment_event', 'e')->countQuery()->execute()->fetchField());
    $this->assertSame('new', $this->db->select('famtastic_booking_request', 'r')->fields('r', ['status'])->condition('id', 1)->execute()->fetchField());
    $this->db->query('DROP TRIGGER reject_notice');
    $this->assertSame('confirmed', $this->command('confirm', 1, 1700100000, 'retry-after-outbox-fails')['status']);
  }

  public function testExpiredProposalFreesHoldButRetainsOriginalReservation(): void {
    $first = $this->command('confirm', 1, 1700100000, 'original-reservation-key');
    $this->service->ownerCommand('tighten-up-your-locs', [
      'action' => 'reschedule', 'appointment_id' => $first['id'], 'expected_revision' => 1,
      'starts_at' => 1700200000, 'ends_at' => 1700203600, 'timezone' => 'America/New_York', 'idempotency_key' => 'expiring-reschedule-key',
    ], 99);
    $this->db->update('famtastic_booking_appointment')->fields(['proposal_expires_at' => 1700000000])->condition('id', $first['id'])->execute();
    $this->assertSame('confirmed', $this->command('confirm', 2, 1700200000, 'reuse-expired-hold-key')['status']);
    $this->expectExceptionMessage('appointment_slot_conflict');
    $this->command('confirm', 3, 1700100000, 'original-still-blocked');
  }

  public function testCancelledProposalCannotBeAcceptedEvenWhenCancellationRacesLock(): void {
    $proposal = $this->command('propose', 1, 1700100000, 'cancel-between-read-lock');
    $token = $this->proposalToken($proposal);
    $this->onLock = function () use ($proposal): void {
      $this->db->update('famtastic_booking_appointment')->fields(['status' => 'cancelled', 'proposal_token_hash' => '', 'revision' => 2])->condition('id', $proposal['id'])->execute();
    };
    $this->expectExceptionMessage('appointment_proposal_not_found');
    $this->service->respondToProposal($proposal['public_id'], $token, 'accept');
  }

  public function testCompletedAppointmentRevokesConsumedProposalToken(): void {
    $proposal = $this->command('propose', 1, 1700100000, 'complete-revokes-token');
    $token = $this->proposalToken($proposal);
    $this->service->respondToProposal($proposal['public_id'], $token, 'accept');
    $this->service->ownerCommand('tighten-up-your-locs', [
      'action' => 'complete', 'appointment_id' => $proposal['id'], 'expected_revision' => 2, 'idempotency_key' => 'complete-consumed-proposal',
    ], 99);
    $this->expectExceptionMessage('appointment_proposal_not_found');
    $this->service->respondToProposal($proposal['public_id'], $token, 'accept');
  }

  public function testFailedReschedulePreservesOriginalAndNoNotice(): void {
    $first = $this->command('confirm', 1, 1700100000, 'rollback-original-key');
    $this->command('confirm', 2, 1700200000, 'rollback-conflicting-key');
    try {
      $this->service->ownerCommand('tighten-up-your-locs', [
        'action' => 'reschedule', 'appointment_id' => $first['id'], 'expected_revision' => 1,
        'starts_at' => 1700200000, 'ends_at' => 1700203600, 'timezone' => 'America/New_York', 'idempotency_key' => 'rollback-reschedule-key',
      ], 99);
      $this->fail('Conflicting reschedule succeeded.');
    }
    catch (\RuntimeException $error) {
      $this->assertSame('appointment_slot_conflict', $error->getMessage());
    }
    $this->assertSame($first, $this->service->ownerSnapshot('tighten-up-your-locs')['appointments'][0]);
    $this->assertSame(2, (int) $this->db->select('famtastic_notification_outbox', 'n')->countQuery()->execute()->fetchField());
  }

  public function testPastSlotIsRejectedAndOtherTenantNoticeHasNoShay(): void {
    $this->db->update('famtastic_booking_request')->fields(['site_key' => 'second-barber'])->condition('id', 2)->execute();
    $this->service->ownerCommand('second-barber', [
      'action' => 'confirm', 'request_id' => 2, 'starts_at' => 1700100000, 'ends_at' => 1700103600,
      'timezone' => 'America/New_York', 'idempotency_key' => 'second-barber-notice',
    ], 99);
    $body = $this->db->select('famtastic_notification_outbox', 'n')->fields('n', ['body'])->execute()->fetchField();
    $this->assertStringNotContainsString('Shay', $body);
    $this->expectExceptionMessage('appointment_time_invalid');
    $this->command('confirm', 1, 1699900000, 'cannot-confirm-past-time');
  }

  public function testOpeningDeduplicatesAndPublicInvitationHidesOccupiedTime(): void {
    $availability = new BookingAvailabilityService($this->db, $this->time, new Uuid());
    $input = ['label' => 'Opening', 'starts_at' => 1700100000, 'ends_at' => 1700103600, 'status' => 'published', 'service_keys' => ['loc-care'], 'idempotency_key' => 'opening-retry-same-key'];
    $first = $availability->create('tighten-up-your-locs', $input);
    $this->assertSame($first, $availability->create('tighten-up-your-locs', $input));
    $this->assertCount(1, $availability->publicWindows('tighten-up-your-locs')['windows']);
    $this->command('confirm', 1, 1700100000, 'occupy-published-window');
    $this->assertSame([], $availability->publicWindows('tighten-up-your-locs')['windows']);
    $input['label'] = 'Changed payload';
    $this->expectExceptionMessage('availability_idempotency_conflict');
    $availability->create('tighten-up-your-locs', $input);
  }

  public function testForeignAvailabilityCannotBeAttached(): void {
    $availability = new BookingAvailabilityService($this->db, $this->time, new Uuid());
    $window = $availability->create('second-barber', ['label' => 'Opening', 'starts_at' => 1700100000, 'ends_at' => 1700103600, 'status' => 'published']);
    $this->expectExceptionMessage('appointment_availability_invalid');
    $this->service->ownerCommand('tighten-up-your-locs', [
      'action' => 'confirm', 'request_id' => 1, 'availability_id' => $window['id'], 'starts_at' => 1700100000, 'ends_at' => 1700103600,
      'timezone' => 'America/New_York', 'idempotency_key' => 'foreign-window-rejected',
    ], 99);
  }

  public function testCancelProposalNoticeAvoidsEpochAndSupersedesRetry(): void {
    $proposal = $this->command('propose', 1, 1700100000, 'proposal-cancel-notice');
    $this->db->update('famtastic_notification_outbox')->fields(['status' => 'retry', 'available_at' => 1700900000])->execute();
    $this->service->ownerCommand('tighten-up-your-locs', [
      'action' => 'cancel', 'appointment_id' => $proposal['id'], 'expected_revision' => 1, 'idempotency_key' => 'proposal-cancel-command',
    ], 99);
    $prior = $this->db->select('famtastic_notification_outbox', 'n')->fields('n', ['status'])
      ->condition('notification_key', 'booking-appointment:' . $proposal['public_id'] . ':1')->execute()->fetchField();
    $this->assertSame('superseded', $prior);
    $notice = $this->db->select('famtastic_notification_outbox', 'n')->fields('n', ['body'])->condition('status', 'queued')->execute()->fetchField();
    $this->assertStringContainsString('proposed appointment was cancelled', $notice);
    $this->assertStringNotContainsString('1969', $notice);
    $this->assertStringNotContainsString('1970', $notice);
  }

  public function testHistoryCannotHideUpcomingAppointmentAndTruncationIsExplicit(): void {
    $first = $this->command('confirm', 1, 1700100000, 'future-above-history');
    $row = $this->db->select('famtastic_booking_appointment', 'a')->fields('a')->condition('id', $first['id'])->execute()->fetchAssoc();
    unset($row['id']);
    for ($i = 0; $i < 201; $i++) {
      $old = $row;
      $old['public_id'] = (new Uuid())->generate();
      $old['request_id'] = 100 + $i;
      $old['status'] = 'completed';
      $old['starts_at'] = 1600000000 + $i;
      $old['ends_at'] = 1600003600 + $i;
      $this->db->insert('famtastic_booking_appointment')->fields($old)->execute();
    }
    $snapshot = $this->service->ownerSnapshot('tighten-up-your-locs');
    $this->assertSame($first['id'], $snapshot['appointments'][0]['id']);
    $this->assertTrue($snapshot['has_more']);
    $this->assertCount(200, $snapshot['appointments']);
  }

  public function testPublicOpeningLimitIsAppliedAfterConflictFilter(): void {
    $availability = new BookingAvailabilityService($this->db, $this->time, new Uuid());
    for ($i = 0; $i < 12; $i++) {
      $availability->create('tighten-up-your-locs', ['label' => 'Occupied', 'starts_at' => 1700100000 + $i, 'ends_at' => 1700103600, 'status' => 'published']);
    }
    $free = $availability->create('tighten-up-your-locs', ['label' => 'Free', 'starts_at' => 1700200000, 'ends_at' => 1700203600, 'status' => 'published']);
    $this->command('confirm', 1, 1700100000, 'public-filter-before-limit');
    $windows = $availability->publicWindows('tighten-up-your-locs')['windows'];
    $this->assertCount(1, $windows);
    $this->assertSame($free['public_id'], $windows[0]['public_id']);
  }

  public function testInvalidProposalAttemptsConsumeFloodBudget(): void {
    $flood = $this->createMock(FloodInterface::class);
    $flood->method('isAllowed')->willReturn(TRUE);
    $flood->expects($this->exactly(2))->method('register');
    $controller = new BookingAppointmentController($this->service, $flood);
    $id = '00000000-0000-4000-8000-000000000001';
    $this->assertSame(404, $controller->respond(Request::create('/', 'POST', [], [], [], [], json_encode(['token' => 'invalid', 'decision' => 'accept'])), $id)->getStatusCode());
    $this->assertSame(404, $controller->proposal(Request::create('/'), $id)->getStatusCode());
  }

  public function testProposalHeaderWorksWithoutSecretInUrl(): void {
    $proposal = $this->command('propose', 1, 1700100000, 'header-token-proposal');
    $token = $this->proposalToken($proposal);
    $flood = $this->createMock(FloodInterface::class);
    $flood->method('isAllowed')->willReturn(TRUE);
    $controller = new BookingAppointmentController($this->service, $flood);
    $request = Request::create('/api/booking-appointment/' . $proposal['public_id']);
    $request->headers->set('X-Appointment-Token', $token);
    $response = $controller->proposal($request, $proposal['public_id']);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    $this->assertStringNotContainsString($token, $request->getUri());
    $this->assertStringNotContainsString($token, $response->getContent());
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
