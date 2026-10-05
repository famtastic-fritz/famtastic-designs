<?php
declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\famtastic_pipeline\Service\AcquisitionCampaignReportService;
use Drupal\famtastic_pipeline\Service\AcquisitionSampleSchema;
use Drupal\famtastic_pipeline\Service\AcquisitionSampleService;
use Drupal\famtastic_pipeline\Service\OperationalLedger;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';

/** Actual SQLite report query/mapping and synthetic Commerce entity receipts. */
final class AcquisitionCampaignReportServiceTest extends UnitTestCase {
  public function testExactVerifiedContextConnectsDistinctInterviewProspectToCurrentPayment(): void {
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $db = new Connection(Connection::open($options), $options);
    $schemas = AcquisitionSampleSchema::tables() + _famtastic_pipeline_automation_schema() + _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_lifecycle_schema();
    foreach (['famtastic_acquisition_sample', 'famtastic_acquisition_sequence', 'famtastic_acquisition_message', 'famtastic_acquisition_request', 'famtastic_customer', 'famtastic_consent', 'famtastic_event', 'famtastic_campaign', 'famtastic_email_message', 'famtastic_project_request', 'famtastic_commerce_fulfillment'] as $table) $db->schema()->createTable($table, $schemas[$table]);
    $db->query('CREATE TABLE famtastic_prospect (id INTEGER PRIMARY KEY, public_email TEXT, campaign TEXT)');
    $db->query("INSERT INTO famtastic_prospect VALUES (1,'controlled@example.test','controlled-acquisition'),(20,'controlled@example.test','customer_portal'),(30,'controlled@example.test','customer_portal')");
    $db->insert('famtastic_campaign')->fields(['id' => 1, 'campaign_key' => 'controlled-acquisition', 'name' => 'Synthetic controlled test', 'status' => 'draft', 'created' => 1, 'changed' => 1])->execute();
    $db->insert('famtastic_customer')->fields(['id' => 70, 'public_id' => 'controlled-customer', 'uid' => 70, 'prospect_id' => 1, 'display_name' => 'Fictional customer', 'email' => 'controlled@example.test', 'verified_at' => 1800000000, 'created' => 1, 'changed' => 1])->execute();
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1800000000);
    $ledger = new OperationalLedger($db, $time);
    $samples = new AcquisitionSampleService($db, $time, $ledger);
    $recipes = array_map(static fn(string $id): array => ['id' => $id, 'version' => 1, 'title' => $id, 'artifact_path' => 'marketing/campaigns/acquisition-199/templates/' . $id . '.html', 'sha256' => hash_file('sha256', dirname(__DIR__, 8) . '/marketing/campaigns/acquisition-199/templates/' . $id . '.html'), 'review' => ['status' => 'approved', 'reviewer' => 'synthetic-only', 'receipt' => 'synthetic-local'], 'recipe_ref' => ['owner' => 'component-studio', 'id' => $id, 'version' => 1, 'status' => 'registered']], ['beauty_editorial', 'beauty_service_first']);
    $invite = $samples->issue('controlled:report', 'controlled@example.test', 1, 1, 'beauty_hair', $recipes, ['business_name' => 'Fictional business', 'locality' => 'Fictional city'], ['business_verified' => TRUE, 'niche_confirmed' => TRUE, 'confirmed_niche' => 'beauty_hair', 'contact_owned' => TRUE, 'jurisdiction_eligible' => TRUE, 'provider_eligible' => TRUE, 'history_reconciled' => TRUE, 'bindings_verified' => TRUE, 'receipt' => 'synthetic-only', 'sha256' => str_repeat('a', 64)], 1800003600, 'generic');
    $samples->claim(70, $invite['token']);
    $context = $samples->continuation(70)['context_id'];
    foreach ([80 => 20, 90 => 30] as $requestId => $prospectId) $db->insert('famtastic_project_request')->fields(['id' => $requestId, 'public_id' => 'controlled-request-' . $requestId, 'customer_id' => 70, 'organization_id' => 7, 'prospect_id' => $prospectId, 'status' => 'submitted', 'project_name' => 'Fictional', 'business_name' => 'Fictional', 'project_type' => 'website', 'domain_choice' => 'undecided', 'intake_data' => '{}', 'submitted_at' => 1800000001, 'commerce_order_id' => $requestId === 80 ? 100 : 200, 'created' => 1, 'changed' => 1])->execute();
    $samples->attachRequest(70, $context, 80, 20);
    $samples->attachRequest(70, $context, 80, 20);
    foreach ([100 => 20, 200 => 30] as $orderId => $prospectId) $db->insert('famtastic_commerce_fulfillment')->fields(['commerce_order_id' => $orderId, 'customer_id' => 70, 'organization_id' => 7, 'prospect_id' => $prospectId, 'status' => 'fulfilled', 'sku_snapshot' => '[]', 'amount_minor' => 999999, 'currency' => 'USD', 'created' => 1, 'changed' => 1, 'fulfilled_at' => 1])->execute();
    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('hasDefinition')->with('commerce_payment')->willReturn(TRUE);
    $storage = $this->createMock(EntityStorageInterface::class);
    $query = new class {
      public array $conditions = [];
      public function accessCheck(bool $check): static { return $this; }
      public function condition(string $field, array $values, string $operator): static { $this->conditions = [$field, $values, $operator]; return $this; }
      public function execute(): array { return [1]; }
    };
    $storage->method('getQuery')->willReturn($query);
    $storage->method('loadMultiple')->willReturn([1 => new class {
      public function id(): int { return 1; }
      public function getOrderId(): int { return 100; }
      public function getState(): object { return (object) ['value' => 'partially_refunded']; }
      public function getPaymentGatewayId(): string { return 'synthetic-gateway'; }
      public function getPaymentGatewayMode(): string { return 'live'; }
      public function getRemoteId(): string { return 'synthetic-never-sent'; }
      public function getAmount(): object { return new class { public function getNumber(): string { return '199.00'; } public function getCurrencyCode(): string { return 'USD'; } }; }
      public function getRefundedAmount(): object { return new class { public function getNumber(): string { return '25.00'; } public function getCurrencyCode(): string { return 'USD'; } }; }
    }]);
    $manager->method('getStorage')->with('commerce_payment')->willReturn($storage);
    $report = (new AcquisitionCampaignReportService($db, $manager))->report('controlled-acquisition');
    $this->assertSame(['order_id', [100], 'IN'], $query->conditions, 'Unrelated same-account order 200 must not be queried.');
    $this->assertSame(1, $report['totals']['verified_registrations']);
    $this->assertSame(1, $report['totals']['completed_interviews']);
    $this->assertSame(1, $report['totals']['paid_customers']);
    $this->assertSame(17400, $report['totals']['revenue_minor']);
    $this->assertSame(1, $report['totals']['refunds']);
    $this->assertSame('beauty_hair', $report['breakdowns']['niche'][0]['key']);
    $this->assertStringNotContainsString('controlled@example.test', json_encode($report));
    $this->assertSame(1, (int) $db->select('famtastic_acquisition_request', 'm')->countQuery()->execute()->fetchField(), 'Context replay must retain the exact one request.');
  }
}
