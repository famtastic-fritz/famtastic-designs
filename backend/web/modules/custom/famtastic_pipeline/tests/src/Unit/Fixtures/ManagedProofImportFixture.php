<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit\Fixtures;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\{EntityStorageInterface, EntityTypeManagerInterface};
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Entity\{ProofCampaign, ProofVariant};
use Drupal\famtastic_pipeline\Service\{BuildTelemetryService, FreshProofAdmission, FreshProofBinding, ManagedProofArtifactStore, ManagedProofArtifactPackage,
  ManagedProofPackageFiles, ManagedProofImportContract as Contract, ManagedProofImporter, ProofCallbackArtifacts, ProofOperationJournal, WorkerCapabilityPolicy, WorkerCoordinator, WorkerCoordinatorSchema};
use Drupal\Tests\UnitTestCase;

require_once dirname(__DIR__, 4) . '/famtastic_pipeline.install';
require_once __DIR__ . '/ProofOperationConnection.php';
require_once __DIR__ . '/ProofArtifactInputs.php';

/** Real services/SQLite/files; entity storage and trusted provenance are doubles. */
abstract class ManagedProofImportFixture extends UnitTestCase {
  protected ProofOperationConnection $db;
  protected TimeInterface $clock;
  protected EntityTypeManagerInterface $entities;
  protected FreshProofAdmission $admission;
  protected WorkerCoordinator $coordinator;
  protected ProofOperationJournal $journal;
  protected BuildTelemetryService $telemetry;
  protected ManagedProofArtifactStore $store;
  protected ManagedProofArtifactPackage $package;
  protected ManagedProofImporter $importer;
  protected array $claim;
  protected array $provenance;
  protected array $policy;
  protected array $catalog;
  protected array $completion;
  protected array $source;
  protected array $preparedPackage;
  protected array $operationReceipt;
  protected int $now = 1790010000;
  protected string $worker = 'synthetic-mac';
  protected string $temporary;
  protected bool $ackAllowed = TRUE;
  protected bool $provenanceAllowed = TRUE;
  protected ?\Closure $afterVariantSaved = NULL;
  protected ?\Closure $afterVerification = NULL;

  protected function setUp(): void {
    parent::setUp(); new Settings(['famtastic_fresh_proof_admission_enabled' => TRUE]);
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new ProofOperationConnection(ProofOperationConnection::open($options), $options);
    $schemas = _famtastic_pipeline_customer_portal_schema() + _famtastic_pipeline_automation_schema() + WorkerCoordinatorSchema::tables();
    foreach (['famtastic_project_request', 'famtastic_customer', 'famtastic_organization', 'famtastic_membership', 'famtastic_customer_resource', 'famtastic_request_asset',
      'famtastic_job', 'famtastic_event', 'famtastic_worker_mutex', 'famtastic_worker_claim', 'famtastic_worker_budget', 'famtastic_worker_nonce', 'famtastic_proof_operation'] as $table) $this->db->schema()->createTable($table, $schemas[$table]);
    $this->db->schema()->createTable('famtastic_build_run', _famtastic_pipeline_build_run_schema());
    $this->db->schema()->createTable('famtastic_notification_outbox', _famtastic_pipeline_lifecycle_schema()['famtastic_notification_outbox']);
    $this->db->schema()->createTable('famtastic_portal_activity', $schemas['famtastic_portal_activity']);
    $this->db->insert('famtastic_notification_outbox')->fields(['notification_key' => 'synthetic-preexisting', 'category' => 'synthetic',
      'recipient' => 'synthetic@example.test', 'subject' => 'Synthetic unsent fixture', 'body' => 'Never send', 'status' => 'queued',
      'available_at' => $this->now, 'created' => $this->now, 'changed' => $this->now])->execute();
    $this->db->insert('famtastic_portal_activity')->fields(['organization_id' => 2, 'event_type' => 'synthetic.preexisting',
      'summary' => 'Synthetic history fixture', 'metadata' => '{}', 'created' => $this->now])->execute();
    $this->db->query('CREATE TABLE famtastic_prospect (id INTEGER PRIMARY KEY)');
    $this->db->query('CREATE TABLE proof_campaign (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id TEXT, prospect_id INTEGER, business_name TEXT, status TEXT, generation_status TEXT, studio_job_id TEXT, expires_at INTEGER, ready_at INTEGER, selected_variant INTEGER, changed INTEGER)');
    $this->db->query('CREATE TABLE proof_variant (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, direction_id TEXT, direction_name TEXT, artifact_path TEXT, thumbnail_path TEXT, preview_url TEXT, design_dna TEXT, created INTEGER)');
    $this->db->insert('famtastic_customer')->fields(['id' => 1, 'public_id' => $this->uuid(1), 'uid' => 1, 'display_name' => 'Synthetic', 'email' => 'synthetic@example.test', 'verified_at' => $this->now, 'created' => $this->now])->execute();
    $this->db->insert('famtastic_organization')->fields(['id' => 2, 'public_id' => $this->uuid(2), 'name' => 'Synthetic', 'status' => 'active', 'created' => $this->now])->execute();
    $this->db->insert('famtastic_membership')->fields(['customer_id' => 1, 'organization_id' => 2, 'status' => 'active', 'created' => $this->now])->execute();
    $this->db->insert('famtastic_prospect')->fields(['id' => 3])->execute();
    $this->db->insert('famtastic_customer_resource')->fields(['organization_id' => 2, 'resource_type' => 'prospect', 'resource_id' => 3, 'created' => $this->now])->execute();
    $this->db->insert('famtastic_project_request')->fields(['id' => 1, 'public_id' => $this->uuid(4), 'customer_id' => 1, 'organization_id' => 2, 'prospect_id' => 3,
      'project_name' => 'Synthetic', 'business_name' => 'Synthetic', 'project_type' => 'website', 'status' => 'submitted', 'submitted_at' => $this->now,
      'intake_data' => '{"primary_goal":"Synthetic only"}', 'created' => $this->now, 'changed' => $this->now])->execute();
    $this->db->insert('famtastic_request_asset')->fields(['id' => 1, 'public_id' => $this->uuid(5), 'website_request_id' => 1, 'customer_id' => 1, 'file_id' => 7,
      'original_name' => 'synthetic.txt', 'mime_type' => 'text/plain', 'size_bytes' => 9, 'sha256' => hash('sha256', 'synthetic'), 'ownership_confirmed' => 1,
      'ai_use_consent' => 1, 'subject_permission_confirmed' => 1, 'ai_transformation_consent' => 1, 'likeness_consent_version' => 'synthetic-v1', 'likeness_consent_at' => $this->now,
      'created' => $this->now, 'changed' => $this->now])->execute();
    $this->clock = $this->createMock(TimeInterface::class);
    $this->clock->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $this->clock->method('getRequestTime')->willReturnCallback(fn() => $this->now);
    $lock = $this->createMock(LockBackendInterface::class); $lock->method('acquire')->willReturn(TRUE);
    $this->policy = ['recipe' => ['id' => 'synthetic-only', 'revision' => str_repeat('a', 40), 'sha256' => str_repeat('b', 64)], 'tool_allowlist' => ['synthetic-tool'],
      'cost_policy' => ['id' => 'synthetic-cost', 'revision' => str_repeat('c', 40), 'currency' => 'USD', 'max_calls' => 2, 'max_cost_cents' => 100, 'reservation_cents' => 100]];
    $this->coordinator = new WorkerCoordinator($this->db, $this->clock, $lock, ['synthetic-cost' => $this->policy]);
    $this->entities = $this->createMock(EntityTypeManagerInterface::class);
    $storages = [];
    foreach (['proof_campaign' => ProofCampaign::class, 'proof_variant' => ProofVariant::class] as $table => $class) {
      $storage = $this->createMock(EntityStorageInterface::class);
      $storage->method('create')->willReturnCallback(function (array $values) use ($table, $class) {
        $entity = $this->createMock($class); $id = NULL;
        $entity->method('save')->willReturnCallback(function () use ($values, $table, &$id) {
          $id = (int) $this->db->insert($table)->fields($values)->execute();
          if ($table === 'proof_variant' && $this->afterVariantSaved) ($this->afterVariantSaved)($values);
          return 1;
        });
        $entity->method('id')->willReturnCallback(function () use (&$id) { return $id; }); return $entity;
      });
      $storages[$table] = $storage;
    }
    $this->entities->method('getStorage')->willReturnCallback(static fn(string $type) => $storages[$type]);
    $this->admission = new FreshProofAdmission($this->db, $this->entities, $this->clock, $this->coordinator, $this->policy);
    $tx = $this->db->startTransaction(); $this->admission->admit(1, 1, 'portal.create', NULL); $tx->commitOrRelease(); unset($tx);
    $this->claim = $this->coordinator->claim($this->worker, [WorkerCapabilityPolicy::PROOF]);
    $slot = ['tool' => 'synthetic-tool', 'adapter' => 'synthetic-adapter', 'max_cost_cents' => 40, 'timeout_seconds' => 60, 'headroom_seconds' => 5];
    $this->catalog = ['synthetic-cost' => $this->policy + ['slots' => ['build' => $slot, 'optional-repair' => $slot]]];
    $this->completion = ['synthetic-cost' => ['operation_policy_sha256' => Contract::hash($this->catalog['synthetic-cost']), 'required_success_slots' => ['build']]];
    $this->journal = new ProofOperationJournal($this->db, $this->clock, $this->admission, $this->coordinator, $this->catalog,
      function () { self::assertFalse($this->db->inTransaction()); return TRUE; }, function () { self::assertFalse($this->db->inTransaction()); return TRUE; });
    $input = ['input_sha256' => hash('sha256', 'synthetic input'), 'prompt_sha256' => hash('sha256', 'synthetic prompt'), 'asset_ids' => [1]];
    $permit = $this->journal->authorizeSubmission(1, 1, $this->worker, $this->claim['lease_token'], 1, [WorkerCapabilityPolicy::PROOF], 'build', $input);
    $this->operationReceipt = ['operation_id' => $permit['operation_id'], 'input_sha256' => $input['input_sha256'], 'adapter' => 'synthetic-adapter', 'provider_request_id' => 'synthetic-receipt',
      'outcome' => 'succeeded', 'cost_status' => 'unknown', 'actual_cost_cents' => NULL, 'checkpoint' => [['name' => 'synthetic-metadata', 'sha256' => hash('sha256', 'synthetic'), 'bytes' => 9]]];
    $this->journal->recordReceipt($permit['operation_id'], 'synthetic-recovery', $this->operationReceipt);
    $this->telemetry = new BuildTelemetryService($this->db, $this->clock);
    $this->temporary = realpath(sys_get_temp_dir()) . '/managed-import-test-' . bin2hex(random_bytes(12)); mkdir($this->temporary, 0700);
    foreach (['sources', 'packages', 'web'] as $dir) mkdir($this->temporary . '/' . $dir, 0700);
    $logo = getenv('FAMTASTIC_TEST_CANONICAL_LOGO') ?: dirname(__DIR__, 9) . '/frontend/public/brand/famtastic-designs-logo-v1.png';
    self::assertFileExists($logo, 'Supply the existing canonical PNG for a sparse checkout.');
    $logo = realpath($logo); // Test-only trusted /tmp override, never a production path exemption.
    self::assertSame(2020725, filesize($logo)); self::assertSame('ebb0477344132d32e449ba19e2b622921585aa71af0decdbcf8abfbe033fa950', hash_file('sha256', $logo));
    $this->store = new ManagedProofArtifactStore($this->temporary . '/sources', $this->temporary . '/web');
    $this->package = new ManagedProofArtifactPackage($this->store, new ManagedProofPackageFiles($this->temporary . '/packages', $this->temporary . '/web'), $logo);
    $campaign = $this->rows('proof_campaign')[0]; $callback = ProofArtifactInputs::input();
    $callback['campaign_id'] = $campaign['campaign_id']; $callback['job_id'] = $campaign['studio_job_id'];
    $wire = ProofArtifactInputs::wire($callback);
    $this->source = $this->store->prepare($wire, ProofCallbackArtifacts::normalize($callback['variants'], ProofArtifactInputs::DIRECTIONS));
    $this->preparedPackage = $this->package->prepare(basename($this->source['directory']), $this->source['manifest_sha256']);
    $dna = ['schema' => 'famtastic.build-dna.v1', 'build_id' => 'synthetic-managed-build', 'classification' => 'synthetic_only_not_deliverable',
      'created_at' => gmdate('Y-m-d\TH:i:s\Z', $this->now), 'repository' => ['name' => 'synthetic', 'revision' => str_repeat('d', 40)],
      'recipe' => ['routine' => 'website_proof.generate.v1', 'version' => '1', 'build_class' => 'synthetic', 'proof_recipe' => $this->policy['recipe']],
      'run' => ['source_lane' => 'managed_proof', 'job_id' => 1, 'website_request_id' => 1, 'customer_id' => 1, 'organization_id' => 2, 'prospect_id' => 3,
        'proof_campaign_id' => 1, 'campaign_id' => $campaign['campaign_id'], 'callback_event_id' => $callback['event_id'],
        'started_at' => gmdate('Y-m-d\TH:i:s\Z', $this->now - 10), 'completed_at' => gmdate('Y-m-d\TH:i:s\Z', $this->now)],
      'stages' => [['stage_id' => 'synthetic-build', 'attempt' => 1, 'capability' => 'synthetic', 'execution' => ['provider' => ['id' => 'synthetic'],
        'model' => ['status' => 'not_applicable'], 'timing' => ['status' => 'synthetic'], 'cost' => ['status' => 'unknown']], 'result' => ['status' => 'synthetic']]],
      'artifacts' => [['role' => 'callback', 'path' => 'callback.json', 'sha256' => hash('sha256', $wire)]],
      'retrieval' => ['filesystem' => [], 'database' => [], 'site_studio' => []], 'integrity' => ['artifact_hash_algorithm' => 'sha256']];
    $admission = FreshProofBinding::event($this->db, 1); $job = $this->rows('famtastic_job')[0];
    $this->provenance = ['schema' => 'famtastic.managed-proof-provenance.v1', 'id' => 'synthetic-provenance', 'job_id' => 1, 'request_id' => 1,
      'payload_sha256' => hash('sha256', $job['payload']), 'admission_sha256' => hash('sha256', $admission['payload']),
      'prepared_bundle_id' => basename($this->source['directory']), 'prepared_manifest_sha256' => $this->source['manifest_sha256'],
      'package_id' => $this->preparedPackage['package_id'], 'package_manifest_sha256' => $this->preparedPackage['package_manifest_sha256'],
      'callback_sha256' => hash('sha256', $wire), 'producer_ids' => ['automation:synthetic-mac'], 'input' => $input,
      'operations' => $this->operationFacts(), 'build_dna' => $dna];
    $this->importer = $this->makeImporter(); $this->db->observed = [];
  }
  protected function makeImporter(bool $configured = TRUE, bool $ackConfigured = TRUE): ManagedProofImporter {
    return new ManagedProofImporter($this->db, $this->entities, $this->clock, $this->admission, $this->coordinator, $this->journal, $this->telemetry,
      $configured ? $this->store : NULL, $configured ? $this->package : NULL,
      $configured ? function () { self::assertFalse($this->db->inTransaction()); if (!$this->provenanceAllowed) throw new \RuntimeException('Synthetic provenance denied.');
        if ($this->afterVerification) ($this->afterVerification)(); return $this->provenance; } : NULL,
      $configured ? $this->completion : [], $ackConfigured ? function (string $actor, array $r) { self::assertFalse($this->db->inTransaction()); return $this->ackAllowed && $actor === 'synthetic-authorized-account' && $r['customer_id'] === 1; } : NULL);
  }
  protected function import(): array {
    return $this->importer->importPrepared(1, 1, $this->worker, $this->claim['lease_token'], $this->claim['attempt'], [WorkerCapabilityPolicy::PROOF],
      $this->preparedPackage['package_id'], $this->preparedPackage['package_manifest_sha256'], basename($this->source['directory']), $this->source['manifest_sha256'],
      $this->provenance['id'], Contract::hash($this->provenance));
  }
  protected function operationFacts(): array {
    $facts = [];
    foreach ($this->rows('famtastic_proof_operation') as $r) {
      $i = json_decode($r['identity_wire'], TRUE);
      $facts[$r['operation_id']] = ['identity_sha256' => $r['identity_sha256'], 'receipt_sha256' => $r['receipt_sha256'],
        'producer_id' => Contract::workerIdentity($i['producer_id']), 'recorder_id' => Contract::workerIdentity($r['recorder_id'])];
    }
    ksort($facts); return $facts;
  }
  protected function rows(string $table): array { return $this->db->select($table, 'r')->fields('r')->execute()->fetchAll(\PDO::FETCH_ASSOC); }
  protected function snapshot(): array {
    $state = [];
    foreach (['famtastic_project_request', 'famtastic_customer', 'famtastic_membership', 'famtastic_request_asset', 'proof_campaign', 'proof_variant', 'famtastic_build_run',
      'famtastic_event', 'famtastic_job', 'famtastic_worker_claim', 'famtastic_worker_budget', 'famtastic_proof_operation', 'famtastic_notification_outbox', 'famtastic_portal_activity'] as $t) $state[$t] = $this->rows($t);
    return $state;
  }
  protected function reject(callable $call, string $message): void {
    $error = NULL; try { $call(); } catch (\Throwable $e) { $error = $e; }
    self::assertNotNull($error, 'Expected rejection: ' . $message); self::assertStringContainsString($message, $error->getMessage());
  }
  private function uuid(int $n): string { return '00000000-0000-0000-0000-' . str_pad((string) $n, 12, '0', STR_PAD_LEFT); }
  protected function tearDown(): void {
    // Only this test's exact random root, no adoption or external cleanup.
    if (isset($this->temporary) && is_dir($this->temporary)) {
      $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->temporary, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
      foreach ($walk as $file) { if ($file->isLink() || !$file->isDir()) unlink($file->getPathname()); else rmdir($file->getPathname()); }
      rmdir($this->temporary);
    }
    new Settings([]); parent::tearDown();
  }
}
