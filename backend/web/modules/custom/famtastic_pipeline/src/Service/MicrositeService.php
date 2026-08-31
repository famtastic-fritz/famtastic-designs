<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\Php as UuidGenerator;
use Drupal\Core\Database\Connection;

/** Durable content and inbound-capture boundary for small client sites. */
final class MicrositeService {

  private const SUPPORTED_SITES = ['thirst-trap-772'];
  private const MESSAGE_STATUSES = ['new', 'read', 'resolved', 'unsubscribed'];
  private const PRODUCT_STATUSES = ['active', 'hidden', 'sold_out'];
  private const ORDER_STATUSES = ['requested', 'confirmed', 'ready', 'completed', 'cancelled'];
  private const PAYMENT_STATUSES = ['unverified', 'confirmed', 'refunded', 'not_required'];
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
    $payments = is_array($content['payments'] ?? NULL) ? $content['payments'] : [];
    $content['payments'] = [
      'preorders_enabled' => !empty($payments['preorders_enabled']),
      'cash_app_available' => $this->cashAppUrl((string) ($payments['cash_app_url'] ?? '')) !== '',
      'pickup_note' => $this->text((string) ($payments['pickup_note'] ?? ''), 240, TRUE),
    ];
    return [
      'site' => $content,
      'changed' => (int) $site['changed'],
    ];
  }

  /** Stores a preorder before offering any owner-managed external payment link. */
  public function preorder(string $siteKey, array $input, string $source): array {
    $site = $this->loadSite($siteKey);
    if (!$site || (string) $site['status'] !== 'active') {
      throw new \RuntimeException('microsite_not_found');
    }
    $content = $this->decodeContent((string) $site['content_json']);
    $payments = is_array($content['payments'] ?? NULL) ? $content['payments'] : [];
    if (empty($payments['preorders_enabled'])) {
      throw new \RuntimeException('preorders_unavailable');
    }

    $name = $this->text((string) ($input['name'] ?? ''), 120);
    $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
    $phone = $this->text((string) ($input['phone'] ?? ''), 40);
    $notes = $this->text((string) ($input['notes'] ?? ''), 1000, TRUE);
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
      throw new \InvalidArgumentException('preorder_contact_invalid');
    }

    $available = [];
    foreach ((array) ($content['products'] ?? []) as $product) {
      if (is_array($product) && ($product['status'] ?? '') === 'active' && !empty($product['id'])) {
        $available[(string) $product['id']] = $product;
      }
    }
    $items = [];
    $itemCount = 0;
    $totalCents = 0;
    $totalKnown = TRUE;
    $seen = [];
    foreach (array_slice((array) ($input['items'] ?? []), 0, 12) as $selection) {
      if (!is_array($selection)) {
        continue;
      }
      $productId = $this->slug((string) ($selection['product_id'] ?? ''));
      $quantityRaw = $selection['quantity'] ?? 0;
      $quantity = is_int($quantityRaw) ? $quantityRaw : (is_string($quantityRaw) && ctype_digit($quantityRaw) ? (int) $quantityRaw : 0);
      if ($quantity < 1 || $quantity > 20 || isset($seen[$productId]) || !isset($available[$productId])) {
        throw new \InvalidArgumentException('preorder_items_invalid');
      }
      $seen[$productId] = TRUE;
      $itemCount += $quantity;
      if ($itemCount > 20) {
        throw new \InvalidArgumentException('preorder_items_invalid');
      }
      $product = $available[$productId];
      $priceCents = isset($product['price_cents']) && is_int($product['price_cents']) ? $product['price_cents'] : NULL;
      if ($priceCents === NULL) {
        $totalKnown = FALSE;
      }
      else {
        $totalCents += $priceCents * $quantity;
      }
      $items[] = [
        'product_id' => $productId,
        'name' => $this->text((string) ($product['name'] ?? ''), 80),
        'quantity' => $quantity,
        'unit_price_cents' => $priceCents,
      ];
    }
    if ($items === []) {
      throw new \InvalidArgumentException('preorder_items_required');
    }

    $pickupEventId = $this->slug((string) ($input['pickup_event_id'] ?? 'coordinate'));
    $pickupLabel = 'Coordinate pickup directly';
    if ($pickupEventId !== 'coordinate') {
      $matched = FALSE;
      foreach ((array) ($content['events'] ?? []) as $event) {
        if (is_array($event) && ($event['status'] ?? '') === 'scheduled' && ($event['id'] ?? '') === $pickupEventId) {
          $pickupLabel = $this->text(implode(' · ', array_filter([(string) ($event['title'] ?? ''), (string) ($event['date_label'] ?? ''), (string) ($event['location'] ?? '')])), 160);
          $matched = TRUE;
          break;
        }
      }
      if (!$matched) {
        throw new \InvalidArgumentException('preorder_pickup_invalid');
      }
    }

    $cashAppUrl = $this->cashAppUrl((string) ($payments['cash_app_url'] ?? ''));
    $paymentAvailable = $cashAppUrl !== '' && $totalKnown && $totalCents >= 100;
    $now = $this->time->getRequestTime();
    $publicId = (new UuidGenerator())->generate();
    $orderNumber = $this->newOrderNumber();
    $this->database->insert('famtastic_microsite_order')->fields([
      'public_id' => $publicId,
      'order_number' => $orderNumber,
      'site_key' => $siteKey,
      'customer_name' => $name,
      'email' => $email,
      'email_hash' => hash('sha256', $email),
      'phone' => $phone,
      'items_json' => json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
      'item_count' => $itemCount,
      'total_cents' => $totalKnown ? $totalCents : NULL,
      'currency' => 'USD',
      'pickup_event_id' => $pickupEventId,
      'pickup_label' => $pickupLabel,
      'notes' => $notes,
      'payment_method' => $paymentAvailable ? 'cash_app' : 'coordinate',
      'payment_destination' => $paymentAvailable ? $cashAppUrl : '',
      'payment_status' => 'unverified',
      'order_status' => 'requested',
      'source' => $this->text($source, 120),
      'created' => $now,
      'changed' => $now,
    ])->execute();

    return [
      'status' => 'requested',
      'order' => [
        'reference' => $orderNumber,
        'item_count' => $itemCount,
        'total_cents' => $totalKnown ? $totalCents : NULL,
        'currency' => 'USD',
        'pickup_label' => $pickupLabel,
        'payment_status' => 'unverified',
      ],
      'payment' => [
        'available' => $paymentAvailable,
        'url' => $paymentAvailable ? $cashAppUrl : '',
        'label' => $paymentAvailable ? $this->text((string) ($payments['cash_app_label'] ?? ''), 80) : '',
        'instructions' => $this->text((string) ($payments['payment_note'] ?? ''), 240, TRUE),
      ],
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
    $orders = $this->database->select('famtastic_microsite_order', 'orders')
      ->fields('orders', ['id', 'order_number', 'customer_name', 'email', 'phone', 'items_json', 'item_count', 'total_cents', 'currency', 'pickup_label', 'notes', 'payment_method', 'payment_status', 'order_status', 'created', 'changed'])
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
      'orders' => array_map(static function (array $row): array {
        foreach (['id', 'item_count', 'created', 'changed'] as $field) {
          $row[$field] = (int) $row[$field];
        }
        $row['total_cents'] = $row['total_cents'] === NULL ? NULL : (int) $row['total_cents'];
        $items = json_decode((string) $row['items_json'], TRUE);
        $row['items'] = is_array($items) ? $items : [];
        unset($row['items_json']);
        return $row;
      }, $orders),
      'changed' => (int) $site['changed'],
    ];
  }

  /** Lets the authorized owner record fulfillment and independently verified payment state. */
  public function updateOrderStatus(string $siteKey, int $uid, bool $admin, int $orderId, string $orderStatus, string $paymentStatus): void {
    $this->assertManageAccess($siteKey, $uid, $admin);
    if ($orderId <= 0 || !in_array($orderStatus, self::ORDER_STATUSES, TRUE) || !in_array($paymentStatus, self::PAYMENT_STATUSES, TRUE)) {
      throw new \InvalidArgumentException('order_status_invalid');
    }
    $updated = $this->database->update('famtastic_microsite_order')
      ->fields([
        'order_status' => $orderStatus,
        'payment_status' => $paymentStatus,
        'changed' => $this->time->getRequestTime(),
      ])
      ->condition('id', $orderId)
      ->condition('site_key', $siteKey)
      ->execute();
    if ($updated === 0) {
      $exists = $this->database->select('famtastic_microsite_order', 'orders')
        ->fields('orders', ['id'])
        ->condition('id', $orderId)
        ->condition('site_key', $siteKey)
        ->execute()
        ->fetchField();
      if ($exists === FALSE) {
        throw new \RuntimeException('order_not_found');
      }
    }
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

    $currentProducts = [];
    foreach ((array) ($current['products'] ?? []) as $currentProduct) {
      if (is_array($currentProduct) && !empty($currentProduct['id'])) {
        $currentProducts[(string) $currentProduct['id']] = $currentProduct;
      }
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
      $id = $this->slug((string) ($item['id'] ?? $name . '-' . $index));
      $prior = $currentProducts[$id] ?? [];
      $products[] = [
        'id' => $id,
        'name' => $name,
        'kicker' => $this->text((string) ($item['kicker'] ?? ''), 40),
        'description' => $this->text((string) ($item['description'] ?? ''), 240, TRUE),
        'price_label' => $this->text((string) ($item['price_label'] ?? ''), 40),
        'price_cents' => $this->priceCents(array_key_exists('price_cents', $item) ? $item['price_cents'] : ($prior['price_cents'] ?? NULL)),
        'status' => in_array($status, self::PRODUCT_STATUSES, TRUE) ? $status : 'active',
        'visual' => in_array($visual, self::VISUALS, TRUE) ? $visual : 'pink',
      ];
    }

    $paymentInput = is_array($input['payments'] ?? NULL) ? $input['payments'] : [];
    $currentPayments = is_array($current['payments'] ?? NULL) ? $current['payments'] : [];
    $payments = [
      'preorders_enabled' => array_key_exists('preorders_enabled', $paymentInput) ? !empty($paymentInput['preorders_enabled']) : !empty($currentPayments['preorders_enabled']),
      'cash_app_url' => $this->cashAppUrl((string) ($paymentInput['cash_app_url'] ?? $currentPayments['cash_app_url'] ?? '')),
      'cash_app_label' => $this->text((string) ($paymentInput['cash_app_label'] ?? $currentPayments['cash_app_label'] ?? ''), 80),
      'payment_note' => $this->text((string) ($paymentInput['payment_note'] ?? $currentPayments['payment_note'] ?? ''), 240, TRUE),
      'pickup_note' => $this->text((string) ($paymentInput['pickup_note'] ?? $currentPayments['pickup_note'] ?? ''), 240, TRUE),
    ];

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
      'payments' => $payments,
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

  private function priceCents(mixed $value): ?int {
    if ($value === NULL || $value === '') {
      return NULL;
    }
    if (is_int($value)) {
      $price = $value;
    }
    elseif (is_string($value) && preg_match('/^(0|[1-9][0-9]{0,7})$/', $value)) {
      $price = (int) $value;
    }
    else {
      throw new \InvalidArgumentException('product_price_invalid');
    }
    if ($price < 0 || $price > 10000000) {
      throw new \InvalidArgumentException('product_price_invalid');
    }
    return $price;
  }

  private function cashAppUrl(string $value): string {
    $value = trim($value);
    if ($value === '') {
      return '';
    }
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
      throw new \InvalidArgumentException('cash_app_url_invalid');
    }
    $parts = parse_url($value);
    if (($parts['scheme'] ?? '') !== 'https' || mb_strtolower((string) ($parts['host'] ?? '')) !== 'cash.app' || isset($parts['user']) || isset($parts['pass'])) {
      throw new \InvalidArgumentException('cash_app_url_invalid');
    }
    return mb_substr($value, 0, 500);
  }

  private function newOrderNumber(): string {
    for ($attempt = 0; $attempt < 5; $attempt++) {
      $number = 'TT772-' . mb_strtoupper(bin2hex(random_bytes(4)));
      $exists = $this->database->select('famtastic_microsite_order', 'orders')
        ->fields('orders', ['id'])
        ->condition('order_number', $number)
        ->execute()
        ->fetchField();
      if ($exists === FALSE) {
        return $number;
      }
    }
    throw new \RuntimeException('order_reference_unavailable');
  }

}
