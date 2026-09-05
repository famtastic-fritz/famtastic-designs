<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;

/** Owner-published request windows, deliberately separate from calendars. */
final class BookingAvailabilityService {

  private const STATUSES = ['draft', 'published', 'hidden', 'closed'];

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
  ) {}

  /** Returns only currently useful published invitations for the public site. */
  public function publicWindows(string $siteKey): array {
    $siteKey = $this->siteKey($siteKey);
    $now = $this->time->getRequestTime();
    $rows = $this->database->select('famtastic_booking_availability', 'window')
      ->fields('window', ['public_id', 'label', 'starts_at', 'ends_at', 'service_keys_json'])
      ->condition('site_key', $siteKey)
      ->condition('status', 'published')
      ->condition('ends_at', $now, '>')
      ->orderBy('starts_at', 'ASC')
      ->range(0, 12)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    return ['site_key' => $siteKey, 'windows' => array_map([$this, 'normalizeRow'], $rows)];
  }

  /** Returns every window to the authorized owner route. */
  public function ownerWindows(string $siteKey): array {
    $siteKey = $this->siteKey($siteKey);
    $rows = $this->database->select('famtastic_booking_availability', 'window')
      ->fields('window')
      ->condition('site_key', $siteKey)
      ->orderBy('starts_at', 'ASC')
      ->range(0, 100)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    return ['site_key' => $siteKey, 'windows' => array_map([$this, 'normalizeRow'], $rows)];
  }

  /** Creates an unpublished or published invitation. */
  public function create(string $siteKey, array $input): array {
    $siteKey = $this->siteKey($siteKey);
    $window = $this->validated($input);
    $now = $this->time->getRequestTime();
    $publicId = $this->uuid->generate();
    $id = $this->database->insert('famtastic_booking_availability')->fields([
      'public_id' => $publicId,
      'site_key' => $siteKey,
      'label' => $window['label'],
      'starts_at' => $window['starts_at'],
      'ends_at' => $window['ends_at'],
      'service_keys_json' => json_encode($window['service_keys'], JSON_THROW_ON_ERROR),
      'status' => $window['status'],
      'created' => $now,
      'changed' => $now,
    ])->execute();
    return ['id' => (int) $id, 'public_id' => $publicId] + $window;
  }

  /** Changes the window fields or visibility; never changes an external calendar. */
  public function update(string $siteKey, int $id, array $input): array {
    $siteKey = $this->siteKey($siteKey);
    if ($id <= 0) {
      throw new \InvalidArgumentException('availability_window_invalid');
    }
    $existing = $this->database->select('famtastic_booking_availability', 'window')
      ->fields('window')
      ->condition('id', $id)
      ->condition('site_key', $siteKey)
      ->execute()
      ->fetchAssoc();
    if (!$existing) {
      throw new \RuntimeException('availability_window_not_found');
    }
    $window = $this->validated($input + [
      'label' => $existing['label'],
      'starts_at' => (int) $existing['starts_at'],
      'ends_at' => (int) $existing['ends_at'],
      'status' => $existing['status'],
      'service_keys' => json_decode((string) $existing['service_keys_json'], TRUE) ?: [],
    ]);
    $this->database->update('famtastic_booking_availability')->fields([
      'label' => $window['label'],
      'starts_at' => $window['starts_at'],
      'ends_at' => $window['ends_at'],
      'service_keys_json' => json_encode($window['service_keys'], JSON_THROW_ON_ERROR),
      'status' => $window['status'],
      'changed' => $this->time->getRequestTime(),
    ])->condition('id', $id)->condition('site_key', $siteKey)->execute();
    return ['id' => $id, 'public_id' => (string) $existing['public_id']] + $window;
  }

  private function validated(array $input): array {
    $label = $this->text((string) ($input['label'] ?? ''), 100);
    $startsAt = $this->timestamp($input['starts_at'] ?? NULL);
    $endsAt = $this->timestamp($input['ends_at'] ?? NULL);
    $status = (string) ($input['status'] ?? 'draft');
    $services = [];
    foreach (array_slice((array) ($input['service_keys'] ?? []), 0, 12) as $service) {
      $key = $this->slug((string) $service);
      if ($key !== '' && !in_array($key, $services, TRUE)) {
        $services[] = $key;
      }
    }
    if ($label === '' || $startsAt <= 0 || $endsAt <= $startsAt || $endsAt - $startsAt > 43200 || !in_array($status, self::STATUSES, TRUE)) {
      throw new \InvalidArgumentException('availability_window_invalid');
    }
    return ['label' => $label, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'service_keys' => $services, 'status' => $status];
  }

  private function normalizeRow(array $row): array {
    foreach (['id', 'starts_at', 'ends_at', 'created', 'changed'] as $field) {
      if (array_key_exists($field, $row)) {
        $row[$field] = (int) $row[$field];
      }
    }
    $services = json_decode((string) ($row['service_keys_json'] ?? '[]'), TRUE);
    $row['service_keys'] = is_array($services) ? $services : [];
    unset($row['service_keys_json']);
    return $row;
  }

  private function timestamp(mixed $value): int {
    return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : 0);
  }

  private function siteKey(string $value): string {
    $value = $this->slug($value);
    if ($value === '' || mb_strlen($value) > 64) {
      throw new \InvalidArgumentException('availability_site_invalid');
    }
    return $value;
  }

  private function slug(string $value): string {
    $value = mb_strtolower(trim($value));
    return trim(preg_replace('/[^a-z0-9]+/', '-', $value) ?? '', '-');
  }

  private function text(string $value, int $limit): string {
    $value = strip_tags($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return mb_substr(trim($value), 0, $limit);
  }

}
