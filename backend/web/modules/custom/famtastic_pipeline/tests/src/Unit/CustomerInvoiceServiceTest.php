<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\Php as Uuid;
use Drupal\famtastic_pipeline\Service\CustomerInvoiceService;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';
require_once dirname(__DIR__, 3) . '/src/Service/CustomerInvoiceService.php';

/**
 * SQLite proof for immutable, account-scoped owner-hosted invoices.
 */
#[\PHPUnit\Framework\Attributes\Group('famtastic_pipeline')]
final class CustomerInvoiceServiceTest extends UnitTestCase {

  private const REQUEST_PUBLIC_ID = '11111111-2222-4333-8444-555555555555';
  private const STAGING_HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

  /**
   * Isolated database fixture.
   */
  private Connection $database;

  /**
   * Service under test.
   */
  private CustomerInvoiceService $invoices;

  /**
   * Creates an exact staged request and verified owner fixture.
   */
  protected function setUp(): void {
    parent::setUp();
    $options = [
      'database' => ':memory:',
      'prefix' => '',
      'driver' => 'sqlite',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ];
    $this->database = new Connection(Connection::open($options), $options);
    $schema = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_customer_invoice_schema();
    $tables = [
      'famtastic_customer', 'famtastic_organization', 'famtastic_membership',
      'famtastic_project_request', 'famtastic_private_offer',
      'famtastic_customer_invoice', 'famtastic_customer_invoice_event',
      'famtastic_owner_hosting_handoff',
    ];
    foreach ($tables as $table) {
      $this->database->schema()->createTable($table, $schema[$table]);
    }
    $this->database->insert('famtastic_customer')->fields([
      'id' => 13,
      'public_id' => 'customer-kofi',
      'uid' => 113,
      'display_name' => 'Kofi A. Oliver',
      'email' => 'kofi@example.test',
      'verified_at' => 1699999999,
      'created' => 1,
      'changed' => 1,
    ])->execute();
    $this->database->insert('famtastic_organization')->fields([
      'id' => 13,
      'public_id' => 'organization-reckoning',
      'type' => 'business',
      'name' => 'The Reckoning',
      'status' => 'active',
      'created' => 1,
      'changed' => 1,
    ])->execute();
    $this->database->insert('famtastic_membership')->fields([
      'organization_id' => 13,
      'customer_id' => 13,
      'role' => 'owner',
      'status' => 'active',
      'created' => 1,
      'changed' => 1,
    ])->execute();
    $this->database->insert('famtastic_project_request')->fields([
      'id' => 15,
      'public_id' => self::REQUEST_PUBLIC_ID,
      'organization_id' => 13,
      'customer_id' => 13,
      'project_name' => 'The Reckoning',
      'business_name' => 'The Reckoning',
      'status' => 'submitted',
      'proof_review_status' => 'selected',
      'staging_status' => 'deployed',
      'staging_review_status' => 'pending',
      'staging_receipt_hash' => self::STAGING_HASH,
      'intake_data' => '{}',
      'created' => 1,
      'changed' => 1,
    ])->execute();
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1700000000);
    $this->invoices = new CustomerInvoiceService($this->database, $time, new Uuid());
  }

  /**
   * Proves the itemized invoice always resolves to exactly $100.00.
   */
  public function testExactCentDefinitionRejectsArithmeticAndLineDrift(): void {
    $definition = CustomerInvoiceService::validateDefinition(CustomerInvoiceService::ownerHostedDefinition());
    self::assertSame(49900, $definition['list_amount_minor']);
    self::assertSame(39900, $definition['credit_amount_minor']);
    self::assertSame(10000, $definition['total_amount_minor']);
    self::assertSame(10000, array_sum(array_column($definition['line_items'], 'amount_minor')));

    foreach ([
      array_replace($definition, ['total_amount_minor' => 9999]),
      array_replace($definition, ['line_items' => array_replace($definition['line_items'], [0 => array_replace($definition['line_items'][0], ['amount_minor' => 49899])])]),
    ] as $invalid) {
      try {
        CustomerInvoiceService::validateDefinition($invalid);
        self::fail('Invoice arithmetic drift was accepted.');
      }
      catch (\InvalidArgumentException $error) {
        self::assertStringStartsWith('invoice_', $error->getMessage());
      }
    }
  }

  /**
   * Proves scope, active-request uniqueness, replay, and immutability.
   */
  public function testIssueIsScopedImmutableAndReplaySafe(): void {
    $first = $this->issue();
    $again = $this->issue();
    self::assertSame($first['id'], $again['id']);
    self::assertSame('TR-KAO-001', $first['invoice_number']);
    self::assertSame(self::REQUEST_PUBLIC_ID, $first['website_request_public_id']);
    self::assertSame('issued', $first['status']);
    self::assertSame('payment_required', $first['owner_hosting']['status']);
    self::assertSame(0, $first['owner_hosting']['access_unlocked']);
    self::assertSame(1, $this->countRows('famtastic_customer_invoice'));
    self::assertSame(1, $this->countRows('famtastic_private_offer'));
    self::assertSame(1, $this->countRows('famtastic_customer_invoice_event'));

    try {
      $this->invoices->issueOwnerHostedInvoice(self::REQUEST_PUBLIC_ID, 13, 13, 'TR-KAO-002', self::STAGING_HASH, 1, 'Shay', 'Different invoice attempt');
      self::fail('A second active invoice was accepted.');
    }
    catch (\RuntimeException $error) {
      self::assertSame('invoice_active_conflict', $error->getMessage());
    }
    try {
      $this->invoices->invoiceByPublicId($first['public_id'], 99, 13, 15);
      self::fail('Cross-account invoice access was accepted.');
    }
    catch (\RuntimeException $error) {
      self::assertSame('invoice_scope_mismatch', $error->getMessage());
    }

    $scope = $first['scope_snapshot'];
    $scope['package_name'] = 'Tampered after issuance';
    $this->database->update('famtastic_customer_invoice')->fields([
      'scope_snapshot_json' => json_encode($scope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    ])->condition('id', $first['id'])->execute();
    $this->expectExceptionMessage('invoice_snapshot_integrity_failed');
    $this->invoices->invoiceByPublicId($first['public_id'], 13, 13, 15);
  }

  /**
   * Proves trusted payment and refund transitions remain replay-safe.
   */
  public function testVerifiedPaymentActivationAndRefundAreIdempotent(): void {
    $invoice = $this->issue();
    $linked = $this->invoices->linkCommerceOrder($invoice['public_id'], 13, 13, 15, 501, CustomerInvoiceService::TERMS_VERSION, 'invoice-checkout:tr-kao-001');
    self::assertTrue($linked['transitioned']);
    self::assertSame('payment_pending', $linked['invoice']['status']);
    self::assertFalse($this->invoices->linkCommerceOrder($invoice['public_id'], 13, 13, 15, 501, CustomerInvoiceService::TERMS_VERSION, 'invoice-checkout:tr-kao-001')['transitioned']);

    try {
      $this->invoices->activatePaid($invoice['public_id'], 13, 13, 15, 501, 9999, 'usd', CustomerInvoiceService::PAYMENT_GATEWAY, 'evt_wrong');
      self::fail('Mismatched provider amount was accepted.');
    }
    catch (\RuntimeException $error) {
      self::assertSame('invoice_payment_mismatch', $error->getMessage());
    }
    try {
      $this->invoices->activatePaid($invoice['public_id'], 13, 13, 15, 501, 10000, 'usd', 'manual', 'evt_wrong_provider');
      self::fail('A non-Stripe payment provider was accepted.');
    }
    catch (\InvalidArgumentException $error) {
      self::assertSame('invoice_payment_evidence_invalid', $error->getMessage());
    }
    $paid = $this->invoices->activatePaid($invoice['public_id'], 13, 13, 15, 501, 10000, 'USD', CustomerInvoiceService::PAYMENT_GATEWAY, 'evt_paid_once');
    self::assertTrue($paid['transitioned']);
    self::assertSame('paid', $paid['invoice']['status']);
    self::assertSame('awaiting_host_access', $paid['invoice']['owner_hosting']['status']);
    self::assertSame(1, $paid['invoice']['owner_hosting']['access_unlocked']);
    self::assertFalse($this->invoices->activatePaid($invoice['public_id'], 13, 13, 15, 501, 10000, 'usd', CustomerInvoiceService::PAYMENT_GATEWAY, 'evt_paid_once')['transitioned']);

    $refunded = $this->invoices->reconcile($invoice['public_id'], 13, 13, 15, 501, 'refunded', CustomerInvoiceService::PAYMENT_GATEWAY, 'evt_refund_once');
    self::assertTrue($refunded['transitioned']);
    self::assertSame('refunded', $refunded['invoice']['status']);
    self::assertSame('blocked', $refunded['invoice']['owner_hosting']['status']);
    self::assertFalse($this->invoices->reconcile($invoice['public_id'], 13, 13, 15, 501, 'refunded', CustomerInvoiceService::PAYMENT_GATEWAY, 'evt_refund_once')['transitioned']);
    self::assertSame(4, $this->countRows('famtastic_customer_invoice_event'));
  }

  /**
   * Proves hosting declarations stay locked until payment and reject secrets.
   */
  public function testOwnerHostingDeclarationIsPaymentGatedAndSecretFree(): void {
    $invoice = $this->issue();
    $details = [
      'provider' => 'Example Host',
      'control_panel_url' => 'https://panel.example.test/',
      'access_method' => 'provider_invitation',
      'invitation_sent' => TRUE,
      'production_domain' => 'thereckoning.example',
      'dns_path' => 'delegation',
      'client_stripe_status' => 'owner_account_ready',
      'note' => 'Invitation sent to the approved FAMtastic support identity.',
    ];
    try {
      $this->invoices->saveOwnerHostingDetails($invoice['public_id'], 13, 13, 15, $details);
      self::fail('Unpaid hosting access was accepted.');
    }
    catch (\RuntimeException $error) {
      self::assertSame('hosting_payment_required', $error->getMessage());
    }
    $this->invoices->linkCommerceOrder(
      $invoice['public_id'],
      13,
      13,
      15,
      501,
      CustomerInvoiceService::TERMS_VERSION,
      'invoice-checkout:hosting-details',
    );
    $this->invoices->activatePaid(
      $invoice['public_id'],
      13,
      13,
      15,
      501,
      10000,
      'usd',
      CustomerInvoiceService::PAYMENT_GATEWAY,
      'evt_hosting_paid',
    );
    try {
      $this->invoices->saveOwnerHostingDetails(
        $invoice['public_id'],
        13,
        13,
        15,
        array_replace($details, ['note' => 'Password: do-not-store']),
      );
      self::fail('A hosting secret was accepted.');
    }
    catch (\InvalidArgumentException $error) {
      self::assertSame('hosting_secrets_forbidden', $error->getMessage());
    }
    $saved = $this->invoices->saveOwnerHostingDetails($invoice['public_id'], 13, 13, 15, $details);
    self::assertSame('awaiting_host_access', $saved['invoice']['owner_hosting']['status']);
    self::assertSame('owner_declared', $saved['invoice']['owner_hosting']['access_status']);
    self::assertSame('owner_declared', $saved['invoice']['owner_hosting']['evidence']['evidence_state']);
    self::assertArrayNotHasKey('password', $saved['invoice']['owner_hosting']['evidence']);
    self::assertSame(4, $this->countRows('famtastic_customer_invoice_event'));
  }

  /**
   * Rejects the wrong tenant, receipt hash, or unverified customer.
   */
  public function testIssueRequiresExactVerifiedOwnerAndStagingReceipt(): void {
    foreach ([
      [99, 13, self::STAGING_HASH, 'invoice_scope_mismatch'],
      [13, 99, self::STAGING_HASH, 'invoice_scope_mismatch'],
      [13, 13, str_repeat('b', 64), 'invoice_staging_receipt_mismatch'],
    ] as [$customerId, $organizationId, $hash, $message]) {
      try {
        $this->invoices->issueOwnerHostedInvoice(self::REQUEST_PUBLIC_ID, $customerId, $organizationId, 'TR-KAO-001', $hash, 1, 'Shay', 'Approved plan');
        self::fail('Invalid tenant or staging evidence was accepted.');
      }
      catch (\RuntimeException $error) {
        self::assertSame($message, $error->getMessage());
      }
    }
    $this->database->update('famtastic_customer')->fields(['verified_at' => NULL])->condition('id', 13)->execute();
    $this->expectExceptionMessage('invoice_verified_owner_required');
    $this->issue();
  }

  /**
   * Verifies the database-level uniqueness and event-ledger contracts.
   */
  public function testSchemaHasOneActiveRequestGuardAndReplayLedger(): void {
    $schema = _famtastic_pipeline_customer_invoice_schema();
    self::assertSame(['active_request_key'], $schema['famtastic_customer_invoice']['unique keys']['active_request']);
    self::assertSame(['idempotency_key'], $schema['famtastic_customer_invoice_event']['unique keys']['idempotency_key']);
    self::assertSame(['invoice_id'], $schema['famtastic_owner_hosting_handoff']['unique keys']['invoice']);
    self::assertTrue(function_exists('famtastic_pipeline_update_8066'));
  }

  /**
   * Issues the canonical fixture invoice.
   */
  private function issue(): array {
    return $this->invoices->issueOwnerHostedInvoice(
      self::REQUEST_PUBLIC_ID,
      13,
      13,
      'TR-KAO-001',
      self::STAGING_HASH,
      1,
      'Shay',
      'Approved The Reckoning invoice-to-launch plan',
    );
  }

  /**
   * Counts rows in one isolated fixture table.
   */
  private function countRows(string $table): int {
    return (int) $this->database->select($table, 't')->countQuery()->execute()->fetchField();
  }

}
