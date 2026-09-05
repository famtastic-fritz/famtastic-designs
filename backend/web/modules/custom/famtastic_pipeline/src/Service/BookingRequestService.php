<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;

/**
 * Durable, owner-managed request-to-book records.
 *
 * This service deliberately does not talk to Booksy, Google Calendar, or a
 * payment provider. A request is only a request until the site owner responds.
 */
final class BookingRequestService {

  private const STATUSES = ['new', 'reviewing', 'responded', 'closed', 'declined'];

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
  ) {}

  /** Creates a validated request and returns its non-sensitive reference. */
  public function create(string $siteKey, array $input, string $source): array {
    $siteKey = $this->siteKey($siteKey);
    $name = $this->text((string) ($input['name'] ?? ''), 120);
    $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
    $phone = $this->text((string) ($input['phone'] ?? ''), 40);
    $serviceKey = $this->slug((string) ($input['service_key'] ?? 'general'));
    $window = $this->text((string) ($input['requested_window'] ?? ''), 180, TRUE);
    $message = $this->text((string) ($input['message'] ?? ''), 2000, TRUE);
    $consent = !empty($input['consent']);

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
      throw new \InvalidArgumentException('booking_contact_invalid');
    }
    if ($window === '') {
      throw new \InvalidArgumentException('requested_window_required');
    }
    if (!$consent) {
      throw new \InvalidArgumentException('booking_consent_required');
    }

    $now = $this->time->getRequestTime();
    $publicId = $this->uuid->generate();
    $this->database->insert('famtastic_booking_request')->fields([
      'public_id' => $publicId,
      'site_key' => $siteKey,
      'service_key' => $serviceKey,
      'customer_name' => $name,
      'email' => $email,
      'email_hash' => hash('sha256', $email),
      'phone' => $phone,
      'requested_window' => $window,
      'message' => $message,
      'consent' => 1,
      'status' => 'new',
      'source' => $this->text($source, 120),
      'created' => $now,
      'changed' => $now,
    ])->execute();

    return [
      'status' => 'received',
      'reference' => $publicId,
      'next_step' => 'owner_review_required',
    ];
  }

  /** Returns records for an authorized operator; callers enforce access. */
  public function ownerSnapshot(string $siteKey): array {
    $siteKey = $this->siteKey($siteKey);
    $rows = $this->database->select('famtastic_booking_request', 'request')
      ->fields('request', ['id', 'public_id', 'service_key', 'customer_name', 'email', 'phone', 'requested_window', 'message', 'consent', 'status', 'source', 'created', 'changed'])
      ->condition('site_key', $siteKey)
      ->orderBy('created', 'DESC')
      ->range(0, 100)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    return [
      'site_key' => $siteKey,
      'requests' => array_map(static function (array $row): array {
        foreach (['id', 'consent', 'created', 'changed'] as $field) {
          $row[$field] = (int) $row[$field];
        }
        return $row;
      }, $rows),
    ];
  }

  /** Updates workflow state only; calendar and payment remain external. */
  public function updateStatus(string $siteKey, int $requestId, string $status): void {
    $siteKey = $this->siteKey($siteKey);
    if ($requestId <= 0 || !in_array($status, self::STATUSES, TRUE)) {
      throw new \InvalidArgumentException('booking_status_invalid');
    }
    $updated = $this->database->update('famtastic_booking_request')
      ->fields(['status' => $status, 'changed' => $this->time->getRequestTime()])
      ->condition('id', $requestId)
      ->condition('site_key', $siteKey)
      ->execute();
    if ($updated === 0) {
      $exists = $this->database->select('famtastic_booking_request', 'request')
        ->fields('request', ['id'])
        ->condition('id', $requestId)
        ->condition('site_key', $siteKey)
        ->execute()
        ->fetchField();
      if ($exists === FALSE) {
        throw new \RuntimeException('booking_request_not_found');
      }
    }
  }

  private function siteKey(string $value): string {
    $value = $this->slug($value);
    if ($value === '' || mb_strlen($value) > 64) {
      throw new \InvalidArgumentException('booking_site_invalid');
    }
    return $value;
  }

  private function slug(string $value): string {
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
  }

  private function text(string $value, int $limit, bool $multiline = FALSE): string {
    $value = strip_tags($value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $value = $multiline ? preg_replace('/[ \t]+/u', ' ', $value) : preg_replace('/\s+/u', ' ', $value);
    return mb_substr(trim((string) $value), 0, $limit);
  }

}
