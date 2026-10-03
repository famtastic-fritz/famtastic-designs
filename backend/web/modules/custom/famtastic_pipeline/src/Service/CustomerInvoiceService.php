<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;

/**
 * Owns immutable private invoices and owner-hosted launch handoff state.
 *
 * This service never calls a payment provider, sends email, changes DNS,
 * deploys a site, or stores hosting credentials. Callers may advance payment
 * only after a separate trusted Commerce/Stripe boundary verifies the event.
 */
final class CustomerInvoiceService {

  public const SKU = 'FAM-BUSINESS-499';
  public const LIST_AMOUNT_MINOR = 49900;
  public const CREDIT_AMOUNT_MINOR = 39900;
  public const TOTAL_AMOUNT_MINOR = 10000;
  public const CURRENCY = 'usd';
  public const TERMS_VERSION = 'owner-hosted-launch-v1';
  public const PAYMENT_GATEWAY = 'famtastic_stripe_live';
  public const OFFLINE_PAYMENT_PROVIDER = 'zelle_owner_attested';

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
  ) {}

  /**
   * Returns the reusable, exact-cent owner-hosted invoice definition.
   */
  public static function ownerHostedDefinition(): array {
    return [
      'sku' => self::SKU,
      'currency' => self::CURRENCY,
      'list_amount_minor' => self::LIST_AMOUNT_MINOR,
      'credit_amount_minor' => self::CREDIT_AMOUNT_MINOR,
      'total_amount_minor' => self::TOTAL_AMOUNT_MINOR,
      'line_items' => [
        self::line('business_website_bundle', 'Business Website Bundle — Growth Launch, owner-hosted private variant', 49900, 'charge'),
        self::line('cinematic_author_site', 'Cinematic multi-page author website'),
        self::line('reader_experiences', 'Book, Author, Reader Circle, Press & Clubs experiences'),
        self::line('digital_author_card', 'Digital Author Card'),
        self::line('command_center', 'Private Command Center and publishing tools'),
        self::line('business_roadmap', 'Business roadmap, launch strategy, and promotional planning'),
        self::line('interactive_review', 'Interactive review and feedback workflow'),
        self::line('payment_readiness', 'Payment and fulfillment readiness planning'),
        self::line('owner_hosted_launch', 'Hosting audit, private installation, and launch preparation'),
        self::line('owner_training', 'Owner training and handoff documentation'),
        self::line('community_sponsorship_credit', 'FAMtastic Community Sponsorship Credit', -39900, 'credit'),
      ],
      'scope_snapshot' => [
        'schema' => 'famtastic.owner-hosted-launch-scope.v1',
        'delivery_model' => 'owner_hosted_private',
        'package_name' => 'Business Website Bundle — Growth Launch',
        'included_features' => [
          'cinematic_author_site',
          'reader_experiences',
          'digital_author_card',
          'command_center',
          'business_roadmap',
          'interactive_review',
          'payment_readiness',
          'owner_hosted_launch',
          'owner_training',
        ],
        'third_party_costs_owner_paid' => TRUE,
        'direct_payments_require_owner_stripe' => TRUE,
        'dns_cutover_requires_separate_approval' => TRUE,
      ],
      'terms' => [
        'version' => self::TERMS_VERSION,
        'one_time' => TRUE,
        'no_recurring_famtastic_charge' => TRUE,
        'owner_hosts_finished_platform' => TRUE,
        'payment_unlocks' => [
          'hosting_access_checklist',
          'hosting_audit',
          'migration_workspace',
          'private_installation',
        ],
        'owner_paid_expenses' => ['hosting', 'domain', 'mailbox', 'processor', 'shipping', 'fulfillment'],
        'direct_payments_require_owner_stripe' => TRUE,
        'production_dns_separate_approval' => TRUE,
      ],
    ];
  }

  /**
   * Validates exact integer arithmetic and returns a normalized definition.
   */
  public static function validateDefinition(array $definition): array {
    $required = [
      'sku', 'currency', 'list_amount_minor', 'credit_amount_minor',
      'total_amount_minor', 'line_items', 'scope_snapshot', 'terms',
    ];
    foreach ($required as $key) {
      if (!array_key_exists($key, $definition)) {
        throw new \InvalidArgumentException('invoice_definition_missing:' . $key);
      }
    }
    foreach (['list_amount_minor', 'credit_amount_minor', 'total_amount_minor'] as $field) {
      if (!is_int($definition[$field]) || $definition[$field] < 0) {
        throw new \InvalidArgumentException('invoice_amount_invalid:' . $field);
      }
    }
    if ($definition['list_amount_minor'] - $definition['credit_amount_minor'] !== $definition['total_amount_minor']) {
      throw new \InvalidArgumentException('invoice_arithmetic_mismatch');
    }
    $currency = strtolower(trim((string) $definition['currency']));
    if (preg_match('/^[a-z]{3}$/', $currency) !== 1) {
      throw new \InvalidArgumentException('invoice_currency_invalid');
    }
    $sku = trim((string) $definition['sku']);
    if ($sku === '' || strlen($sku) > 128) {
      throw new \InvalidArgumentException('invoice_sku_invalid');
    }
    if (!is_array($definition['line_items']) || !array_is_list($definition['line_items']) || $definition['line_items'] === []) {
      throw new \InvalidArgumentException('invoice_line_items_invalid');
    }
    $codes = [];
    $lineTotal = 0;
    $lineItems = [];
    foreach ($definition['line_items'] as $line) {
      if (!is_array($line) || !is_int($line['amount_minor'] ?? NULL)) {
        throw new \InvalidArgumentException('invoice_line_item_invalid');
      }
      $code = trim((string) ($line['code'] ?? ''));
      $label = trim((string) ($line['label'] ?? ''));
      $kind = trim((string) ($line['kind'] ?? ''));
      if (preg_match('/^[a-z0-9_]{2,64}$/', $code) !== 1
        || isset($codes[$code])
        || $label === ''
        || mb_strlen($label) > 255
        || !in_array($kind, ['charge', 'included', 'credit'], TRUE)) {
        throw new \InvalidArgumentException('invoice_line_item_invalid');
      }
      if (($kind === 'included' && $line['amount_minor'] !== 0)
        || ($kind === 'charge' && $line['amount_minor'] <= 0)
        || ($kind === 'credit' && $line['amount_minor'] >= 0)) {
        throw new \InvalidArgumentException('invoice_line_item_sign_invalid');
      }
      $codes[$code] = TRUE;
      $lineTotal += $line['amount_minor'];
      $lineItems[] = ['code' => $code, 'label' => $label, 'amount_minor' => $line['amount_minor'], 'kind' => $kind];
    }
    if ($lineTotal !== $definition['total_amount_minor']) {
      throw new \InvalidArgumentException('invoice_line_total_mismatch');
    }
    if (!is_array($definition['scope_snapshot']) || !is_array($definition['terms']) || trim((string) ($definition['terms']['version'] ?? '')) === '') {
      throw new \InvalidArgumentException('invoice_snapshot_invalid');
    }
    $definition['sku'] = $sku;
    $definition['currency'] = $currency;
    $definition['line_items'] = $lineItems;
    return $definition;
  }

  /**
   * Throws unless a row belongs to the exact account/request scope.
   */
  public static function assertTenantBinding(array $record, int $customerId, int $organizationId, ?int $requestId = NULL): void {
    if ($customerId <= 0 || $organizationId <= 0
      || (int) ($record['customer_id'] ?? 0) !== $customerId
      || (int) ($record['organization_id'] ?? 0) !== $organizationId
      || ($requestId !== NULL && (int) ($record['website_request_id'] ?? 0) !== $requestId)) {
      throw new \RuntimeException('invoice_scope_mismatch');
    }
  }

  /**
   * Issues one immutable owner-hosted invoice for an exact staged request.
   */
  public function issueOwnerHostedInvoice(
    string $requestPublicId,
    int $expectedCustomerId,
    int $expectedOrganizationId,
    string $invoiceNumber,
    string $stagingReceiptHash,
    int $createdByUid,
    string $actor,
    string $authority,
    ?int $dueAt = NULL,
  ): array {
    $requestPublicId = strtolower(trim($requestPublicId));
    $invoiceNumber = strtoupper(trim($invoiceNumber));
    $stagingReceiptHash = strtolower(trim($stagingReceiptHash));
    $actor = trim($actor);
    $authority = trim($authority);
    if (preg_match('/^[a-f0-9-]{36}$/', $requestPublicId) !== 1
      || preg_match('/^[A-Z0-9][A-Z0-9-]{2,63}$/', $invoiceNumber) !== 1
      || preg_match('/^[a-f0-9]{64}$/', $stagingReceiptHash) !== 1
      || $createdByUid <= 0 || $actor === '' || $authority === '') {
      throw new \InvalidArgumentException('invoice_issue_input_invalid');
    }
    if (mb_strlen($actor) > 255 || mb_strlen($authority) > 1000) {
      throw new \InvalidArgumentException('invoice_issue_evidence_too_long');
    }
    $definition = self::validateDefinition(self::ownerHostedDefinition());
    $now = $this->time->getRequestTime();
    if ($dueAt !== NULL && $dueAt < $now) {
      throw new \InvalidArgumentException('invoice_due_date_invalid');
    }

    $transaction = $this->database->startTransaction();
    try {
      $request = $this->database->select('famtastic_project_request', 'r')->fields('r')
        ->condition('public_id', $requestPublicId)->forUpdate()->execute()->fetchAssoc();
      if (!$request) {
        throw new \RuntimeException('invoice_request_not_found');
      }
      self::assertTenantBinding($request, $expectedCustomerId, $expectedOrganizationId);
      $this->assertVerifiedOwner($expectedCustomerId, $expectedOrganizationId);
      if ((string) ($request['staging_status'] ?? '') !== 'deployed'
        || !hash_equals((string) ($request['staging_receipt_hash'] ?? ''), $stagingReceiptHash)) {
        throw new \RuntimeException('invoice_staging_receipt_mismatch');
      }
      if (!empty($request['commerce_order_id'])) {
        throw new \RuntimeException('invoice_request_already_converted');
      }

      $scope = $definition['scope_snapshot'];
      $scope['issuance_evidence'] = ['actor' => $actor, 'authority' => $authority];
      $payload = $this->snapshotPayload(
        $invoiceNumber,
        (int) $request['id'],
        $expectedCustomerId,
        $expectedOrganizationId,
        $definition,
        $scope,
        $stagingReceiptHash,
      );
      $snapshotHash = hash('sha256', self::canonicalJson($payload));

      $existingQuery = $this->database->select('famtastic_customer_invoice', 'i');
      $existingOr = $existingQuery->orConditionGroup()
        ->condition('invoice_number', $invoiceNumber)
        ->condition('active_request_key', 'request:' . $request['id']);
      $existing = $existingQuery->fields('i')->condition($existingOr)
        ->range(0, 1)->execute()->fetchAssoc();
      if ($existing) {
        self::assertTenantBinding($existing, $expectedCustomerId, $expectedOrganizationId, (int) $request['id']);
        $this->assertSnapshotIntegrity($existing);
        if (!hash_equals((string) $existing['snapshot_sha256'], $snapshotHash)
          || !hash_equals((string) $existing['invoice_number'], $invoiceNumber)) {
          throw new \RuntimeException('invoice_active_conflict');
        }
        return $this->hydrate($existing);
      }

      $offer = $this->database->select('famtastic_private_offer', 'o')->fields('o')
        ->condition('website_request_id', (int) $request['id'])->condition('status', 'active')
        ->range(0, 1)->execute()->fetchAssoc();
      if ($offer) {
        self::assertTenantBinding($offer, $expectedCustomerId, $expectedOrganizationId, (int) $request['id']);
        if ((string) $offer['sku'] !== $definition['sku']
          || (int) $offer['list_amount_minor'] !== $definition['list_amount_minor']
          || (int) $offer['offered_amount_minor'] !== $definition['total_amount_minor']
          || strtolower((string) $offer['currency']) !== $definition['currency']) {
          throw new \RuntimeException('invoice_active_offer_conflict');
        }
        $offerId = (int) $offer['id'];
      }
      else {
        $offerId = (int) $this->database->insert('famtastic_private_offer')->fields([
          'public_id' => $this->uuid->generate(),
          'website_request_id' => (int) $request['id'],
          'organization_id' => $expectedOrganizationId,
          'customer_id' => $expectedCustomerId,
          'sku' => $definition['sku'],
          'list_amount_minor' => $definition['list_amount_minor'],
          'offered_amount_minor' => $definition['total_amount_minor'],
          'currency' => $definition['currency'],
          'reason' => 'FAMtastic Community Sponsorship Credit — owner-hosted private launch.',
          'status' => 'active',
          'expires_at' => $dueAt,
          'created_by_uid' => $createdByUid,
          'created' => $now,
          'changed' => $now,
        ])->execute();
      }

      $publicId = $this->uuid->generate();
      $invoiceId = (int) $this->database->insert('famtastic_customer_invoice')->fields([
        'public_id' => $publicId,
        'invoice_number' => $invoiceNumber,
        'active_request_key' => 'request:' . $request['id'],
        'website_request_id' => (int) $request['id'],
        'organization_id' => $expectedOrganizationId,
        'customer_id' => $expectedCustomerId,
        'private_offer_id' => $offerId,
        'sku' => $definition['sku'],
        'status' => 'issued',
        'currency' => $definition['currency'],
        'list_amount_minor' => $definition['list_amount_minor'],
        'credit_amount_minor' => $definition['credit_amount_minor'],
        'total_amount_minor' => $definition['total_amount_minor'],
        'line_items_json' => self::canonicalJson($definition['line_items']),
        'scope_snapshot_json' => self::canonicalJson($scope),
        'terms_snapshot_json' => self::canonicalJson($definition['terms']),
        'snapshot_sha256' => $snapshotHash,
        'staging_receipt_hash' => $stagingReceiptHash,
        'created_by_uid' => $createdByUid,
        'issued_at' => $now,
        'due_at' => $dueAt,
        'created' => $now,
        'changed' => $now,
      ])->execute();
      $handoffId = (int) $this->database->insert('famtastic_owner_hosting_handoff')->fields([
        'public_id' => $this->uuid->generate(),
        'invoice_id' => $invoiceId,
        'website_request_id' => (int) $request['id'],
        'organization_id' => $expectedOrganizationId,
        'customer_id' => $expectedCustomerId,
        'status' => 'payment_required',
        'access_unlocked' => 0,
        'access_status' => 'locked',
        'current_blocker' => 'Verified payment required',
        'owner_next_action' => 'Review and pay the invoice',
        'evidence_json' => '{}',
        'created' => $now,
        'changed' => $now,
      ])->execute();
      $this->appendEvent($invoiceId, 'invoice.issued', 'invoice:issued:' . strtolower($invoiceNumber), [
        'invoice_public_id' => $publicId,
        'invoice_number' => $invoiceNumber,
        'snapshot_sha256' => $snapshotHash,
        'handoff_id' => $handoffId,
        'actor' => $actor,
        'authority' => $authority,
      ], $createdByUid);
      $row = $this->loadById($invoiceId);
      return $this->hydrate($row);
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
  }

  /**
   * Returns the latest invoice for an exact portal request scope.
   */
  public function invoiceForRequest(int $customerId, int $organizationId, string $requestPublicId): ?array {
    $query = $this->database->select('famtastic_customer_invoice', 'i');
    $query->join('famtastic_project_request', 'r', 'r.id = i.website_request_id');
    $row = $query->fields('i')->condition('i.customer_id', $customerId)
      ->condition('i.organization_id', $organizationId)->condition('r.public_id', strtolower(trim($requestPublicId)))
      ->orderBy('i.id', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    if (!$row) {
      return NULL;
    }
    $this->assertSnapshotIntegrity($row);
    return $this->hydrate($row);
  }

  /**
   * Returns one invoice only when the supplied tenant and request match. */
  public function invoiceByPublicId(string $publicId, int $customerId, int $organizationId, ?int $requestId = NULL): ?array {
    $row = $this->database->select('famtastic_customer_invoice', 'i')->fields('i')
      ->condition('public_id', strtolower(trim($publicId)))->range(0, 1)->execute()->fetchAssoc();
    if (!$row) {
      return NULL;
    }
    self::assertTenantBinding($row, $customerId, $organizationId, $requestId);
    $this->assertSnapshotIntegrity($row);
    return $this->hydrate($row);
  }

  /**
   * Links the accepted invoice snapshot to one Commerce order. */
  public function linkCommerceOrder(
    string $publicId,
    int $customerId,
    int $organizationId,
    int $requestId,
    int $orderId,
    string $termsVersion,
    string $idempotencyKey,
  ): array {
    if ($orderId <= 0 || preg_match('/^[a-zA-Z0-9:_.-]{12,128}$/', $idempotencyKey) !== 1) {
      throw new \InvalidArgumentException('invoice_checkout_input_invalid');
    }
    $now = $this->time->getRequestTime();
    $transaction = $this->database->startTransaction();
    try {
      $row = $this->lockedInvoice($publicId);
      self::assertTenantBinding($row, $customerId, $organizationId, $requestId);
      $this->assertSnapshotIntegrity($row);
      $terms = json_decode((string) $row['terms_snapshot_json'], TRUE, 512, JSON_THROW_ON_ERROR);
      if (!hash_equals((string) ($terms['version'] ?? ''), trim($termsVersion))) {
        throw new \RuntimeException('invoice_terms_mismatch');
      }
      if (!empty($row['commerce_order_id']) && (int) $row['commerce_order_id'] !== $orderId) {
        throw new \RuntimeException('invoice_order_conflict');
      }
      if (in_array((string) $row['status'], ['payment_pending', 'paid'], TRUE)) {
        return ['invoice' => $this->hydrate($row), 'transitioned' => FALSE];
      }
      if (!in_array((string) $row['status'], ['issued', 'viewed', 'accepted'], TRUE)) {
        throw new \RuntimeException('invoice_not_payable');
      }
      $this->database->update('famtastic_customer_invoice')->fields([
        'commerce_order_id' => $orderId,
        'status' => 'payment_pending',
        'accepted_at' => $row['accepted_at'] ?: $now,
        'changed' => $now,
      ])->condition('id', (int) $row['id'])->condition('snapshot_sha256', (string) $row['snapshot_sha256'])->execute();
      $this->database->update('famtastic_private_offer')->fields([
        'status' => 'accepted',
        'commerce_order_id' => $orderId,
        'accepted_at' => $now,
        'changed' => $now,
      ])->condition('id', (int) $row['private_offer_id'])->condition('status', 'active')->execute();
      $this->database->update('famtastic_owner_hosting_handoff')->fields([
        'commerce_order_id' => $orderId,
        'changed' => $now,
      ])
        ->condition('invoice_id', (int) $row['id'])->execute();
      $this->appendEvent((int) $row['id'], 'invoice.checkout_linked', $idempotencyKey, [
        'order_id' => $orderId,
        'terms_version' => $termsVersion,
        'snapshot_sha256' => (string) $row['snapshot_sha256'],
      ], $customerId);
      return ['invoice' => $this->hydrate($this->loadById((int) $row['id'])), 'transitioned' => TRUE];
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
  }

  /**
   * Activates the hosting handoff after trusted exact payment evidence.
   */
  public function activatePaid(
    string $publicId,
    int $customerId,
    int $organizationId,
    int $requestId,
    int $orderId,
    int $amountMinor,
    string $currency,
    string $provider,
    string $providerEventId,
  ): array {
    $provider = trim($provider);
    $providerEventId = trim($providerEventId);
    if ($orderId <= 0 || !hash_equals(self::PAYMENT_GATEWAY, $provider) || $providerEventId === '' || strlen($providerEventId) > 255) {
      throw new \InvalidArgumentException('invoice_payment_evidence_invalid');
    }
    $now = $this->time->getRequestTime();
    $transaction = $this->database->startTransaction();
    try {
      $row = $this->lockedInvoice($publicId);
      self::assertTenantBinding($row, $customerId, $organizationId, $requestId);
      $this->assertSnapshotIntegrity($row);
      if ((int) ($row['commerce_order_id'] ?? 0) !== $orderId
        || (int) $row['total_amount_minor'] !== $amountMinor
        || !hash_equals(strtolower((string) $row['currency']), strtolower(trim($currency)))) {
        throw new \RuntimeException('invoice_payment_mismatch');
      }
      if ((string) $row['status'] === 'paid') {
        return ['invoice' => $this->hydrate($row), 'transitioned' => FALSE];
      }
      if ((string) $row['status'] !== 'payment_pending') {
        throw new \RuntimeException('invoice_payment_state_invalid');
      }
      $eventMaterial = strtolower($provider) . '|' . $providerEventId . '|' . $row['public_id'];
      $eventKey = 'invoice:paid:' . hash('sha256', $eventMaterial);
      $this->appendEvent((int) $row['id'], 'invoice.payment_verified', $eventKey, [
        'order_id' => $orderId,
        'amount_minor' => $amountMinor,
        'currency' => strtolower(trim($currency)),
        'snapshot_sha256' => (string) $row['snapshot_sha256'],
      ], 0, $provider, $providerEventId);
      $this->database->update('famtastic_customer_invoice')->fields([
        'status' => 'paid',
        'paid_at' => $now,
        'changed' => $now,
      ])
        ->condition('id', (int) $row['id'])->condition('status', 'payment_pending')->execute();
      $this->database->update('famtastic_owner_hosting_handoff')->fields([
        'status' => 'awaiting_host_access',
        'access_unlocked' => 1,
        'access_status' => 'awaiting_owner',
        'current_blocker' => 'Owner hosting access required',
        'owner_next_action' => 'Provide a temporary least-privilege hosting invitation and domain path',
        'changed' => $now,
      ])->condition('invoice_id', (int) $row['id'])->condition('status', 'payment_required')->execute();
      return ['invoice' => $this->hydrate($this->loadById((int) $row['id'])), 'transitioned' => TRUE];
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
  }

  /**
   * Records a staff-verified offline invoice receipt without inventing Stripe evidence.
   *
   * The caller must first create and reconcile one completed manual Commerce
   * payment for the exact order. This transition records the owner attestation,
   * unlocks the contracted hosting work, and deliberately does not infer terms
   * acceptance, staging acceptance, inbox delivery, DNS authority, or launch.
   */
  public function recordOwnerConfirmedOfflinePayment(
    string $publicId,
    int $customerId,
    int $organizationId,
    int $requestId,
    int $orderId,
    int $amountMinor,
    string $currency,
    string $provider,
    string $evidenceId,
    int $staffUid,
    string $actor,
    string $authority,
  ): array {
    $provider = strtolower(trim($provider));
    $evidenceId = strtolower(trim($evidenceId));
    $currency = strtolower(trim($currency));
    $actor = trim($actor);
    $authority = trim($authority);
    if ($orderId <= 0
      || !hash_equals(self::OFFLINE_PAYMENT_PROVIDER, $provider)
      || preg_match('/^[a-f0-9]{64}$/', $evidenceId) !== 1
      || $staffUid <= 0 || $actor === '' || $authority === ''
      || mb_strlen($actor) > 255 || mb_strlen($authority) > 1000) {
      throw new \InvalidArgumentException('invoice_offline_payment_evidence_invalid');
    }

    $now = $this->time->getRequestTime();
    $transaction = $this->database->startTransaction();
    try {
      $row = $this->lockedInvoice($publicId);
      self::assertTenantBinding($row, $customerId, $organizationId, $requestId);
      $this->assertSnapshotIntegrity($row);
      $this->assertVerifiedOwner($customerId, $organizationId);
      if ((int) $row['total_amount_minor'] !== $amountMinor
        || !hash_equals(strtolower((string) $row['currency']), $currency)) {
        throw new \RuntimeException('invoice_offline_payment_amount_mismatch');
      }

      $existingOrderId = (int) ($row['commerce_order_id'] ?? 0);
      if ((string) $row['status'] === 'paid') {
        $eventExists = (bool) $this->database->select('famtastic_customer_invoice_event', 'e')
          ->condition('invoice_id', (int) $row['id'])
          ->condition('event_type', 'invoice.offline_payment_owner_confirmed')
          ->condition('provider', self::OFFLINE_PAYMENT_PROVIDER)
          ->condition('provider_event_id', $evidenceId)
          ->countQuery()->execute()->fetchField();
        if ($existingOrderId !== $orderId || !$eventExists) {
          throw new \RuntimeException('invoice_offline_payment_replay_conflict');
        }
        return ['invoice' => $this->hydrate($row), 'transitioned' => FALSE];
      }
      if (!in_array((string) $row['status'], ['issued', 'viewed', 'accepted'], TRUE)
        || $existingOrderId > 0) {
        throw new \RuntimeException('invoice_offline_payment_state_invalid');
      }

      $eventKey = 'invoice:offline-paid:' . hash('sha256', $provider . '|' . $evidenceId . '|' . $row['public_id']);
      $this->appendEvent((int) $row['id'], 'invoice.offline_payment_owner_confirmed', $eventKey, [
        'order_id' => $orderId,
        'amount_minor' => $amountMinor,
        'currency' => $currency,
        'snapshot_sha256' => (string) $row['snapshot_sha256'],
        'evidence_state' => 'owner_attested_received',
        'actor' => $actor,
        'authority' => $authority,
        'terms_accepted' => FALSE,
        'staging_acceptance_inferred' => FALSE,
        'bank_transaction_reference_recorded' => FALSE,
        'payment_time_meaning' => 'owner_recorded_at_not_bank_settlement_timestamp',
        'customer_receipt_sent' => FALSE,
      ], $staffUid, $provider, $evidenceId);
      $invoiceUpdated = $this->database->update('famtastic_customer_invoice')->fields([
        'commerce_order_id' => $orderId,
        'status' => 'paid',
        'paid_at' => $now,
        'changed' => $now,
      ])->condition('id', (int) $row['id'])->condition('status', (string) $row['status'])->execute();
      $offerUpdated = $this->database->update('famtastic_private_offer')->fields([
        'status' => 'prepaid_held',
        'commerce_order_id' => $orderId,
        'changed' => $now,
      ])->condition('id', (int) $row['private_offer_id'])->condition('status', 'active')->execute();
      $handoffUpdated = $this->database->update('famtastic_owner_hosting_handoff')->fields([
        'commerce_order_id' => $orderId,
        'status' => 'awaiting_host_access',
        'access_unlocked' => 1,
        'access_status' => 'awaiting_owner',
        'current_blocker' => 'Owner hosting access required',
        'owner_next_action' => 'Provide a temporary least-privilege hosting invitation and domain path',
        'changed' => $now,
      ])->condition('invoice_id', (int) $row['id'])->condition('status', 'payment_required')->execute();
      if ($invoiceUpdated !== 1 || $offerUpdated !== 1 || $handoffUpdated !== 1) {
        throw new \RuntimeException('invoice_offline_payment_transition_conflict');
      }
      return ['invoice' => $this->hydrate($this->loadById((int) $row['id'])), 'transitioned' => TRUE];
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
  }

  /**
   * Reconciles terminal void/refund evidence without changing the snapshot. */
  public function reconcile(
    string $publicId,
    int $customerId,
    int $organizationId,
    int $requestId,
    int $orderId,
    string $status,
    string $provider,
    string $providerEventId,
  ): array {
    $status = strtolower(trim($status));
    $provider = trim($provider);
    $providerEventId = trim($providerEventId);
    if (!in_array($status, ['void', 'refunded'], TRUE)
      || !hash_equals(self::PAYMENT_GATEWAY, $provider) || $providerEventId === '') {
      throw new \InvalidArgumentException('invoice_reconciliation_input_invalid');
    }
    $now = $this->time->getRequestTime();
    $transaction = $this->database->startTransaction();
    try {
      $row = $this->lockedInvoice($publicId);
      self::assertTenantBinding($row, $customerId, $organizationId, $requestId);
      $this->assertSnapshotIntegrity($row);
      $linkedOrder = (int) ($row['commerce_order_id'] ?? 0);
      if ($linkedOrder !== $orderId || ($status === 'refunded' && $orderId <= 0)) {
        throw new \RuntimeException('invoice_reconciliation_order_mismatch');
      }
      if ((string) $row['status'] === $status) {
        return ['invoice' => $this->hydrate($row), 'transitioned' => FALSE];
      }
      $voidable = ['issued', 'viewed', 'accepted', 'payment_pending'];
      if (($status === 'refunded' && (string) $row['status'] !== 'paid')
        || ($status === 'void' && !in_array((string) $row['status'], $voidable, TRUE))) {
        throw new \RuntimeException('invoice_reconciliation_state_invalid');
      }
      $eventMaterial = strtolower($provider) . '|' . $providerEventId . '|' . $row['public_id'];
      $eventKey = 'invoice:' . $status . ':' . hash('sha256', $eventMaterial);
      $this->appendEvent(
        (int) $row['id'],
        'invoice.' . $status,
        $eventKey,
        ['order_id' => $orderId, 'previous_status' => $row['status']],
        0,
        $provider,
        $providerEventId,
      );
      $fields = ['status' => $status, 'active_request_key' => NULL, 'changed' => $now];
      if ($status === 'refunded') {
        $fields['refunded_at'] = $now;
      }
      $this->database->update('famtastic_customer_invoice')->fields($fields)->condition('id', (int) $row['id'])->execute();
      $this->database->update('famtastic_private_offer')->fields(['status' => $status, 'changed' => $now])
        ->condition('id', (int) $row['private_offer_id'])->execute();
      $this->database->update('famtastic_owner_hosting_handoff')->fields([
        'status' => 'blocked',
        'access_unlocked' => 0,
        'access_status' => 'locked',
        'current_blocker' => $status === 'refunded' ? 'Invoice payment was refunded' : 'Invoice was voided',
        'owner_next_action' => 'Contact FAMtastic to review the invoice status',
        'changed' => $now,
      ])->condition('invoice_id', (int) $row['id'])->execute();
      return ['invoice' => $this->hydrate($this->loadById((int) $row['id'])), 'transitioned' => TRUE];
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
  }

  /**
   * Saves an owner's non-secret hosting handoff declaration after payment.
   *
   * This records contact and routing facts only. It cannot verify access,
   * hosting compatibility, DNS control, Stripe ownership, or deployment.
   */
  public function saveOwnerHostingDetails(
    string $invoicePublicId,
    int $customerId,
    int $organizationId,
    int $requestId,
    array $input,
  ): array {
    $provider = trim((string) ($input['provider'] ?? ''));
    $controlPanelUrl = trim((string) ($input['control_panel_url'] ?? ''));
    $accessMethod = trim((string) ($input['access_method'] ?? ''));
    $productionDomain = strtolower(trim((string) ($input['production_domain'] ?? '')));
    $dnsPath = trim((string) ($input['dns_path'] ?? 'undecided'));
    $stripeStatus = trim((string) ($input['client_stripe_status'] ?? 'not_started'));
    $note = trim((string) ($input['note'] ?? ''));
    $invitationSent = filter_var($input['invitation_sent'] ?? FALSE, FILTER_VALIDATE_BOOL);

    $combined = implode("\n", [
      $provider, $controlPanelUrl, $accessMethod, $productionDomain,
      $dnsPath, $stripeStatus, $note,
    ]);
    $secretPattern = '/(?:password|passcode|api[ _-]?key|secret|token|private[ _-]?key|'
      . 'recovery[ _-]?code|card[ _-]?number|cvv|cvc|sk_(?:live|test)|'
      . 'rk_(?:live|test)|-----BEGIN)/i';
    if (preg_match($secretPattern, $combined)) {
      throw new \InvalidArgumentException('hosting_secrets_forbidden');
    }
    if ($provider === '' || mb_strlen($provider) > 120
      || mb_strlen($controlPanelUrl) > 2048
      || !in_array($accessMethod, ['provider_invitation', 'cpanel', 'sftp_ssh', 'other'], TRUE)
      || !in_array($dnsPath, ['delegation', 'registrar_access', 'transfer', 'undecided'], TRUE)
      || !in_array($stripeStatus, ['not_started', 'in_progress', 'owner_account_ready', 'ready_for_controlled_test'], TRUE)
      || mb_strlen($note) > 2000) {
      throw new \InvalidArgumentException('hosting_details_invalid');
    }
    $parts = parse_url($controlPanelUrl);
    if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
      || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
      throw new \InvalidArgumentException('hosting_control_panel_url_invalid');
    }
    $productionDomain = preg_replace('#^https?://#i', '', $productionDomain) ?? '';
    $productionDomain = rtrim($productionDomain, '/');
    if (str_contains($productionDomain, '/') || preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $productionDomain) !== 1) {
      throw new \InvalidArgumentException('hosting_production_domain_invalid');
    }

    $now = $this->time->getRequestTime();
    $transaction = $this->database->startTransaction();
    try {
      $invoice = $this->lockedInvoice($invoicePublicId);
      self::assertTenantBinding($invoice, $customerId, $organizationId, $requestId);
      $this->assertSnapshotIntegrity($invoice);
      if ((string) $invoice['status'] !== 'paid') {
        throw new \RuntimeException('hosting_payment_required');
      }
      $handoff = $this->database->select('famtastic_owner_hosting_handoff', 'h')->fields('h')
        ->condition('invoice_id', (int) $invoice['id'])->forUpdate()->execute()->fetchAssoc();
      if (!$handoff || empty($handoff['access_unlocked'])) {
        throw new \RuntimeException('hosting_access_locked');
      }

      $declaration = [
        'schema' => 'famtastic.owner-hosting-declaration.v1',
        'evidence_state' => 'owner_declared',
        'invitation_sent' => $invitationSent,
        'note' => $note,
        'declared_at' => $now,
        'declared_by_customer_id' => $customerId,
      ];
      $status = 'awaiting_host_access';
      $accessStatus = $invitationSent ? 'owner_declared' : 'awaiting_owner';
      $blocker = $invitationSent
        ? 'FAMtastic must verify the hosting invitation and audit the destination'
        : 'Owner hosting access invitation required';
      $nextAction = $invitationSent
        ? 'Wait for Shay to verify access and publish the hosting-audit finding'
        : 'Send a temporary least-privilege hosting invitation';
      $this->database->update('famtastic_owner_hosting_handoff')->fields([
        'status' => $status,
        'provider_name' => $provider,
        'control_panel_url' => $controlPanelUrl,
        'access_method' => $accessMethod,
        'access_status' => $accessStatus,
        'production_domain' => $productionDomain,
        'dns_path' => $dnsPath,
        'client_stripe_status' => $stripeStatus,
        'current_blocker' => $blocker,
        'owner_next_action' => $nextAction,
        'evidence_json' => self::canonicalJson($declaration),
        'changed' => $now,
      ])->condition('id', (int) $handoff['id'])->condition('invoice_id', (int) $invoice['id'])->execute();
      $eventPayload = [
        'handoff_public_id' => (string) $handoff['public_id'],
        'provider' => $provider,
        'control_panel_host' => strtolower((string) $parts['host']),
        'access_method' => $accessMethod,
        'invitation_sent' => $invitationSent,
        'production_domain' => $productionDomain,
        'dns_path' => $dnsPath,
        'client_stripe_status' => $stripeStatus,
        'evidence_state' => 'owner_declared',
      ];
      $eventKey = 'invoice:hosting-owner-declared:' . hash('sha256', self::canonicalJson($eventPayload));
      $this->appendEvent((int) $invoice['id'], 'hosting.owner_declared', $eventKey, $eventPayload, $customerId);
      return ['invoice' => $this->hydrate($this->loadById((int) $invoice['id'])), 'transitioned' => TRUE];
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      throw $error;
    }
  }

  /**
   * Ensures the customer is a verified owner of the active organization.
   */
  private function assertVerifiedOwner(int $customerId, int $organizationId): void {
    $customer = $this->database->select('famtastic_customer', 'c')->fields('c', ['id', 'verified_at'])
      ->condition('id', $customerId)->execute()->fetchAssoc();
    $organization = $this->database->select('famtastic_organization', 'o')->fields('o', ['id', 'status'])
      ->condition('id', $organizationId)->execute()->fetchAssoc();
    $membership = $this->database->select('famtastic_membership', 'm')->fields('m', ['role', 'status'])
      ->condition('customer_id', $customerId)->condition('organization_id', $organizationId)->execute()->fetchAssoc();
    if (!$customer || empty($customer['verified_at']) || !$organization || $organization['status'] !== 'active'
      || !$membership || $membership['status'] !== 'active' || $membership['role'] !== 'owner') {
      throw new \RuntimeException('invoice_verified_owner_required');
    }
  }

  /**
   * Loads and locks one invoice by its public identifier.
   */
  private function lockedInvoice(string $publicId): array {
    $row = $this->database->select('famtastic_customer_invoice', 'i')->fields('i')
      ->condition('public_id', strtolower(trim($publicId)))->forUpdate()->execute()->fetchAssoc();
    if (!$row) {
      throw new \RuntimeException('invoice_not_found');
    }
    return $row;
  }

  /**
   * Loads one invoice by its internal identifier.
   */
  private function loadById(int $invoiceId): array {
    $row = $this->database->select('famtastic_customer_invoice', 'i')->fields('i')->condition('id', $invoiceId)->execute()->fetchAssoc();
    if (!$row) {
      throw new \RuntimeException('invoice_not_found');
    }
    return $row;
  }

  /**
   * Decodes a verified invoice and attaches its owner-hosting state.
   */
  private function hydrate(array $row): array {
    $this->assertSnapshotIntegrity($row);
    $integerFields = [
      'id', 'website_request_id', 'organization_id', 'customer_id',
      'private_offer_id', 'commerce_order_id', 'list_amount_minor',
      'credit_amount_minor', 'total_amount_minor', 'created_by_uid',
      'issued_at', 'due_at', 'viewed_at', 'accepted_at', 'paid_at',
      'refunded_at', 'created', 'changed',
    ];
    foreach ($integerFields as $field) {
      $row[$field] = isset($row[$field]) ? (int) $row[$field] : NULL;
    }
    $row['line_items'] = json_decode((string) $row['line_items_json'], TRUE, 512, JSON_THROW_ON_ERROR);
    $row['scope_snapshot'] = json_decode((string) $row['scope_snapshot_json'], TRUE, 512, JSON_THROW_ON_ERROR);
    $row['terms'] = json_decode((string) $row['terms_snapshot_json'], TRUE, 512, JSON_THROW_ON_ERROR);
    $row['website_request_public_id'] = (string) ($this->database->select('famtastic_project_request', 'r')
      ->fields('r', ['public_id'])->condition('id', (int) $row['website_request_id'])->execute()->fetchField() ?: '');
    unset($row['line_items_json'], $row['scope_snapshot_json'], $row['terms_snapshot_json'], $row['active_request_key']);
    $handoff = $this->database->select('famtastic_owner_hosting_handoff', 'h')->fields('h')
      ->condition('invoice_id', (int) $row['id'])->range(0, 1)->execute()->fetchAssoc();
    if ($handoff) {
      $handoffIntegerFields = [
        'id', 'invoice_id', 'website_request_id', 'organization_id',
        'customer_id', 'commerce_order_id', 'access_unlocked', 'created', 'changed',
      ];
      foreach ($handoffIntegerFields as $field) {
        $handoff[$field] = isset($handoff[$field]) ? (int) $handoff[$field] : NULL;
      }
      $handoff['evidence'] = json_decode((string) $handoff['evidence_json'], TRUE, 512, JSON_THROW_ON_ERROR);
      unset($handoff['evidence_json']);
    }
    $row['owner_hosting'] = $handoff ?: NULL;
    return $row;
  }

  /**
   * Recomputes and verifies the immutable invoice snapshot checksum.
   */
  private function assertSnapshotIntegrity(array $row): void {
    $definition = [
      'sku' => (string) $row['sku'],
      'currency' => (string) $row['currency'],
      'list_amount_minor' => (int) $row['list_amount_minor'],
      'credit_amount_minor' => (int) $row['credit_amount_minor'],
      'total_amount_minor' => (int) $row['total_amount_minor'],
      'line_items' => json_decode((string) $row['line_items_json'], TRUE, 512, JSON_THROW_ON_ERROR),
      'scope_snapshot' => json_decode((string) $row['scope_snapshot_json'], TRUE, 512, JSON_THROW_ON_ERROR),
      'terms' => json_decode((string) $row['terms_snapshot_json'], TRUE, 512, JSON_THROW_ON_ERROR),
    ];
    $definition = self::validateDefinition($definition);
    $expected = hash('sha256', self::canonicalJson($this->snapshotPayload(
      (string) $row['invoice_number'],
      (int) $row['website_request_id'],
      (int) $row['customer_id'],
      (int) $row['organization_id'],
      $definition,
      $definition['scope_snapshot'],
      (string) $row['staging_receipt_hash'],
    )));
    if (!hash_equals((string) $row['snapshot_sha256'], $expected)) {
      throw new \RuntimeException('invoice_snapshot_integrity_failed');
    }
  }

  /**
   * Builds the complete immutable snapshot payload.
   */
  private function snapshotPayload(string $invoiceNumber, int $requestId, int $customerId, int $organizationId, array $definition, array $scope, string $stagingHash): array {
    return [
      'schema' => 'famtastic.customer-invoice-snapshot.v1',
      'invoice_number' => $invoiceNumber,
      'website_request_id' => $requestId,
      'customer_id' => $customerId,
      'organization_id' => $organizationId,
      'sku' => $definition['sku'],
      'currency' => $definition['currency'],
      'list_amount_minor' => $definition['list_amount_minor'],
      'credit_amount_minor' => $definition['credit_amount_minor'],
      'total_amount_minor' => $definition['total_amount_minor'],
      'line_items' => $definition['line_items'],
      'scope_snapshot' => $scope,
      'terms' => $definition['terms'],
      'staging_receipt_hash' => $stagingHash,
    ];
  }

  /**
   * Appends one replay-safe invoice lifecycle event.
   */
  private function appendEvent(int $invoiceId, string $eventType, string $idempotencyKey, array $payload, int $actorUid = 0, string $provider = '', string $providerEventId = ''): void {
    $existing = $this->database->select('famtastic_customer_invoice_event', 'e')->fields('e')
      ->condition('idempotency_key', $idempotencyKey)->execute()->fetchAssoc();
    $encoded = self::canonicalJson($payload);
    if ($existing) {
      if ((int) $existing['invoice_id'] !== $invoiceId || (string) $existing['event_type'] !== $eventType || !hash_equals((string) $existing['payload_json'], $encoded)) {
        throw new \RuntimeException('invoice_idempotency_conflict');
      }
      return;
    }
    $this->database->insert('famtastic_customer_invoice_event')->fields([
      'invoice_id' => $invoiceId,
      'event_type' => $eventType,
      'idempotency_key' => $idempotencyKey,
      'actor_uid' => $actorUid,
      'provider' => $provider,
      'provider_event_id' => $providerEventId,
      'payload_json' => $encoded,
      'created' => $this->time->getRequestTime(),
    ])->execute();
  }

  /**
   * Encodes a recursively normalized JSON value.
   */
  private static function canonicalJson(array $value): string {
    return json_encode(self::canonicalize($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  }

  /**
   * Sorts associative keys while preserving list order.
   */
  private static function canonicalize(array $value): array {
    $isList = array_is_list($value);
    if (!$isList) {
      ksort($value, SORT_STRING);
    }
    foreach ($value as $key => $item) {
      if (is_array($item)) {
        $value[$key] = self::canonicalize($item);
      }
    }
    return $value;
  }

  /**
   * Builds one normalized invoice line.
   */
  private static function line(string $code, string $label, int $amountMinor = 0, string $kind = 'included'): array {
    return [
      'code' => $code,
      'label' => $label,
      'amount_minor' => $amountMinor,
      'kind' => $kind,
    ];
  }

}
