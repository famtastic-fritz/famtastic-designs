<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Entity\ProofCampaign;
use Drupal\famtastic_pipeline\Entity\Prospect;
use Drupal\famtastic_pipeline\Service\{AttributionService, CustomerPortalService, FreshProofAdmission, FreshProofBinding, FreshProofInput, OperationalLedger, ProofAssetContract, ProofCampaignService, PublicPreviewDeliveryService, SiteStudioBuildPacketService, WorkerCapabilityPolicy, WorkerCoordinator, WorkerCoordinatorSchema};
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';
require_once __DIR__ . '/Fixtures/FreshProofLoginControllerCases.php';

/** Real writers, coordinator and SQLite transactions; entity storage is doubled. */
final class FreshProofAdmissionTest extends UnitTestCase {
  use FreshProofLoginControllerCases;
  private Connection $db;
  private TimeInterface $clock;
  private EntityTypeManagerInterface $entities;
  private CustomerPortalService $portal;
  private FreshProofAdmission $admission;
  private WorkerCoordinator $coordinator;
  private array $policy;
  private int $now = 1790010000;
  private int $uuidCounter = 100;
  private int $campaignCreates = 0;
  private bool $lockAvailable = TRUE;
  private ?\Closure $interleave = NULL;
  private ?\Closure $onCampaignSave = NULL;
  private array $entityObjects = [];

  protected function setUp(): void {
    parent::setUp();
    new Settings([]);
    $o = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new Connection(Connection::open($o), $o);
    $schemas = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_automation_schema() + _famtastic_pipeline_lifecycle_schema() + _famtastic_pipeline_preview_delivery_schema() + WorkerCoordinatorSchema::tables();
    foreach (['famtastic_project_request', 'famtastic_customer', 'famtastic_membership', 'famtastic_organization', 'famtastic_customer_resource', 'famtastic_request_asset', 'famtastic_private_offer', 'famtastic_job', 'famtastic_event', 'famtastic_notification_outbox', 'famtastic_portal_activity', 'famtastic_preview_delivery', 'famtastic_worker_claim', 'famtastic_worker_budget', 'famtastic_worker_nonce'] as $table) $this->db->schema()->createTable($table, $schemas[$table]);
    // These two schemas model only entity fields touched here, not a Drupal kernel.
    $this->db->query('CREATE TABLE famtastic_prospect (id INTEGER PRIMARY KEY AUTOINCREMENT, business_name TEXT, public_email TEXT, contact_name TEXT, contact_method TEXT, contact_value TEXT, campaign TEXT, source TEXT, authorized INTEGER, confirmed_at INTEGER, status TEXT, owner_uid INTEGER, utm_json TEXT)');
    $this->db->query('CREATE TABLE proof_campaign (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id TEXT UNIQUE, prospect_id INTEGER, business_name TEXT, status TEXT, generation_status TEXT, studio_job_id TEXT, expires_at INTEGER)');
    $this->db->insert('famtastic_customer')->fields(['id' => 1, 'public_id' => $this->uuid(), 'uid' => 1, 'display_name' => 'Synthetic', 'email' => 'synthetic@example.test', 'verified_at' => $this->now, 'created' => $this->now])->execute();
    $this->db->insert('famtastic_organization')->fields(['id' => 2, 'public_id' => '00000000-0000-0000-0000-000000000002', 'name' => 'Synthetic', 'status' => 'active', 'created' => $this->now])->execute();
    $this->db->insert('famtastic_membership')->fields(['organization_id' => 2, 'customer_id' => 1, 'status' => 'active', 'created' => $this->now])->execute();
    $this->clock = $this->createMock(TimeInterface::class);
    $this->clock->method('getRequestTime')->willReturnCallback(fn() => $this->now);
    $this->clock->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $this->entities = $this->createMock(EntityTypeManagerInterface::class);
    $this->entities->method('getStorage')->willReturnCallback(function (string $type) {
      if (!in_array($type, ['proof_campaign', 'famtastic_prospect'], TRUE)) throw new \LogicException('Unexpected entity access: ' . $type);
      $storage = $this->createMock(EntityStorageInterface::class);
      $storage->method('create')->willReturnCallback(fn(array $values) => $this->entity($type, $values));
      $storage->method('load')->willReturnCallback(fn($id) => $this->entityObjects[$type][$id] ?? NULL);
      return $storage;
    });
    $this->policy = ['recipe' => ['id' => 'synthetic-only', 'revision' => str_repeat('a', 40), 'sha256' => str_repeat('b', 64)],
      'tool_allowlist' => ['synthetic-no-network'], 'cost_policy' => ['id' => 'synthetic-bound-v1', 'revision' => str_repeat('c', 40), 'currency' => 'USD', 'max_calls' => 1, 'max_cost_cents' => 100, 'reservation_cents' => 100]];
    $this->install();
  }

  protected function tearDown(): void {
    \Drupal\famtastic_pipeline\Controller\PortalLoginSessionDouble::$finalize = NULL;
    new Settings([]); parent::tearDown();
  }
  private function uuid(): string { return '00000000-0000-0000-0000-' . str_pad((string) ++$this->uuidCounter, 12, '0', STR_PAD_LEFT); }
  private function enable(): void { new Settings(['famtastic_fresh_proof_admission_enabled' => TRUE]); }

  private function install(bool $admissionPolicy = TRUE, bool $coordinatorPolicy = TRUE, bool $admissionInstalled = TRUE): void {
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturnCallback(function () {
      if ($this->interleave) { $c = $this->interleave; $this->interleave = NULL; $c(); }
      return $this->lockAvailable;
    });
    $this->coordinator = new WorkerCoordinator($this->db, $this->clock, $lock, $coordinatorPolicy ? [$this->policy['cost_policy']['id'] => $this->policy] : []);
    $this->admission = new FreshProofAdmission($this->db, $this->entities, $this->clock, $this->coordinator, $admissionPolicy ? $this->policy : []);
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturnCallback(fn() => $this->uuid());
    $preview = (new \ReflectionClass(PublicPreviewDeliveryService::class))->newInstanceWithoutConstructor();
    (new \ReflectionProperty($preview, 'database'))->setValue($preview, $this->db);
    (new \ReflectionProperty($preview, 'time'))->setValue($preview, $this->clock);
    (new \ReflectionProperty($preview, 'ledger'))->setValue($preview, new OperationalLedger($this->db, $this->clock, $this->coordinator));
    $packets = (new \ReflectionClass(SiteStudioBuildPacketService::class))->newInstanceWithoutConstructor();
    $this->portal = new CustomerPortalService($this->db, $this->entities, $this->clock, $uuid,
      $this->getConfigFactoryStub(['famtastic_pipeline.settings' => ['frontend_base_url' => 'https://example.test', 'notification_to_email' => 'operator@example.test']]),
      new OperationalLedger($this->db, $this->clock, $this->coordinator), $packets, new AttributionService($this->db, $this->clock), $preview, NULL, $admissionInstalled ? $this->admission : NULL);
  }

  private function entity(string $type, array $values): object {
    if ($type === 'proof_campaign') $this->campaignCreates++;
    $entity = $this->createMock($type === 'proof_campaign' ? ProofCampaign::class : Prospect::class);
    $id = NULL;
    $entity->method('id')->willReturnCallback(function () use (&$id) { return $id; });
    $entity->method('get')->willReturnCallback(function ($field) use (&$values) { return (object) ['value' => $values[$field] ?? NULL, 'target_id' => $values[$field] ?? NULL]; });
    $entity->method('set')->willReturnCallback(function ($field, $value) use (&$values, $entity) { $values[$field] = $value; return $entity; });
    $entity->method('save')->willReturnCallback(function () use (&$id, &$values, $entity, $type) {
      if ($id === NULL) $id = (int) $this->db->insert($type)->fields($values)->execute();
      else $this->db->update($type)->fields($values)->condition('id', $id)->execute();
      $this->entityObjects[$type][$id] = $entity;
      if ($type === 'proof_campaign' && $this->onCampaignSave) { $c = $this->onCampaignSave; $this->onCampaignSave = NULL; $c(); }
      return 1;
    });
    return $entity;
  }

  private function input(string $action = 'submit'): array { return ['project_name' => 'Synthetic proof only', 'business_name' => 'Synthetic', 'primary_goal' => 'Describe a business', 'products_services' => 'Synthetic examples', 'action' => $action]; }
  private function create(string $action = 'submit'): array { return $this->portal->createWebsiteRequest(1, '00000000-0000-0000-0000-000000000002', $this->input($action)); }
  private function row(string $table, int $id = 1): array { return $this->db->select($table, 't')->fields('t')->condition('id', $id)->execute()->fetchAssoc(); }
  private function tableCount(string $table): int { return (int) $this->db->select($table, 't')->countQuery()->execute()->fetchField(); }
  private function transaction(callable $operation): mixed {
    $tx = $this->db->startTransaction();
    try { $r = $operation(); unset($tx); return $r; }
    catch (\Throwable $e) { $tx->rollBack(); throw $e; }
  }
  private function repeat(string $source = 'portal.create', ?string $prior = NULL): int { return $this->transaction(fn() => $this->admission->admit(1, 1, $source, $prior)); }
  private function reject(callable $operation, string $message): void {
    try { $operation(); }
    catch (\RuntimeException|\InvalidArgumentException $e) { self::assertStringContainsString($message, $e->getMessage()); return; }
    self::fail('Expected rejection: ' . $message);
  }
  private function asset(array $override = []): void {
    $this->db->insert('famtastic_request_asset')->fields($override + ['public_id' => $this->uuid(), 'website_request_id' => 1, 'customer_id' => 1, 'file_id' => 7,
      'original_name' => 'reference.png', 'mime_type' => 'image/png', 'size_bytes' => 12, 'sha256' => str_repeat('d', 64), 'ownership_confirmed' => 1, 'created' => $this->now, 'changed' => $this->now])->execute();
  }
  private function emptyAdmission(): void {
    foreach (['proof_campaign', 'famtastic_job', 'famtastic_worker_claim', 'famtastic_worker_budget', 'famtastic_notification_outbox', 'famtastic_event'] as $table) self::assertSame(0, $this->tableCount($table), $table);
  }

  public function testFlagOffPreservesLegacySubmissionWithoutPolicy(): void {
    $this->install(FALSE, FALSE);
    $this->create();
    self::assertSame('queued', $this->row('famtastic_job')['status']);
    self::assertSame(5, (int) $this->row('famtastic_job')['max_attempts']);
    self::assertNull($this->row('famtastic_project_request')['proof_campaign_id']);
    self::assertSame(0, $this->tableCount('proof_campaign'));
    self::assertSame(0, $this->tableCount('famtastic_worker_claim'));
  }

  public function testEnabledCreateIsOneInertBindingAndExactRetry(): void {
    $this->enable();
    $result = $this->create();
    self::assertSame('queued', $result['proof_handoff']['state']);
    self::assertStringNotContainsString('accepted', $result['proof_handoff']['label']);
    self::assertSame('queued', $this->row('proof_campaign')['generation_status']);
    self::assertSame('worker_queued', $this->row('famtastic_job')['status']);
    self::assertSame(0, $this->coordinator->health()['reserved_cents']);
    $event = FreshProofBinding::event($this->db, 1);
    self::assertSame(1, $this->repeat());
    self::assertSame($event, FreshProofBinding::event($this->db, 1));
    self::assertSame(1, $this->campaignCreates);
    self::assertSame(1, $this->tableCount('proof_campaign'));
    self::assertSame(2, $this->tableCount('famtastic_notification_outbox'));
    $r = FreshProofBinding::read($this->db, $event, $this->row('famtastic_project_request'));
    self::assertSame('portal.create', $r['binding']['freshness']['source']);
    self::assertSame(['a', 'b', 'c'], $r['payload']['direction_ids']);
    self::assertSame(100, $r['payload']['cost_policy']['reservation_cents']);
    $claim = $this->coordinator->claim('synthetic-mac', [WorkerCapabilityPolicy::PROOF]);
    self::assertSame('preparing', $this->portal->websiteRequestProofHandoff(1)['state']);
    self::assertStringContainsString('not yet confirmed', $this->portal->websiteRequestProofHandoff(1)['detail']);
    self::assertNull($this->coordinator->claim('synthetic-cloud', [WorkerCapabilityPolicy::PROOF]));
    $this->now = $claim['lease_until'];
    self::assertSame('needs_attention', $this->portal->websiteRequestProofHandoff(1)['state']);
    $this->db->update('famtastic_job')->fields(['status' => 'completed'])->condition('id', 1)->execute();
    $this->db->update('proof_campaign')->fields(['generation_status' => 'ready'])->condition('id', 1)->execute();
    self::assertSame('needs_attention', $this->portal->websiteRequestProofHandoff(1)['state']);
  }

  public function testDraftUpdateFreezesExactPrivateAssetsAndDoesNotReenroll(): void {
    $draft = $this->create('save'); $this->asset(); $this->enable();
    $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input());
    $event = FreshProofBinding::event($this->db, 1);
    $b = json_decode($event['payload'], TRUE);
    self::assertSame('draft', $b['freshness']['prior_status']);
    self::assertSame('portal.update', $b['freshness']['source']);
    self::assertFalse($b['asset_snapshot']['records'][0]['ai_transformation_consent']);
    self::assertTrue($b['asset_snapshot']['records'][0]['ownership_confirmed']);
    self::assertSame(1, $this->repeat('portal.update', 'draft'));
    $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input());
    self::assertSame($event, FreshProofBinding::event($this->db, 1));
    self::assertSame(1, $this->campaignCreates);
  }

  #[DataProvider('rollbackModes')]
  public function testCreateRollbackIncludesProspectRequestOutboxAndEnrollment(string $mode): void {
    $this->enable();
    if ($mode === 'missing_admission_policy') $this->install(FALSE);
    if ($mode === 'missing_coordinator_policy') $this->install(TRUE, FALSE);
    if ($mode === 'busy') $this->lockAvailable = FALSE;
    if ($mode === 'late_account_change') $this->interleave = fn() => $this->db->update('famtastic_customer')->fields(['verified_at' => NULL])->condition('id', 1)->execute();
    try { $this->create(); self::fail('Expected rollback'); } catch (\RuntimeException|\InvalidArgumentException) {}
    $this->emptyAdmission();
    self::assertSame(0, $this->tableCount('famtastic_prospect'));
    self::assertSame(0, $this->tableCount('famtastic_project_request'));
    self::assertSame(0, $this->tableCount('famtastic_customer_resource'));
    self::assertSame(0, $this->tableCount('famtastic_portal_activity'));
    self::assertNotNull($this->row('famtastic_customer')['verified_at']);
  }
  public static function rollbackModes(): iterable { foreach (['missing_admission_policy', 'missing_coordinator_policy', 'busy', 'late_account_change'] as $m) yield $m => [$m]; }

  #[DataProvider('ineligible')]
  public function testDraftFailureRestoresRequestAndPreservesHistory(string $table, array $change, string $message): void {
    $draft = $this->create('save'); $this->asset();
    $this->db->update($table)->fields($change)->condition('id', $table === 'famtastic_organization' ? 2 : 1)->execute();
    $before = $this->row('famtastic_project_request');
    $this->enable();
    $this->reject(fn() => $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input()), $message);
    self::assertSame($before, $this->row('famtastic_project_request'));
    self::assertSame(0, $this->campaignCreates);
    $this->emptyAdmission();
  }
  public static function ineligible(): iterable {
    yield 'unverified' => ['famtastic_customer', ['verified_at' => NULL], 'Verified account'];
    yield 'inactive member' => ['famtastic_membership', ['status' => 'inactive'], 'not found'];
    yield 'inactive org' => ['famtastic_organization', ['status' => 'inactive'], 'Verified account'];
    yield 'foreign resource' => ['famtastic_customer_resource', ['organization_id' => 3], 'Verified account'];
    yield 'foreign asset' => ['famtastic_request_asset', ['customer_id' => 3], 'asset ownership'];
    yield 'asset rights' => ['famtastic_request_asset', ['ownership_confirmed' => 0], 'asset integrity'];
    yield 'asset hash' => ['famtastic_request_asset', ['sha256' => 'invalid'], 'asset integrity'];
    yield 'paid' => ['famtastic_project_request', ['commerce_order_id' => 9], 'not eligible'];
    yield 'bound campaign' => ['famtastic_project_request', ['proof_campaign_id' => 9], 'Existing proof campaign'];
    yield 'advanced history' => ['famtastic_project_request', ['proof_review_status' => 'revision_requested'], 'not eligible'];
  }

  #[DataProvider('historicalJobs')]
  public function testLegacyJobsExcludeAdmissionBeforeAllocation(string $key, string $status): void {
    $draft = $this->create('save');
    $this->db->insert('famtastic_job')->fields(['job_key' => $key, 'job_type' => 'proof.generate', 'prospect_id' => 1, 'status' => $status, 'payload' => '{}', 'created' => $this->now])->execute();
    $before = $this->row('famtastic_job'); $this->enable();
    $this->reject(fn() => $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input()), 'Historical proof');
    self::assertSame($before, $this->row('famtastic_job'));
    self::assertSame('draft', $this->row('famtastic_project_request')['status']);
    self::assertSame(0, $this->campaignCreates);
  }
  public static function historicalJobs(): iterable {
    foreach (['queued', 'running', 'failed', 'completed', 'worker_queued'] as $status) {
      yield 'old-' . $status => ['website_proof.generate.v1:request:1', $status];
      yield 'brief-' . $status => ['website_proof.generate.v1:request:1:brief:old', $status];
    }
  }

  #[DataProvider('historicalCampaignIds')]
  public function testUnboundImportedCampaignIsNotAdoptedOrDuplicated(string $campaignId): void {
    $draft = $this->create('save');
    $this->db->insert('proof_campaign')->fields(['campaign_id' => $campaignId, 'prospect_id' => 1, 'generation_status' => 'ready'])->execute();
    $before = $this->row('proof_campaign'); $this->enable();
    $this->reject(fn() => $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input()), 'Historical proof');
    self::assertSame($before, $this->row('proof_campaign'));
    self::assertSame(0, $this->campaignCreates);
  }
  public static function historicalCampaignIds(): iterable {
    foreach (['legacy-import', 'pc-legacy-0123456789abcdef', 'proof-' . str_repeat('a', 32)] as $id) yield $id => [$id];
  }

  public function testNewManagedCampaignUsesCanonicalAssetNamespaceWithoutDispatch(): void {
    $this->enable(); $this->create();
    $campaignId = $this->row('proof_campaign')['campaign_id'];
    $payload = json_decode($this->row('famtastic_job')['payload'], TRUE, flags: JSON_THROW_ON_ERROR);
    self::assertSame($campaignId, $payload['campaign_id']);
    foreach ($payload['direction_ids'] as $direction) {
      self::assertSame('web/proofs/' . $campaignId . '/' . $direction . '/assets/media/hero.png',
        ProofAssetContract::artifactPath($campaignId, $direction, 'media/hero.png'));
    }
    self::assertMatchesRegularExpression('/^pc-[a-f0-9]{32}$/D', $campaignId);
    self::assertSame('queued', $this->row('proof_campaign')['generation_status']);
    $before = $this->proofRecords();
    self::assertSame(1, $this->repeat());
    self::assertSame($before, $this->proofRecords());
    self::assertSame(1, $this->campaignCreates);
  }

  public function testLateWithdrawalRollsBackEverythingAndExactReplayRejectsChangedRights(): void {
    $draft = $this->create('save'); $this->asset(); $this->enable();
    $this->interleave = fn() => $this->db->update('famtastic_request_asset')->fields(['status' => 'withdrawn'])->condition('id', 1)->execute();
    $this->reject(fn() => $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input()), 'input changed');
    self::assertSame('active', $this->row('famtastic_request_asset')['status']);
    self::assertSame('draft', $this->row('famtastic_project_request')['status']);
    $this->emptyAdmission();
    $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input());
    $event = FreshProofBinding::event($this->db, 1);
    $this->db->update('famtastic_request_asset')->fields(['status' => 'withdrawn'])->condition('id', 1)->execute();
    $this->reject(fn() => $this->repeat('portal.update', 'draft'), 'differs from current input');
    self::assertSame($event, FreshProofBinding::event($this->db, 1));
  }

  public function testUniqueInsertFailureCannotCommitAnOrphanCampaign(): void {
    $draft = $this->create('save'); $this->enable();
    $this->onCampaignSave = function () {
      $r = $this->row('famtastic_project_request');
      $hash = hash('sha256', FreshProofInput::wire(json_decode($r['intake_data'], TRUE)));
      $this->db->insert('famtastic_job')->fields(['job_key' => 'website_proof.generate.v1:request:1:brief:' . $hash, 'job_type' => 'proof.generate', 'status' => 'queued', 'payload' => '{}', 'created' => $this->now])->execute();
    };
    try { $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input()); self::fail('Duplicate must throw'); }
    catch (\Drupal\Core\Database\IntegrityConstraintViolationException) {}
    $this->emptyAdmission();
    self::assertSame('draft', $this->row('famtastic_project_request')['status']);
  }

  #[DataProvider('corruptions')]
  public function testExactBindingBytesAndIdentityCannotBeSubstituted(string $corruption): void {
    $this->enable(); $this->create();
    $event = FreshProofBinding::event($this->db, 1);
    if ($corruption === 'event_bytes') $this->db->update('famtastic_event')->fields(['payload' => $event['payload'] . ' '])->condition('id', $event['id'])->execute();
    if ($corruption === 'job_bytes') $this->db->update('famtastic_job')->fields(['payload' => $this->row('famtastic_job')['payload'] . ' '])->condition('id', 1)->execute();
    if ($corruption === 'event_campaign') $this->db->update('famtastic_event')->fields(['campaign_id' => 8])->condition('id', $event['id'])->execute();
    if ($corruption === 'claim_hash') $this->db->update('famtastic_worker_claim')->fields(['payload_sha256' => str_repeat('f', 64)])->condition('job_id', 1)->execute();
    if ($corruption === 'request_scope') $this->db->update('famtastic_project_request')->fields(['project_type' => 'online_store'])->condition('id', 1)->execute();
    if ($corruption === 'campaign_job') $this->db->update('proof_campaign')->fields(['studio_job_id' => 'foreign'])->condition('id', 1)->execute();
    try { $this->repeat(); self::fail('Changed immutable input must reject'); } catch (\RuntimeException|\InvalidArgumentException) {}
    self::assertSame(1, $this->campaignCreates);
    self::assertSame(1, $this->tableCount('proof_campaign'));
  }
  public static function corruptions(): iterable { foreach (['event_bytes', 'job_bytes', 'event_campaign', 'claim_hash', 'request_scope', 'campaign_job'] as $c) yield $c => [$c]; }

  public function testGenericImportRejectsManagedBeforeAnyDuplicateOrLegacyLookupEvenFlagOff(): void {
    $this->enable(); $this->create(); new Settings([]);
    $campaign = $this->entityObjects['proof_campaign'][1];
    $service = $this->getMockBuilder(ProofCampaignService::class)->disableOriginalConstructor()->onlyMethods(['loadByCampaignId'])->getMock();
    $service->method('loadByCampaignId')->willReturn($campaign);
    (new \ReflectionProperty($service, 'database'))->setValue($service, $this->db);
    // All downstream dependencies intentionally uninitialized: guard must be first.
    $this->reject(fn() => $service->acceptCallback('already-processed', $this->row('proof_campaign')['campaign_id'], $this->row('proof_campaign')['studio_job_id'], []), 'fenced importer');
    $this->db->update('famtastic_event')->fields(['payload' => '{broken'])->condition('event_type', FreshProofBinding::EVENT)->execute();
    $this->reject(fn() => $service->acceptCallback('new-event', $this->row('proof_campaign')['campaign_id'], $this->row('proof_campaign')['studio_job_id'], []), 'fenced importer');
    $this->db->update('famtastic_event')->fields(['campaign_id' => 999])->condition('event_type', FreshProofBinding::EVENT)->execute();
    $this->reject(fn() => $service->acceptCallback('new-event', $this->row('proof_campaign')['campaign_id'], $this->row('proof_campaign')['studio_job_id'], []), 'fenced importer');
    FreshProofBinding::assertGenericImportAllowed($this->db, 998);
    self::assertSame('queued', $this->row('proof_campaign')['generation_status']);
  }

  public function testFreshnessAndOuterTransactionCannotBeForgedByBodyOrServiceDefaults(): void {
    $this->enable();
    $this->reject(fn() => $this->admission->admit(1, 1, 'portal.create', NULL), 'outer request transaction');
    $draft = $this->create('save');
    $this->reject(fn() => $this->repeat('login.repair', 'submitted'), 'intent');
    $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input('save') + ['freshness' => ['source' => 'portal.create'], 'capability' => WorkerCapabilityPolicy::PROOF]);
    self::assertSame(0, $this->campaignCreates);
    self::assertSame('draft', $this->row('famtastic_project_request')['status']);
  }

  public function testAnotherRequestsJobDoesNotCausePrefixCollision(): void {
    $draft = $this->create('save'); $this->enable();
    $this->db->insert('famtastic_job')->fields(['job_key' => 'website_proof.generate.v1:request:10:brief:old', 'job_type' => 'proof.generate', 'prospect_id' => 10, 'status' => 'failed', 'payload' => '{}', 'created' => $this->now])->execute();
    $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input());
    self::assertSame(1, $this->campaignCreates);
    self::assertSame('failed', $this->row('famtastic_job')['status']);
    self::assertSame('worker_queued', $this->row('famtastic_job', 2)['status']);
  }

  public function testMissingProspectAndForeignCustomerCannotSubmit(): void {
    $draft = $this->create('save'); $this->enable();
    $this->reject(fn() => $this->portal->updateWebsiteRequest(2, $draft['public_id'], $this->input()), 'not found');
    $this->db->delete('famtastic_prospect')->condition('id', 1)->execute();
    unset($this->entityObjects['famtastic_prospect'][1]);
    $this->reject(fn() => $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input()), 'Verified account');
    self::assertSame('draft', $this->row('famtastic_project_request')['status']);
    $this->emptyAdmission();
  }

  public function testLateScopeChangeAndNewAssetCannotChangeFrozenInput(): void {
    $draft = $this->create('save'); $this->enable();
    $this->interleave = function () { $this->asset(); };
    $this->reject(fn() => $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input()), 'input changed');
    self::assertSame(0, $this->tableCount('famtastic_request_asset'));
    $this->emptyAdmission();
    $this->interleave = fn() => $this->db->update('famtastic_project_request')->fields(['project_type' => 'online_store'])->condition('id', 1)->execute();
    $this->reject(fn() => $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input()), 'input changed');
    self::assertSame('new_website', $this->row('famtastic_project_request')['project_type']);
    $this->emptyAdmission();
  }

  public function testClaimedPreviewCannotOverwriteForeignResourceOwnership(): void {
    $this->create('save');
    $this->db->update('famtastic_customer_resource')->fields(['organization_id' => 3])->condition('id', 1)->execute();
    // Only fields read by claimedProspectId: a deliberately minimal preview double.
    $schema = $this->db->schema();
    $schema->dropTable('famtastic_preview_delivery');
    $this->db->query('CREATE TABLE famtastic_preview_delivery (id INTEGER PRIMARY KEY, customer_id INTEGER, prospect_id INTEGER, website_request_id INTEGER, claimed_at INTEGER)');
    $this->db->insert('famtastic_preview_delivery')->fields(['id' => 1, 'customer_id' => 1, 'prospect_id' => 1, 'claimed_at' => $this->now])->execute();
    $this->enable();
    $this->reject(fn() => $this->create(), 'different workspace');
    self::assertSame(3, (int) $this->row('famtastic_customer_resource')['organization_id']);
    self::assertSame(1, $this->tableCount('famtastic_project_request'));
    $this->emptyAdmission();
  }

  public function testNonDraftTransitionRemainsLegacyAndBooleanGateIsStrict(): void {
    new Settings(['famtastic_fresh_proof_admission_enabled' => 'true']);
    self::assertFalse(FreshProofAdmission::enabled());
    $draft = $this->create('save');
    $this->db->update('famtastic_project_request')->fields(['status' => 'checkout_started'])->condition('id', 1)->execute();
    $this->enable();
    $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input());
    self::assertSame('queued', $this->row('famtastic_job')['status']);
    self::assertSame(0, $this->campaignCreates);
    self::assertSame(0, $this->tableCount('famtastic_worker_claim'));
  }

  /** Full rows, including frozen bytes, attempts, holds and outbox dedupe keys. */
  private function proofRecords(): array {
    $records = [];
    foreach (['famtastic_project_request', 'proof_campaign', 'famtastic_job', 'famtastic_worker_claim', 'famtastic_worker_budget', 'famtastic_event', 'famtastic_notification_outbox'] as $table) {
      $key = match ($table) { 'famtastic_worker_claim' => 'job_id', 'famtastic_worker_budget' => 'reservation_key', default => 'id' };
      $records[$table] = $this->db->select($table, 't')->fields('t')->orderBy($key)->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }
    return $records;
  }

  #[DataProvider('resendCases')]
  public function testPublicManualResendCannotEscapeManagedClaim(bool $enabled, bool $changed, bool $claimed): void {
    $this->enable(); $request = $this->create();
    if ($claimed) $this->coordinator->claim('synthetic-mac', [WorkerCapabilityPolicy::PROOF]);
    new Settings(['famtastic_fresh_proof_admission_enabled' => $enabled]);
    if ($changed) $this->portal->updateWebsiteRequest(1, $request['public_id'], ['primary_goal' => 'Changed submitted brief'] + $this->input());
    $before = $this->proofRecords();
    for ($i = 0; $i < 2; $i++) {
      if ($changed) $this->reject(fn() => $this->portal->sendWebsiteRequestToSiteStudio(1, $request['public_id']), 'differs from current input');
      else {
        $result = $this->portal->sendWebsiteRequestToSiteStudio(1, $request['public_id']);
        self::assertSame($claimed ? 'preparing' : 'queued', $result['proof_handoff']['state']);
        self::assertSame(1, $result['proof_handoff']['job_id']);
      }
      self::assertSame($before, $this->proofRecords());
    }
    self::assertSame(1, $this->campaignCreates);
    self::assertFalse($this->db->inTransaction());
  }
  public static function resendCases(): iterable {
    foreach ([TRUE, FALSE] as $enabled) foreach ([FALSE, TRUE] as $changed) foreach ([FALSE, TRUE] as $claimed) {
      yield ($enabled ? 'on' : 'off') . '-' . ($changed ? 'edited' : 'same') . '-' . ($claimed ? 'leased' : 'pending') => [$enabled, $changed, $claimed];
    }
  }

  #[DataProvider('resendAuthority')]
  public function testManagedManualResendRechecksLiveAccountAndRights(bool $enabled, string $table, array $change, string $message): void {
    $draft = $this->create('save'); $this->asset(); $this->enable();
    $this->portal->updateWebsiteRequest(1, $draft['public_id'], $this->input());
    new Settings(['famtastic_fresh_proof_admission_enabled' => $enabled]);
    $this->db->update($table)->fields($change)->condition('id', $table === 'famtastic_organization' ? 2 : 1)->execute();
    $before = $this->proofRecords();
    $this->reject(fn() => $this->portal->sendWebsiteRequestToSiteStudio(1, $draft['public_id']), $message);
    self::assertSame($before, $this->proofRecords());
    self::assertSame(1, $this->campaignCreates);
    self::assertFalse($this->db->inTransaction());
  }
  public static function resendAuthority(): iterable {
    foreach ([TRUE, FALSE] as $enabled) foreach ([
      'unverified' => ['famtastic_customer', ['verified_at' => NULL], 'Verified account'],
      'inactive member' => ['famtastic_membership', ['status' => 'inactive'], 'not found'],
      'inactive org' => ['famtastic_organization', ['status' => 'inactive'], 'Verified account'],
      'foreign resource' => ['famtastic_customer_resource', ['organization_id' => 3], 'Verified account'],
      'foreign asset' => ['famtastic_request_asset', ['customer_id' => 3], 'asset ownership'],
      'rights revoked' => ['famtastic_request_asset', ['ownership_confirmed' => 0], 'asset integrity'],
      'withdrawn' => ['famtastic_request_asset', ['status' => 'withdrawn'], 'differs from current input'],
      'consent changed' => ['famtastic_request_asset', ['ai_transformation_consent' => 1], 'differs from current input'],
    ] as $case => $values) yield ($enabled ? 'on-' : 'off-') . $case => [$enabled, ...$values];
  }

  #[DataProvider('corruptions')]
  public function testMalformedManagedResendCannotFallBackWhenDisabled(string $corruption): void {
    $this->enable(); $request = $this->create(); new Settings([]);
    $event = FreshProofBinding::event($this->db, 1);
    if ($corruption === 'event_bytes') $this->db->update('famtastic_event')->fields(['payload' => '{broken'])->condition('id', $event['id'])->execute();
    if ($corruption === 'job_bytes') $this->db->update('famtastic_job')->fields(['payload' => $this->row('famtastic_job')['payload'] . ' '])->condition('id', 1)->execute();
    if ($corruption === 'event_campaign') $this->db->update('famtastic_event')->fields(['event_key' => 'damaged-key'])->condition('id', $event['id'])->execute();
    if ($corruption === 'claim_hash') $this->db->update('famtastic_worker_claim')->fields(['payload_sha256' => str_repeat('f', 64)])->condition('job_id', 1)->execute();
    if ($corruption === 'request_scope') $this->db->update('famtastic_project_request')->fields(['proof_campaign_id' => NULL])->condition('id', 1)->execute();
    if ($corruption === 'campaign_job') $this->db->update('proof_campaign')->fields(['studio_job_id' => 'foreign'])->condition('id', 1)->execute();
    $before = $this->proofRecords();
    $rejected = FALSE;
    try { $this->portal->sendWebsiteRequestToSiteStudio(1, $request['public_id']); }
    catch (\RuntimeException|\InvalidArgumentException|\JsonException) { $rejected = TRUE; }
    self::assertTrue($rejected, 'Corrupt binding must reject');
    self::assertSame($before, $this->proofRecords());
    self::assertFalse($this->db->inTransaction());
  }

  #[DataProvider('flags')]
  public function testManagedRevisionRejectsBeforeExpiringOrResettingAnything(bool $enabled): void {
    $this->enable(); $this->create(); new Settings(['famtastic_fresh_proof_admission_enabled' => $enabled]);
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'revision_requested'])->condition('id', 1)->execute();
    $before = $this->proofRecords(); $activities = $this->tableCount('famtastic_portal_activity');
    $this->reject(fn() => $this->portal->prepareWebsiteRequestRevisionRebuild(1, 9, 'Synthetic replacement request'), 'replacement policy');
    self::assertSame($before, $this->proofRecords());
    self::assertSame('active', $this->entityObjects['proof_campaign'][1]->get('status')->value);
    self::assertSame($activities, $this->tableCount('famtastic_portal_activity'));
    self::assertFalse($this->db->inTransaction());
  }
  public static function flags(): iterable { yield 'on' => [TRUE]; yield 'off' => [FALSE]; }

  #[DataProvider('flags')]
  public function testUnmanagedResendPreservesLegacyBriefDeduplication(bool $enabled): void {
    $request = $this->create(); new Settings(['famtastic_fresh_proof_admission_enabled' => $enabled]);
    $before = $this->proofRecords();
    $this->portal->sendWebsiteRequestToSiteStudio(1, $request['public_id']);
    self::assertSame($before, $this->proofRecords());
    $this->portal->updateWebsiteRequest(1, $request['public_id'], ['primary_goal' => 'Changed legacy brief'] + $this->input());
    $this->portal->sendWebsiteRequestToSiteStudio(1, $request['public_id']);
    $this->portal->sendWebsiteRequestToSiteStudio(1, $request['public_id']);
    self::assertSame(2, $this->tableCount('famtastic_job'));
    self::assertSame('queued', $this->row('famtastic_job', 2)['status']);
    self::assertSame(5, (int) $this->row('famtastic_job', 2)['max_attempts']);
    self::assertSame(0, $this->tableCount('famtastic_worker_claim'));
    self::assertSame(0, $this->campaignCreates);
    self::assertSame($before['famtastic_notification_outbox'], $this->proofRecords()['famtastic_notification_outbox']);
  }

  public function testManagedReuseWithMissingInstalledPolicyNeverReopensLegacy(): void {
    $this->enable(); $request = $this->create(); new Settings([]); $this->install(FALSE, FALSE);
    $before = $this->proofRecords();
    $this->reject(fn() => $this->portal->sendWebsiteRequestToSiteStudio(1, $request['public_id']), 'No reviewed creative cost policy');
    self::assertSame($before, $this->proofRecords());
  }

  public function testMissingAdmissionServiceAndNewAssetCannotReopenLegacy(): void {
    $this->enable(); $request = $this->create(); new Settings([]); $this->install(TRUE, TRUE, FALSE);
    $before = $this->proofRecords();
    $this->reject(fn() => $this->portal->sendWebsiteRequestToSiteStudio(1, $request['public_id']), 'service is not installed');
    self::assertSame($before, $this->proofRecords());
    $this->install(); $this->asset();
    $this->reject(fn() => $this->portal->sendWebsiteRequestToSiteStudio(1, $request['public_id']), 'differs from current input');
    self::assertSame($before, $this->proofRecords());
  }

  #[DataProvider('flags')]
  public function testUnmanagedRevisionStillQueuesItsLegacyReplacement(bool $enabled): void {
    $this->create();
    $campaign = $this->entity('proof_campaign', ['campaign_id' => 'pc-legacy', 'prospect_id' => 1, 'status' => 'active', 'generation_status' => 'ready', 'expires_at' => $this->now + 3600]);
    $campaign->save();
    $this->db->update('famtastic_project_request')->fields(['proof_review_status' => 'revision_requested', 'proof_campaign_id' => 1])->condition('id', 1)->execute();
    new Settings(['famtastic_fresh_proof_admission_enabled' => $enabled]);
    $notices = $this->proofRecords()['famtastic_notification_outbox'];
    $result = $this->portal->prepareWebsiteRequestRevisionRebuild(1, 9, 'Synthetic legacy replacement');
    self::assertSame(2, $result['job_id']);
    self::assertSame(1, $result['prior_campaign_id']);
    self::assertSame('expired', $this->row('proof_campaign')['status']);
    self::assertNull($this->row('famtastic_project_request')['proof_campaign_id']);
    self::assertSame('queued', $this->row('famtastic_job', 2)['status']);
    self::assertSame(5, (int) $this->row('famtastic_job', 2)['max_attempts']);
    self::assertSame(0, $this->tableCount('famtastic_worker_claim'));
    self::assertSame($notices, $this->proofRecords()['famtastic_notification_outbox']);
    self::assertFalse($this->db->inTransaction());
  }

  #[DataProvider('flags')]
  public function testNonFreshDeepDiveResumeCannotQueueAfterManagedBindingIsCleared(bool $enabled): void {
    $this->enable(); $this->create();
    new Settings(['famtastic_fresh_proof_admission_enabled' => $enabled]);
    $this->db->update('famtastic_project_request')->fields(['proof_campaign_id' => NULL])->condition('id', 1)->execute();
    $before = $this->proofRecords();
    // The public deep-dive entry delegates to the actual existing-request helper.
    self::assertSame(1, $this->portal->createWebsiteRequestFromDeepDive(1, ['status' => 'claimed', 'website_request_id' => 1]));
    self::assertSame('needs_attention', $this->portal->websiteRequestProofHandoff(1)['state']);
    self::assertSame($before, $this->proofRecords());
    self::assertSame(1, $this->campaignCreates);
  }

  #[DataProvider('flags')]
  public function testManagedDeepDiveResumeReturnsOwnedRequestWithoutProofRetry(bool $enabled): void {
    $this->enable(); $request = $this->create(); new Settings(['famtastic_fresh_proof_admission_enabled' => $enabled]);
    $before = $this->proofRecords();
    self::assertSame(1, $this->portal->createWebsiteRequestFromDeepDive(1, ['status' => 'claimed', 'website_request_id' => 1]));
    self::assertSame($before, $this->proofRecords());
    $this->portal->updateWebsiteRequest(1, $request['public_id'], ['primary_goal' => 'Changed submitted brief'] + $this->input());
    $before = $this->proofRecords();
    self::assertSame(1, $this->portal->createWebsiteRequestFromDeepDive(1, ['status' => 'claimed', 'website_request_id' => 1]));
    self::assertSame('needs_attention', $this->portal->websiteRequestProofHandoff(1)['state']);
    self::assertSame($before, $this->proofRecords());
    self::assertFalse($this->db->inTransaction());
  }

  #[DataProvider('flags')]
  public function testUnmanagedDeepDiveStillSubmitsDraftAndReusesItsLegacyJob(bool $enabled): void {
    $this->create('save'); new Settings(['famtastic_fresh_proof_admission_enabled' => $enabled]);
    self::assertSame(1, $this->portal->createWebsiteRequestFromDeepDive(1, ['status' => 'claimed', 'website_request_id' => 1]));
    $before = $this->proofRecords();
    self::assertSame(1, $this->portal->createWebsiteRequestFromDeepDive(1, ['status' => 'claimed', 'website_request_id' => 1]));
    self::assertSame($before, $this->proofRecords());
    self::assertSame('submitted', $this->row('famtastic_project_request')['status']);
    self::assertSame('queued', $this->row('famtastic_job')['status']);
    self::assertSame(5, (int) $this->row('famtastic_job')['max_attempts']);
    self::assertSame(0, $this->campaignCreates);
    self::assertSame(0, $this->tableCount('famtastic_worker_claim'));
    self::assertFalse($this->db->inTransaction());
  }
}
