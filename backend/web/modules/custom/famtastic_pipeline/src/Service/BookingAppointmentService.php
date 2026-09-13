<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Statement\FetchAs;
use Drupal\Core\Lock\LockBackendInterface;

/**
 * Durable appointment lifecycle for one exact owner-bound site. */
final class BookingAppointmentService {

  private const ACTIVE = ['confirmed', 'proposal_pending'];
  private const TERMINAL = ['cancelled', 'completed', 'declined'];

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    private readonly LockBackendInterface $lock,
    private readonly CustomerPortalService $portal,
  ) {}

  /**
   * Returns appointments plus the append-only timeline for the owner desk. */
  public function ownerSnapshot(string $siteKey): array {
    $siteKey = $this->siteKey($siteKey);
    $rows = $this->database->select('famtastic_booking_appointment', 'appointment')
      ->fields('appointment')
      ->condition('site_key', $siteKey)
      ->orderBy('starts_at', 'ASC')
      ->orderBy('proposed_starts_at', 'ASC')
      ->range(0, 200)
      ->execute()
      ->fetchAll(FetchAs::Associative);
    $appointments = [];
    foreach ($rows as $row) {
      $request = $this->request($siteKey, (int) $row['request_id'], FALSE);
      $appointments[] = $this->normalize($row, $request ?: []);
    }
    return ['site_key' => $siteKey, 'appointments' => $appointments];
  }

  /**
   * Applies one idempotent owner command under a site-scoped persistent lock.
   *
   * Supported actions: confirm, propose, reschedule, cancel, complete.
   */
  public function ownerCommand(string $siteKey, array $input, int $actorUid): array {
    $siteKey = $this->siteKey($siteKey);
    $action = (string) ($input['action'] ?? '');
    if (!in_array($action, ['confirm', 'propose', 'reschedule', 'cancel', 'complete'], TRUE)) {
      throw new \InvalidArgumentException('appointment_action_invalid');
    }
    $idempotency = $this->idempotency((string) ($input['idempotency_key'] ?? ''));
    $lockName = 'famtastic:appointment:' . $siteKey;
    if (!$this->lock->acquire($lockName, 15.0)) {
      throw new \RuntimeException('appointment_busy');
    }

    try {
      $prior = $this->database->select('famtastic_booking_appointment_event', 'event')
        ->fields('event', ['appointment_id'])
        ->condition('idempotency_key', $idempotency)
        ->execute()
        ->fetchField();
      if ($prior !== FALSE) {
        return $this->load((int) $prior, $siteKey);
      }

      $transaction = $this->database->startTransaction();
      try {
        $result = match ($action) {
          'confirm', 'propose' => $this->createFromRequest($siteKey, $action, $input, $actorUid, $idempotency),
          'reschedule', 'cancel', 'complete' => $this->changeExisting($siteKey, $action, $input, $actorUid, $idempotency),
        };
      }
      catch (\Throwable $error) {
        $transaction->rollBack();
        throw $error;
      }
      unset($transaction);

      $this->queueCustomerNotice($result, $action);
      return $result;
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * Accepts or declines a token-scoped proposed time without account access. */
  public function respondToProposal(string $publicId, string $token, string $decision): array {
    if (!in_array($decision, ['accept', 'decline'], TRUE) || !preg_match('/^[0-9a-f-]{36}$/i', $publicId)) {
      throw new \InvalidArgumentException('appointment_response_invalid');
    }
    $row = $this->database->select('famtastic_booking_appointment', 'appointment')
      ->fields('appointment')
      ->condition('public_id', $publicId)
      ->execute()
      ->fetchAssoc();
    if (!$row || empty($row['proposal_token_hash']) || !hash_equals((string) $row['proposal_token_hash'], hash('sha256', $token))) {
      throw new \RuntimeException('appointment_proposal_not_found');
    }
    $now = $this->time->getRequestTime();
    if ((int) $row['proposal_expires_at'] < $now) {
      throw new \RuntimeException('appointment_proposal_expired');
    }
    $responseKey = 'proposal:' . hash('sha256', $token . ':' . $decision);
    $prior = $this->database->select('famtastic_booking_appointment_event', 'event')
      ->fields('event', ['appointment_id'])->condition('idempotency_key', $responseKey)->execute()->fetchField();
    if ($prior !== FALSE) {
      return $this->publicResponse($this->load((int) $prior, (string) $row['site_key']));
    }
    if ((int) $row['proposed_starts_at'] <= 0 || (int) $row['proposed_ends_at'] <= 0) {
      throw new \RuntimeException('appointment_proposal_not_found');
    }
    $siteKey = (string) $row['site_key'];
    $lockName = 'famtastic:appointment:' . $siteKey;
    if (!$this->lock->acquire($lockName, 15.0)) {
      throw new \RuntimeException('appointment_busy');
    }
    try {
      $transaction = $this->database->startTransaction();
      try {
        $fields = ['changed' => $now, 'revision' => (int) $row['revision'] + 1];
        if ($decision === 'accept') {
          $start = (int) $row['proposed_starts_at'];
          $end = (int) $row['proposed_ends_at'];
          $this->assertSlotFree($siteKey, $start, $end, (int) $row['id']);
          $fields += [
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => 'confirmed',
            'proposed_starts_at' => 0,
            'proposed_ends_at' => 0,
          ];
        }
        else {
          $fields += [
            'status' => (int) $row['starts_at'] > 0 ? 'confirmed' : 'declined',
            'proposed_starts_at' => 0,
            'proposed_ends_at' => 0,
          ];
        }
        $updated = $this->database->update('famtastic_booking_appointment')->fields($fields)
          ->condition('id', (int) $row['id'])->condition('revision', (int) $row['revision'])->execute();
        if ($updated !== 1) {
          throw new \RuntimeException('appointment_revision_conflict');
        }
        $eventType = $decision === 'accept' ? 'proposal_accepted' : 'proposal_declined';
        $this->event((int) $row['id'], $siteKey, (int) $row['request_id'], $eventType, $responseKey, 0, $fields);
      }
      catch (\Throwable $error) {
        $transaction->rollBack();
        throw $error;
      }
      unset($transaction);
      return $this->publicResponse($this->load((int) $row['id'], $siteKey));
    }
    finally {
      $this->lock->release($lockName);
    }
  }

  /**
   * Returns the minimum public proposal details after token verification. */
  public function proposalSnapshot(string $publicId, string $token): array {
    if (!preg_match('/^[0-9a-f-]{36}$/i', $publicId)) {
      throw new \InvalidArgumentException('appointment_response_invalid');
    }
    $row = $this->database->select('famtastic_booking_appointment', 'appointment')
      ->fields('appointment')->condition('public_id', $publicId)->execute()->fetchAssoc();
    if (!$row || empty($row['proposal_token_hash']) || !hash_equals((string) $row['proposal_token_hash'], hash('sha256', $token))) {
      throw new \RuntimeException('appointment_proposal_not_found');
    }
    if ((int) $row['proposal_expires_at'] < $this->time->getRequestTime()) {
      throw new \RuntimeException('appointment_proposal_expired');
    }
    return [
      'public_id' => (string) $row['public_id'],
      'status' => (string) $row['status'],
      'proposed_starts_at' => (int) $row['proposed_starts_at'],
      'proposed_ends_at' => (int) $row['proposed_ends_at'],
      'timezone' => (string) $row['timezone'],
      'expires_at' => (int) $row['proposal_expires_at'],
    ];
  }

  /**
   * Creates an appointment from one exact booking request.
   */
  private function createFromRequest(string $siteKey, string $action, array $input, int $actorUid, string $idempotency): array {
    $requestId = (int) ($input['request_id'] ?? 0);
    $request = $this->request($siteKey, $requestId);
    $existing = $this->database->select('famtastic_booking_appointment', 'appointment')
      ->fields('appointment')->condition('site_key', $siteKey)->condition('request_id', $requestId)->execute()->fetchAssoc();
    if ($existing) {
      throw new \RuntimeException('appointment_request_already_active');
    }
    [$start, $end, $timezone] = $this->slot($input);
    $this->assertSlotFree($siteKey, $start, $end);
    $now = $this->time->getRequestTime();
    $publicId = $this->uuid->generate();
    $token = $action === 'propose' ? $this->token() : '';
    $fields = [
      'public_id' => $publicId,
      'site_key' => $siteKey,
      'request_id' => $requestId,
      'availability_id' => !empty($input['availability_id']) ? (int) $input['availability_id'] : NULL,
      'service_key' => (string) $request['service_key'],
      'starts_at' => $action === 'confirm' ? $start : 0,
      'ends_at' => $action === 'confirm' ? $end : 0,
      'proposed_starts_at' => $action === 'propose' ? $start : 0,
      'proposed_ends_at' => $action === 'propose' ? $end : 0,
      'timezone' => $timezone,
      'status' => $action === 'confirm' ? 'confirmed' : 'proposal_pending',
      'revision' => 1,
      'proposal_token_hash' => $token === '' ? '' : hash('sha256', $token),
      'proposal_expires_at' => $token === '' ? 0 : $now + 172800,
      'created_by_uid' => $actorUid,
      'created' => $now,
      'changed' => $now,
    ];
    $id = (int) $this->database->insert('famtastic_booking_appointment')->fields($fields)->execute();
    $this->database->update('famtastic_booking_request')->fields(['status' => 'responded', 'changed' => $now])
      ->condition('id', $requestId)->condition('site_key', $siteKey)->execute();
    $this->event($id, $siteKey, $requestId, $action === 'confirm' ? 'confirmed' : 'proposed', $idempotency, $actorUid, $fields);
    $result = $this->load($id, $siteKey);
    if ($token !== '') {
      $result['proposal_token'] = $token;
    }
    return $result;
  }

  /**
   * Changes an existing appointment using optimistic concurrency.
   */
  private function changeExisting(string $siteKey, string $action, array $input, int $actorUid, string $idempotency): array {
    $id = (int) ($input['appointment_id'] ?? 0);
    $row = $this->database->select('famtastic_booking_appointment', 'appointment')->fields('appointment')
      ->condition('id', $id)->condition('site_key', $siteKey)->execute()->fetchAssoc();
    if (!$row) {
      throw new \RuntimeException('appointment_not_found');
    }
    $expected = (int) ($input['expected_revision'] ?? 0);
    if ($expected <= 0 || $expected !== (int) $row['revision']) {
      throw new \RuntimeException('appointment_revision_conflict');
    }
    if (in_array((string) $row['status'], self::TERMINAL, TRUE)) {
      throw new \RuntimeException('appointment_terminal');
    }
    $now = $this->time->getRequestTime();
    $fields = ['revision' => $expected + 1, 'changed' => $now];
    $token = '';
    if ($action === 'reschedule') {
      [$start, $end, $timezone] = $this->slot($input);
      $this->assertSlotFree($siteKey, $start, $end, $id);
      $token = $this->token();
      $fields += [
        'status' => 'proposal_pending',
        'proposed_starts_at' => $start,
        'proposed_ends_at' => $end,
        'timezone' => $timezone,
        'proposal_token_hash' => hash('sha256', $token),
        'proposal_expires_at' => $now + 172800,
      ];
    }
    elseif ($action === 'cancel') {
      $fields += ['status' => 'cancelled', 'proposed_starts_at' => 0, 'proposed_ends_at' => 0];
    }
    else {
      if ((string) $row['status'] !== 'confirmed') {
        throw new \RuntimeException('appointment_not_confirmed');
      }
      $fields += ['status' => 'completed'];
    }
    $updated = $this->database->update('famtastic_booking_appointment')->fields($fields)
      ->condition('id', $id)->condition('site_key', $siteKey)->condition('revision', $expected)->execute();
    if ($updated !== 1) {
      throw new \RuntimeException('appointment_revision_conflict');
    }
    $eventType = match ($action) {
      'reschedule' => 'reschedule_proposed',
      'cancel' => 'cancelled',
      default => 'completed',
    };
    $this->event($id, $siteKey, (int) $row['request_id'], $eventType, $idempotency, $actorUid, $fields);
    $result = $this->load($id, $siteKey);
    if ($token !== '') {
      $result['proposal_token'] = $token;
    }
    return $result;
  }

  /**
   * Refuses overlapping confirmed or proposed appointment times.
   */
  private function assertSlotFree(string $siteKey, int $start, int $end, int $excludeId = 0): void {
    $rows = $this->database->select('famtastic_booking_appointment', 'appointment')->fields('appointment', [
      'id', 'status', 'starts_at', 'ends_at', 'proposed_starts_at', 'proposed_ends_at',
    ])->condition('site_key', $siteKey)->condition('status', self::ACTIVE, 'IN')->execute()->fetchAll(FetchAs::Associative);
    foreach ($rows as $row) {
      if ((int) $row['id'] === $excludeId) {
        continue;
      }
      $occupied = [
        [(int) $row['starts_at'], (int) $row['ends_at']],
        [(int) $row['proposed_starts_at'], (int) $row['proposed_ends_at']],
      ];
      foreach ($occupied as [$usedStart, $usedEnd]) {
        if ($usedStart > 0 && $start < $usedEnd && $end > $usedStart) {
          throw new \RuntimeException('appointment_slot_conflict');
        }
      }
    }
  }

  /**
   * Queues a transactional customer notice after persistence succeeds.
   */
  private function queueCustomerNotice(array $appointment, string $action): void {
    if ($action === 'complete') {
      return;
    }
    $recipient = (string) ($appointment['customer']['email'] ?? '');
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
      return;
    }
    $publicId = (string) $appointment['public_id'];
    $when = $this->when(
      $appointment,
      $action === 'propose' || $action === 'reschedule' || (int) ($appointment['starts_at'] ?? 0) === 0,
    );
    if (in_array($action, ['propose', 'reschedule'], TRUE)) {
      $token = (string) ($appointment['proposal_token'] ?? '');
      $link = 'https://famtasticdesigns.com/appointment/' . rawurlencode($publicId) . '?token=' . rawurlencode($token);
      $subject = 'Shay proposed a time for your appointment request';
      $body = "Shay proposed {$when}. Review and accept or decline it here:\n\n{$link}\n\nThis link expires in 48 hours. No payment was taken.";
    }
    else {
      $subject = $action === 'cancel' ? 'Your appointment was cancelled' : 'Your appointment request was confirmed';
      $body = $action === 'cancel' ? "Your appointment scheduled for {$when} was cancelled. Contact Shay if you need another time." : "Shay confirmed your appointment for {$when}. No payment was taken by this confirmation.";
    }
    $this->portal->queueNotification('booking-appointment:' . $publicId . ':' . (int) $appointment['revision'], 'transactional', $recipient, $subject, $body);
  }

  /**
   * Loads one appointment inside an exact site boundary.
   */
  private function load(int $id, string $siteKey): array {
    $row = $this->database->select('famtastic_booking_appointment', 'appointment')->fields('appointment')
      ->condition('id', $id)->condition('site_key', $siteKey)->execute()->fetchAssoc();
    if (!$row) {
      throw new \RuntimeException('appointment_not_found');
    }
    return $this->normalize($row, $this->request($siteKey, (int) $row['request_id'], FALSE) ?: []);
  }

  /**
   * Loads the booking request associated with an appointment.
   */
  private function request(string $siteKey, int $requestId, bool $required = TRUE): array|false {
    $row = $this->database->select('famtastic_booking_request', 'request')->fields('request', [
      'id', 'service_key', 'customer_name', 'email', 'phone', 'requested_window', 'message', 'status', 'created', 'changed',
    ])->condition('id', $requestId)->condition('site_key', $siteKey)->execute()->fetchAssoc();
    if (!$row && $required) {
      throw new \RuntimeException('booking_request_not_found');
    }
    return $row;
  }

  /**
   * Writes one immutable command event.
   */
  private function event(int $appointmentId, string $siteKey, int $requestId, string $type, string $idempotency, int $actorUid, array $payload): void {
    $this->database->insert('famtastic_booking_appointment_event')->fields([
      'appointment_id' => $appointmentId,
      'site_key' => $siteKey,
      'request_id' => $requestId,
      'event_type' => $type,
      'idempotency_key' => $idempotency,
      'actor_uid' => $actorUid,
      'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
      'created' => $this->time->getRequestTime(),
    ])->execute();
  }

  /**
   * Normalizes database scalars for the owner API.
   */
  private function normalize(array $row, array $request): array {
    $integerFields = [
      'id', 'request_id', 'availability_id', 'starts_at', 'ends_at',
      'proposed_starts_at', 'proposed_ends_at', 'revision',
      'proposal_expires_at', 'created_by_uid', 'created', 'changed',
    ];
    foreach ($integerFields as $field) {
      if (array_key_exists($field, $row)) {
        $row[$field] = $row[$field] === NULL ? NULL : (int) $row[$field];
      }
    }
    unset($row['proposal_token_hash']);
    $row['customer'] = [
      'name' => (string) ($request['customer_name'] ?? ''),
      'email' => (string) ($request['email'] ?? ''),
      'phone' => (string) ($request['phone'] ?? ''),
    ];
    return $row;
  }

  /**
   * Removes owner-only customer contact fields from token-scoped responses.
   */
  private function publicResponse(array $appointment): array {
    return array_intersect_key($appointment, array_flip([
      'public_id',
      'status',
      'starts_at',
      'ends_at',
      'proposed_starts_at',
      'proposed_ends_at',
      'timezone',
      'revision',
      'changed',
    ]));
  }

  /**
   * Validates and normalizes an appointment time slot.
   */
  private function slot(array $input): array {
    $start = $this->timestamp($input['starts_at'] ?? NULL);
    $end = $this->timestamp($input['ends_at'] ?? NULL);
    $timezone = trim((string) ($input['timezone'] ?? 'America/New_York'));
    try {
      new \DateTimeZone($timezone);
    }
    catch (\Throwable) {
      throw new \InvalidArgumentException('appointment_timezone_invalid');
    }
    if ($start <= 0 || $end <= $start || $end - $start > 43200) {
      throw new \InvalidArgumentException('appointment_time_invalid');
    }
    return [$start, $end, $timezone];
  }

  /**
   * Converts a supported timestamp value to an integer.
   */
  private function timestamp(mixed $value): int {
    return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : 0);
  }

  /**
   * Normalizes a site identifier for exact-scoped queries.
   */
  private function siteKey(string $value): string {
    $value = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(trim($value))) ?? '', '-');
    if ($value === '' || mb_strlen($value) > 64) {
      throw new \InvalidArgumentException('appointment_site_invalid');
    }
    return $value;
  }

  /**
   * Validates a caller-supplied idempotency key.
   */
  private function idempotency(string $value): string {
    $value = trim($value);
    if (!preg_match('/^[A-Za-z0-9:_-]{12,96}$/', $value)) {
      throw new \InvalidArgumentException('appointment_idempotency_invalid');
    }
    return $value;
  }

  /**
   * Generates a high-entropy customer response token.
   */
  private function token(): string {
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
  }

  /**
   * Formats an appointment time for a transactional notice.
   */
  private function when(array $appointment, bool $proposal): string {
    $start = (int) ($proposal ? $appointment['proposed_starts_at'] : $appointment['starts_at']);
    $timezone = new \DateTimeZone((string) ($appointment['timezone'] ?? 'America/New_York'));
    return (new \DateTimeImmutable('@' . $start))->setTimezone($timezone)->format('l, F j \a\t g:i A T');
  }

}
