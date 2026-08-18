<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;

/**
 * Creates, validates, and atomically redeems private service grant codes.
 */
final class GrantCodeService {

  public const CLASSES = [
    'OWNER_COMP',
    'CUSTOMER_GRANT',
    'PERCENT_PROMO',
    'FIXED_PROMO',
    'SERVICE_CREDIT',
    'PARTNER_GRANT',
    'TEST_ONLY',
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
  ) {}

  /**
   * Creates a hashed grant and returns the raw code exactly once.
   */
  public function create(array $input, int $createdByUid): array {
    $class = strtoupper(trim((string) ($input['grant_class'] ?? '')));
    if (!in_array($class, self::CLASSES, TRUE)) {
      throw new \InvalidArgumentException('Unknown grant-code class.');
    }
    $sku = strtoupper(trim((string) ($input['sku'] ?? '')));
    if ($sku === '' || strlen($sku) > 128) {
      throw new \InvalidArgumentException('A valid SKU is required.');
    }
    $type = strtolower(trim((string) ($input['discount_type'] ?? '')));
    if (!in_array($type, ['free', 'percent', 'fixed'], TRUE)) {
      throw new \InvalidArgumentException('Discount type must be free, percent, or fixed.');
    }
    $value = $type === 'free' ? 10000 : max(1, (int) ($input['discount_value'] ?? 0));
    if ($type === 'percent' && $value > 10000) {
      throw new \InvalidArgumentException('Percentage discounts use basis points and cannot exceed 10000.');
    }
    $customerId = !empty($input['customer_id']) ? (int) $input['customer_id'] : NULL;
    $organizationId = !empty($input['organization_id']) ? (int) $input['organization_id'] : NULL;
    $requestId = !empty($input['website_request_id']) ? (int) $input['website_request_id'] : NULL;
    if ($class === 'OWNER_COMP' && !$customerId) {
      throw new \InvalidArgumentException('OWNER_COMP must be restricted to one customer account.');
    }
    if ($class === 'CUSTOMER_GRANT' && (!$customerId || !$requestId)) {
      throw new \InvalidArgumentException('CUSTOMER_GRANT must be restricted to one customer and website request.');
    }
    if (!empty($input['covers_renewal'])) {
      throw new \InvalidArgumentException('Renewal sponsorship requires a separately approved service contract and is not available through grant codes.');
    }
    $prefix = match ($class) {
      'OWNER_COMP' => 'FAM-OWNER',
      'CUSTOMER_GRANT' => 'FAM-GRANT',
      'PERCENT_PROMO' => 'FAM-PCT',
      'FIXED_PROMO' => 'FAM-FIXED',
      'SERVICE_CREDIT' => 'FAM-CREDIT',
      'PARTNER_GRANT' => 'FAM-PARTNER',
      default => 'FAM-TEST',
    };
    $raw = $prefix . '-' . strtoupper(bin2hex(random_bytes(6)));
    $now = $this->time->getRequestTime();
    $id = (int) $this->database->insert('famtastic_grant_code')->fields([
      'public_id' => $this->uuid->generate(),
      'code_hash' => hash('sha256', $raw),
      'code_prefix' => $prefix,
      'label' => mb_substr(trim(strip_tags((string) ($input['label'] ?? $class))), 0, 255),
      'grant_class' => $class,
      'customer_id' => $customerId,
      'organization_id' => $organizationId,
      'website_request_id' => $requestId,
      'sku' => $sku,
      'discount_type' => $type,
      'discount_value' => $value,
      'max_redemptions' => max(1, min(1000, (int) ($input['max_redemptions'] ?? 1))),
      'redemptions' => 0,
      'expires_at' => !empty($input['expires_at']) ? (int) $input['expires_at'] : NULL,
      'covers_renewal' => 0,
      'status' => 'active',
      'created_by_uid' => $createdByUid,
      'created' => $now,
      'changed' => $now,
    ])->execute();
    return ['id' => $id, 'code' => $raw, 'class' => $class, 'sku' => $sku];
  }

  /**
   * Validates exact ownership and returns a non-secret discount quote.
   */
  public function quote(
    string $raw,
    int $customerId,
    int $organizationId,
    ?int $websiteRequestId,
    array $skuAmounts,
  ): array {
    $normalized = strtoupper(trim($raw));
    if ($normalized === '' || strlen($normalized) > 128) {
      throw new \InvalidArgumentException('Enter a valid grant code.');
    }
    $row = $this->database->select('famtastic_grant_code', 'g')->fields('g')
      ->condition('code_hash', hash('sha256', $normalized))->execute()->fetchAssoc();
    $now = $this->time->getRequestTime();
    if (!$row || $row['status'] !== 'active' || ((int) $row['expires_at'] > 0 && (int) $row['expires_at'] <= $now)) {
      throw new \InvalidArgumentException('This grant code is invalid or expired.');
    }
    if ((int) $row['redemptions'] >= (int) $row['max_redemptions']) {
      throw new \InvalidArgumentException('This grant code has already been fully redeemed.');
    }
    if ($row['grant_class'] === 'TEST_ONLY' && getenv('FAMTASTIC_ALLOW_TEST_GRANT_CODES') !== '1') {
      throw new \InvalidArgumentException('Test-only grant codes are disabled.');
    }
    foreach ([
      'customer_id' => $customerId,
      'organization_id' => $organizationId,
      'website_request_id' => $websiteRequestId,
    ] as $field => $actual) {
      if ($row[$field] !== NULL && (int) $row[$field] !== (int) $actual) {
        throw new \InvalidArgumentException('This grant code is not valid for this account or website request.');
      }
    }
    $sku = (string) $row['sku'];
    if (!array_key_exists($sku, $skuAmounts)) {
      throw new \InvalidArgumentException('This grant code does not apply to the selected services.');
    }
    $amount = max(0, (int) $skuAmounts[$sku]);
    $discount = match ($row['discount_type']) {
      'free' => $amount,
      'percent' => (int) floor($amount * ((int) $row['discount_value'] / 10000)),
      'fixed' => min($amount, (int) $row['discount_value']),
      default => 0,
    };
    if ($discount <= 0) {
      throw new \InvalidArgumentException('This grant code does not reduce the selected purchase.');
    }
    return [
      'id' => (int) $row['id'],
      'public_id' => (string) $row['public_id'],
      'grant_class' => (string) $row['grant_class'],
      'code_prefix' => (string) $row['code_prefix'],
      'sku' => $sku,
      'discount_minor' => $discount,
      'covers_renewal' => (bool) $row['covers_renewal'],
    ];
  }

  /**
   * Records one immutable redemption while holding the grant row lock.
   */
  public function redeem(array $quote, int $orderId, int $customerId, int $organizationId, ?int $requestId): void {
    $transaction = $this->database->startTransaction();
    try {
      $existing = $this->database->select('famtastic_grant_redemption', 'r')->fields('r', ['id'])
        ->condition('commerce_order_id', $orderId)->execute()->fetchField();
      if ($existing) return;
      $query = $this->database->select('famtastic_grant_code', 'g')->fields('g');
      $query->condition('id', (int) $quote['id'])->forUpdate();
      $row = $query->execute()->fetchAssoc();
      $now = $this->time->getRequestTime();
      if (
        !$row
        || $row['status'] !== 'active'
        || (int) $row['redemptions'] >= (int) $row['max_redemptions']
        || ((int) $row['expires_at'] > 0 && (int) $row['expires_at'] <= $now)
        || !hash_equals((string) $row['public_id'], (string) ($quote['public_id'] ?? ''))
        || !hash_equals((string) $row['sku'], (string) ($quote['sku'] ?? ''))
        || ($row['customer_id'] !== NULL && (int) $row['customer_id'] !== $customerId)
        || ($row['organization_id'] !== NULL && (int) $row['organization_id'] !== $organizationId)
        || ($row['website_request_id'] !== NULL && (int) $row['website_request_id'] !== (int) $requestId)
      ) {
        throw new \RuntimeException('Grant code could not be reserved. Please try again.');
      }
      $this->database->insert('famtastic_grant_redemption')->fields([
        'grant_code_id' => (int) $row['id'],
        'commerce_order_id' => $orderId,
        'customer_id' => $customerId,
        'organization_id' => $organizationId,
        'website_request_id' => $requestId,
        'sku' => (string) $quote['sku'],
        'amount_minor' => (int) $quote['discount_minor'],
        'redeemed_at' => $now,
      ])->execute();
      $redemptions = (int) $row['redemptions'] + 1;
      $updated = $this->database->update('famtastic_grant_code')->fields([
        'redemptions' => $redemptions,
        'status' => $redemptions >= (int) $row['max_redemptions'] ? 'redeemed' : 'active',
        'changed' => $now,
      ])->condition('id', (int) $row['id'])->condition('redemptions', (int) $row['redemptions'])->execute();
      if ((int) $updated !== 1) {
        throw new \RuntimeException('Grant code was claimed by another checkout. Please try again.');
      }
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
  }

}
