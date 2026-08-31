<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/** Durable content and inbound-capture boundary for small client sites. */
final class MicrositeService {

  private const SUPPORTED_SITES = ['thirst-trap-772'];
  private const MESSAGE_STATUSES = ['new', 'read', 'resolved', 'unsubscribed'];
  private const PRODUCT_STATUSES = ['active', 'hidden', 'sold_out'];
  private const VISUALS = ['citrus', 'berry', 'tropical', 'pink', 'lime', 'orange'];

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /** Returns only active, public content. */
  public function publicSnapshot(string $siteKey): array {
    $site = $this->loadSite($siteKey);
    if (!$site || (string) $site['status'] !== 'active') {
      throw new \RuntimeException('microsite_not_found');
    }
    $content = $this->decodeContent((string) $site['content_json']);
    $content['products'] = array_values(array_filter(
      (array) ($content['products'] ?? []),
      static fn(array $product): bool => ($product['status'] ?? '') !== 'hidden',
    ));
    return [
      'site' => $content,
      'changed' => (int) $site['changed'],
    ];
  }

  /** Stores a contact, subscriber, or unsubscribe request idempotently. */
  public function capture(string $siteKey, string $kind, array $input, string $source): array {
    $this->assertSiteKey($siteKey);
    if (!in_array($kind, ['contact', 'subscriber', 'unsubscribe'], TRUE)) {
      throw new \InvalidArgumentException('capture_kind_invalid');
    }
    if (!$this->loadSite($siteKey)) {
      throw new \RuntimeException('microsite_not_found');
    }

    $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
      throw new \InvalidArgumentException('email_invalid');
    }
    $emailHash = hash('sha256', $email);
    $now = $this->time->getRequestTime();

    if ($kind === 'unsubscribe') {
      $updated = $this->database->update('famtastic_microsite_message')
        ->fields(['status' => 'unsubscribed', 'changed' => $now])
        ->condition('site_key', $siteKey)
        ->condition('kind', 'subscriber')
        ->condition('email_hash', $emailHash)
        ->execute();
      return ['status' => 'unsubscribed', 'matched' => $updated > 0];
    }

    $name = $this->text((string) ($input['name'] ?? ''), 120);
    $phone = $this->text((string) ($input['phone'] ?? ''), 40);
    $subject = $this->text((string) ($input['subject'] ?? ''), 120);
    $message = $this->text((string) ($input['message'] ?? ''), 2000, TRUE);
    $consent = !empty($input['consent']);

    if ($kind === 'subscriber' && !$consent) {
      throw new \InvalidArgumentException('consent_required');
    }
    if ($kind === 'contact' && ($name === '' || $message === '')) {
      throw new \InvalidArgumentException('contact_fields_required');
    }

    if ($kind === 'subscriber') {
      $existing = $this->database->select('famtastic_microsite_message', 'message')
        ->fields('message', ['id', 'status'])
        ->condition('site_key', $siteKey)
        ->condition('kind', 'subscriber')
        ->condition('email_hash', $emailHash)
        ->orderBy('id', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();
      if ($existing) {
        if ((string) $existing['status'] === 'unsubscribed') {
          $this->database->update('famtastic_microsite_message')
            ->fields(['status' => 'new', 'consent' => 1, 'changed' => $now])
            ->condition('id', (int) $existing['id'])
            ->execute();
        }
        return ['status' => 'subscribed', 'duplicate' => TRUE];
      }
    }

    $this->database->insert('famtastic_microsite_message')->fields([
      'site_key' => $siteKey,
      'kind' => $kind,
      'name' => $name,
      'email' => $email,
      'email_hash' => $emailHash,
      'phone' => $phone,
      'subject' => $subject,
      'message' => $message,
      'consent' => $consent ? 1 : 0,
      'status' => 'new',
      'source' => $this->text($source, 120),
      'created' => $now,
      'changed' => $now,
    ])->execute();

    return ['status' => $kind === 'subscriber' ? 'subscribed' : 'received', 'duplicate' => FALSE];
  }

  /** Returns owner content plus recent inbound records after authorization. */
  public function ownerSnapshot(string $siteKey, int $uid, bool $admin): array {
    $site = $this->assertManageAccess($siteKey, $uid, $admin);
    $messages = $this->database->select('famtastic_microsite_message', 'message')
      ->fields('message', ['id', 'kind', 'name', 'email', 'phone', 'subject', 'message', 'consent', 'status', 'created', 'changed'])
      ->condition('site_key', $siteKey)
      ->orderBy('created', 'DESC')
      ->range(0, 100)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    return [
      'site' => $this->decodeContent((string) $site['content_json']),
      'owner_uid' => isset($site['owner_uid']) ? (int) $site['owner_uid'] : NULL,
      'messages' => array_map(static function (array $row): array {
        foreach (['id', 'consent', 'created', 'changed'] as $field) {
          $row[$field] = (int) $row[$field];
        }
        return $row;
      }, $messages),
      'changed' => (int) $site['changed'],
    ];
  }

  /** Replaces the validated content snapshot for an authorized owner. */
  public function updateContent(string $siteKey, int $uid, bool $admin, array $input): array {
    $site = $this->assertManageAccess($siteKey, $uid, $admin);
    $current = $this->decodeContent((string) $site['content_json']);
    $content = $this->normalizeContent($siteKey, $input, $current);
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_microsite')->fields([
      'content_json' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
      'changed' => $now,
    ])->condition('site_key', $siteKey)->execute();
    return ['site' => $content, 'changed' => $now];
  }

  /** Changes one message status without exposing cross-site records. */
  public function updateMessageStatus(string $siteKey, int $uid, bool $admin, int $messageId, string $status): void {
    $this->assertManageAccess($siteKey, $uid, $admin);
    if ($messageId <= 0 || !in_array($status, self::MESSAGE_STATUSES, TRUE)) {
      throw new \InvalidArgumentException('message_status_invalid');
    }
    $exists = $this->database->select('famtastic_microsite_message', 'message')
      ->fields('message', ['id'])
      ->condition('id', $messageId)
      ->condition('site_key', $siteKey)
      ->execute()
      ->fetchField();
    if ($exists === FALSE) {
      throw new \RuntimeException('message_not_found');
    }
    $this->database->update('famtastic_microsite_message')
      ->fields(['status' => $status, 'changed' => $this->time->getRequestTime()])
      ->condition('id', $messageId)
      ->condition('site_key', $siteKey)
      ->execute();
  }

  /** Assigns an existing Drupal account as the site owner. */
  public function assignOwner(string $siteKey, ?int $uid): void {
    $this->assertSiteKey($siteKey);
    $this->database->update('famtastic_microsite')->fields([
      'owner_uid' => $uid && $uid > 0 ? $uid : NULL,
      'changed' => $this->time->getRequestTime(),
    ])->condition('site_key', $siteKey)->execute();
  }

  public function ownerId(string $siteKey): ?int {
    $site = $this->loadSite($siteKey);
    $uid = (int) ($site['owner_uid'] ?? 0);
    return $uid > 0 ? $uid : NULL;
  }

  private function normalizeContent(string $siteKey, array $input, array $current): array {
    $brandInput = is_array($input['brand'] ?? NULL) ? $input['brand'] : [];
    $currentBrand = is_array($current['brand'] ?? NULL) ? $current['brand'] : [];
    $brand = [
      'name' => $this->text((string) ($brandInput['name'] ?? $currentBrand['name'] ?? ''), 80),
      'tagline' => $this->text((string) ($brandInput['tagline'] ?? $currentBrand['tagline'] ?? ''), 120),
      'service_area' => $this->text((string) ($brandInput['service_area'] ?? $currentBrand['service_area'] ?? ''), 160),
      'intro' => $this->text((string) ($brandInput['intro'] ?? $currentBrand['intro'] ?? ''), 280, TRUE),
    ];
    if ($brand['name'] === '' || $brand['tagline'] === '') {
      throw new \InvalidArgumentException('brand_fields_required');
    }

    $products = [];
    foreach (array_slice((array) ($input['products'] ?? []), 0, 24) as $index => $item) {
      if (!is_array($item)) {
        continue;
      }
      $name = $this->text((string) ($item['name'] ?? ''), 80);
      if ($name === '') {
        continue;
      }
      $status = (string) ($item['status'] ?? 'active');
      $visual = (string) ($item['visual'] ?? 'pink');
      $products[] = [
        'id' => $this->slug((string) ($item['id'] ?? $name . '-' . $index)),
        'name' => $name,
        'kicker' => $this->text((string) ($item['kicker'] ?? ''), 40),
        'description' => $this->text((string) ($item['description'] ?? ''), 240, TRUE),
        'price_label' => $this->text((string) ($item['price_label'] ?? ''), 40),
        'status' => in_array($status, self::PRODUCT_STATUSES, TRUE) ? $status : 'active',
        'visual' => in_array($visual, self::VISUALS, TRUE) ? $visual : 'pink',
      ];
    }

    $events = [];
    foreach (array_slice((array) ($input['events'] ?? []), 0, 20) as $index => $item) {
      if (!is_array($item)) {
        continue;
      }
      $title = $this->text((string) ($item['title'] ?? ''), 100);
      if ($title === '') {
        continue;
      }
      $events[] = [
        'id' => $this->slug((string) ($item['id'] ?? $title . '-' . $index)),
        'title' => $title,
        'date_label' => $this->text((string) ($item['date_label'] ?? ''), 80),
        'location' => $this->text((string) ($item['location'] ?? ''), 160),
        'details' => $this->text((string) ($item['details'] ?? ''), 280, TRUE),
        'status' => in_array((string) ($item['status'] ?? 'scheduled'), ['scheduled', 'cancelled', 'hidden'], TRUE) ? (string) ($item['status'] ?? 'scheduled') : 'scheduled',
      ];
    }

    $socialInput = is_array($input['socials'] ?? NULL) ? $input['socials'] : [];
    $currentSocials = is_array($current['socials'] ?? NULL) ? $current['socials'] : [];
    $socials = [];
    foreach (['instagram', 'facebook'] as $network) {
      $url = trim((string) ($socialInput[$network] ?? $currentSocials[$network] ?? ''));
      if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && str_starts_with($url, 'https://')) {
        $socials[$network] = mb_substr($url, 0, 500);
      }
    }

    return [
      'schema_version' => 1,
      'site_key' => $siteKey,
      'brand' => $brand,
      'products' => $products,
      'events' => $events,
      'socials' => $socials,
    ];
  }

  private function assertManageAccess(string $siteKey, int $uid, bool $admin): array {
    $site = $this->loadSite($siteKey);
    if (!$site) {
      throw new \RuntimeException('microsite_not_found');
    }
    if (!$admin && ($uid <= 0 || (int) ($site['owner_uid'] ?? 0) !== $uid)) {
      throw new \RuntimeException('microsite_access_denied');
    }
    return $site;
  }

  private function loadSite(string $siteKey): ?array {
    $this->assertSiteKey($siteKey);
    $row = $this->database->select('famtastic_microsite', 'site')
      ->fields('site')
      ->condition('site_key', $siteKey)
      ->execute()
      ->fetchAssoc();
    return $row ?: NULL;
  }

  private function assertSiteKey(string $siteKey): void {
    if (!in_array($siteKey, self::SUPPORTED_SITES, TRUE)) {
      throw new \InvalidArgumentException('microsite_key_invalid');
    }
  }

  private function decodeContent(string $json): array {
    $content = json_decode($json, TRUE);
    if (!is_array($content)) {
      throw new \RuntimeException('microsite_content_invalid');
    }
    return $content;
  }

  private function text(string $value, int $limit, bool $multiline = FALSE): string {
    $value = strip_tags($value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $value = $multiline ? preg_replace('/[ \t]+/u', ' ', $value) : preg_replace('/\s+/u', ' ', $value);
    return mb_substr(trim((string) $value), 0, $limit);
  }

  private function slug(string $value): string {
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return mb_substr($value !== '' ? $value : 'item', 0, 64);
  }

}
