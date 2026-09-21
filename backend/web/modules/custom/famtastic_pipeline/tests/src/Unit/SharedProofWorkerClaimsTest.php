<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Controller\WorkerCoordinatorController;
use Drupal\famtastic_pipeline\Service\WorkerCapabilityPolicy;
use Drupal\famtastic_pipeline\Service\WorkerCoordinator;
use Drupal\famtastic_pipeline\Service\WorkerCoordinatorSchema;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__, 3) . '/famtastic_pipeline.install';

/** Paused fake clock, actual SQLite/controller, no creative/import/provider code. */
final class SharedProofWorkerClaimsTest extends UnitTestCase {
  private Connection $db;
  private TimeInterface $clock;
  private LockBackendInterface $lock;
  private WorkerCoordinator $coordinator;
  private int $now = 1790010000;
  private array $payload;
  private array $catalog;
  private array $lockNames = [];
  private const CAPS = [WorkerCapabilityPolicy::PROOF];

  protected function setUp(): void {
    parent::setUp();
    $o = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->db = new Connection(Connection::open($o), $o);
    foreach (WorkerCoordinatorSchema::tables() + ['famtastic_job' => _famtastic_pipeline_automation_schema()['famtastic_job']] as $name => $schema) $this->db->schema()->createTable($name, $schema);
    $this->clock = $this->createMock(TimeInterface::class);
    $this->clock->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $this->lock = $this->createMock(LockBackendInterface::class);
    $this->lock->method('acquire')->willReturnCallback(function ($name) { $this->lockNames[] = $name; return TRUE; });
    $brief = ['business_name' => 'Synthetic proof claims only'];
    $this->payload = [
      'schema' => 'famtastic.proof-worker-input.v1', 'routine' => 'website_proof.generate.v1', 'brief_version' => 1,
      'website_request_id' => 17, 'website_request_public_id' => '00000000-0000-0000-0000-000000000017',
      'customer_id' => 21, 'organization_id' => 22, 'prospect_id' => 23, 'proof_campaign_id' => 24,
      'campaign_id' => 'synthetic-campaign', 'studio_job_id' => 'synthetic-opaque-run',
      'brief_sha256' => hash('sha256', json_encode($brief, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
      'website_discovery_v3' => $brief, 'request_binding_sha256' => str_repeat('a', 64), 'asset_authority_sha256' => str_repeat('b', 64),
      'direction_ids' => ['a', 'b', 'c'], 'recipe' => ['id' => 'synthetic-no-provider', 'revision' => str_repeat('c', 40), 'sha256' => str_repeat('d', 64)],
      'tool_allowlist' => ['synthetic-record-only'],
      'cost_policy' => ['id' => 'synthetic-hold-only-v1', 'revision' => str_repeat('e', 40), 'currency' => 'USD', 'max_calls' => 1, 'max_cost_cents' => 100, 'reservation_cents' => 100],
    ];
    $this->catalog = ['synthetic-hold-only-v1' => array_intersect_key($this->payload, array_flip(['recipe', 'tool_allowlist', 'cost_policy']))];
    $this->coordinator = new WorkerCoordinator($this->db, $this->clock, $this->lock, $this->catalog);
    $this->insertProof();
    $this->installController();
  }

  protected function tearDown(): void { new Settings([]); parent::tearDown(); }

  private function insertProof(): void {
    $this->db->insert('famtastic_job')->fields(['id' => 901, 'prospect_id' => 23, 'job_key' => $this->jobKey(), 'job_type' => 'proof.generate',
      'status' => 'queued', 'attempts' => 0, 'max_attempts' => 5, 'available_at' => $this->now, 'payload' => $this->wire(), 'created' => $this->now, 'changed' => $this->now])->execute();
  }
  private function jobKey(): string { return 'website_proof.generate.v1:request:17:brief:' . $this->payload['brief_sha256']; }
  private function wire(): string { return json_encode($this->payload, JSON_THROW_ON_ERROR); }
  private function enroll(int $reserve = 100): array { return $this->coordinator->enrollProof(901, $this->jobKey(), hash('sha256', $this->wire()), $reserve); }
  private function claim(string $worker = 'mac-creative'): ?array { return $this->coordinator->claim($worker, self::CAPS); }
  private function row(string $table, string $field = 'job_id', int $id = 901): array {
    return $this->db->select($table, 't')->fields('t')->condition($field, $id)->execute()->fetchAssoc();
  }
  private function snapshot(): array {
    $out = [];
    foreach (['famtastic_job', 'famtastic_worker_claim', 'famtastic_worker_budget'] as $table) $out[$table] = $this->db->select($table, 't')->fields('t')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return $out;
  }
  private function rejects(callable $operation, string $message = ''): void {
    $before = $this->snapshot();
    try { $operation(); self::fail('Expected fail-closed rejection.'); }
    catch (\InvalidArgumentException|\RuntimeException $e) { if ($message) self::assertStringContainsString($message, $e->getMessage()); }
    self::assertSame($before, $this->snapshot());
  }
  private function enrollStatic(): void {
    $wire = json_encode(['packet' => ['build_class' => 'prepayment_selected_direction_staging', 'packet_id' => 'packet', 'idempotency_key' => 'key',
      'continuation' => ['spec' => ['capability_class' => 'static'], 'operation' => 'package_existing', 'requested_next_action' => 'protected_review']]], JSON_THROW_ON_ERROR);
    $this->db->insert('famtastic_job')->fields(['id' => 902, 'job_key' => 'selected:902', 'job_type' => 'site_studio_staging_prepare', 'status' => 'queued',
      'attempts' => 0, 'max_attempts' => 5, 'available_at' => $this->now, 'payload' => $wire, 'created' => $this->now, 'changed' => $this->now])->execute();
    $this->coordinator->enroll(902, 'selected:902', hash('sha256', $wire), 25);
  }

  public function testNoCatalogOrGenericStaticEntryCanEnrollProof(): void {
    $this->rejects(fn() => $this->coordinator->enroll(901, $this->jobKey(), hash('sha256', $this->wire()), 25));
    $this->coordinator = new WorkerCoordinator($this->db, $this->clock, $this->lock);
    $this->rejects(fn() => $this->enroll(), 'cost policy');
    self::assertNull($this->claim());
    self::assertSame('queued', $this->row('famtastic_job', 'id')['status']);
  }

  public function testMacClaimCloudContendsSameBudgetAndLock(): void {
    self::assertSame('enrolled', $this->enroll()['status']);
    self::assertSame('already_enrolled', $this->enroll()['status']);
    self::assertSame(0, $this->coordinator->health()['reserved_cents']);
    $c = $this->claim();
    self::assertSame(180, $c['lease_until'] - $this->now);
    self::assertSame(1800, $c['execution_deadline'] - $this->now);
    self::assertSame(1830, (int) $this->row('famtastic_worker_claim')['attempt_deadline'] - $this->now);
    self::assertSame(30, $c['heartbeat_seconds']);
    self::assertSame(WorkerCapabilityPolicy::PROOF_POLICY, $c['policy_version']);
    self::assertNull($this->claim('cloud-creative'));
    self::assertSame(100, $this->coordinator->health()['reserved_cents']);
    self::assertSame(['famtastic:bounded-worker-coordinator:v1'], array_values(array_unique($this->lockNames)));
    self::assertSame(hash('sha256', $c['lease_token']), $this->row('famtastic_worker_claim')['token_hash']);
  }

  public function testStaticDefaultsFilterProofAndKeepOriginalProfile(): void {
    $this->enroll(); $this->enrollStatic();
    $c = $this->coordinator->claim('drupal-static-dispatch');
    self::assertSame(902, $c['job_id']);
    self::assertSame(90, $c['lease_until'] - $this->now);
    self::assertSame(300, $c['execution_deadline'] - $this->now);
    self::assertSame(330, (int) $this->row('famtastic_worker_claim', 'job_id', 902)['attempt_deadline'] - $this->now);
    self::assertNull($this->claim());
    $result = ['status' => 'accepted_waiting_callback', 'receipt_id' => 'receipt', 'packet_id' => 'packet', 'idempotency_key' => 'key'];
    self::assertSame('handoff_completed', $this->coordinator->finish(902, 'drupal-static-dispatch', $c['lease_token'], $result)['status']);
    self::assertTrue($this->coordinator->finish(902, 'drupal-static-dispatch', $c['lease_token'], $result)['duplicate']);
    self::assertSame(901, $this->claim()['job_id']);
    self::assertSame(125, $this->coordinator->health()['reserved_cents']);
  }

  public function testProofOnlyCannotTakeStaticOrReviewCapability(): void {
    $this->enrollStatic();
    self::assertNull($this->claim());
    self::assertNull($this->coordinator->claim('qa-only', ['proof.review']));
    self::assertSame(0, $this->coordinator->health()['reserved_cents']);
  }

  public function testPausedClockRenewalNeverExtendsExecutionOrReplacementFence(): void {
    $this->enroll(); $c = $this->claim(); $start = $this->now;
    for ($elapsed = 30; $elapsed <= 1770; $elapsed += 30) {
      $this->now = $start + $elapsed;
      $r = $this->coordinator->renew(901, 'mac-creative', $c['lease_token'], self::CAPS, 1);
      self::assertSame(min($this->now + 180, $start + 1800), $r['lease_until']);
      self::assertSame($start + 1830, (int) $this->row('famtastic_worker_claim')['attempt_deadline']);
    }
    $this->now = $start + 1800;
    $this->rejects(fn() => $this->coordinator->renew(901, 'mac-creative', $c['lease_token'], self::CAPS, 1));
    self::assertNull($this->claim('cloud-creative'));
    $this->now = $start + 1830;
    self::assertSame(2, $this->claim('cloud-creative')['attempt']);
  }

  public function testStaleGenerationAndTokenCannotRenewFailOrFinish(): void {
    $this->enroll(); $old = $this->claim();
    $this->now += 181;
    self::assertNull($this->claim('cloud-creative'));
    $this->now += 1649;
    $new = $this->claim();
    self::assertSame(2, $new['attempt']);
    self::assertNotSame($old['lease_token'], $new['lease_token']);
    $this->rejects(fn() => $this->coordinator->renew(901, 'mac-creative', $old['lease_token'], self::CAPS, 1));
    $this->rejects(fn() => $this->coordinator->renew(901, 'mac-creative', $new['lease_token'], self::CAPS, 1), 'generation');
    $this->rejects(fn() => $this->coordinator->renew(901, 'mac-creative', $new['lease_token'], self::CAPS), 'generation');
    $this->rejects(fn() => $this->coordinator->fail(901, 'mac-creative', $old['lease_token'], self::CAPS, 2));
    $this->rejects(fn() => $this->coordinator->renew(901, 'cloud-creative', $new['lease_token'], self::CAPS, 2));
    $this->rejects(fn() => $this->coordinator->renew(901, 'mac-creative', $new['lease_token']));
    self::assertSame(200, $this->coordinator->health()['reserved_cents']);
  }

  public function testProofCannotFinishEvenWithSelectedReceiptOrForgedCompletedRow(): void {
    $this->enroll(); $c = $this->claim();
    $r = ['status' => 'accepted_waiting_callback', 'receipt_id' => 'receipt', 'packet_id' => 'packet', 'idempotency_key' => 'key'];
    $this->rejects(fn() => $this->coordinator->finish(901, 'mac-creative', $c['lease_token'], $r, self::CAPS, 1), 'authoritative importer');
    self::assertSame('worker_running', $this->row('famtastic_job', 'id')['status']);
    $this->db->update('famtastic_worker_claim')->fields(['state' => 'handoff_completed', 'result_sha256' => hash('sha256', json_encode($r, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))])->execute();
    $this->rejects(fn() => $this->coordinator->finish(901, 'mac-creative', $c['lease_token'], $r, self::CAPS, 1), 'authoritative importer');
  }

  public function testMaxThreeAttemptsAndUnknownHoldsRetained(): void {
    $this->enroll();
    for ($i = 1; $i <= 3; $i++) {
      $c = $this->claim(); self::assertSame($i, $c['attempt']);
      $r = $this->coordinator->fail(901, 'mac-creative', $c['lease_token'], self::CAPS, $i);
      self::assertSame($i === 3 ? 'exception' : 'retry', $r['status']);
      self::assertNull($this->claim('cloud-creative'));
      $this->now += 1830;
    }
    self::assertNull($this->claim());
    self::assertSame('failed', $this->row('famtastic_job', 'id')['status']);
    self::assertSame(300, $this->coordinator->health()['reserved_cents']);
  }

  public function testSharedBudgetStopsBeforeClaimAndDoesNotReleaseEarlierHolds(): void {
    $this->enroll();
    $this->db->insert('famtastic_worker_budget')->fields(['reservation_key' => 'prior', 'month' => gmdate('Y-m', $this->now), 'job_id' => 1, 'attempt' => 1, 'reserved_cents' => 1925, 'created' => $this->now])->execute();
    $this->rejects(fn() => $this->claim(), 'worker_budget_exhausted');
    self::assertSame(2000, $this->coordinator->health()['stop_cents']);
    self::assertSame(2500, $this->coordinator->health()['authorized_monthly_cents']);
  }

  #[DataProvider('invalidPayloads')]
  public function testStrictFrozenInputAndReviewedCostPolicy(string $field, mixed $value): void {
    $parts = explode('.', $field); $target =& $this->payload;
    foreach ($parts as $part) $target =& $target[$part];
    $target = $value;
    $this->db->update('famtastic_job')->fields(['payload' => $this->wire()])->condition('id', 901)->execute();
    $this->rejects(fn() => $this->enroll());
  }
  public static function invalidPayloads(): iterable {
    foreach (['schema', 'routine', 'website_request_id', 'website_request_public_id', 'customer_id', 'organization_id', 'prospect_id', 'proof_campaign_id',
      'campaign_id', 'studio_job_id', 'brief_sha256', 'request_binding_sha256', 'asset_authority_sha256', 'recipe.id', 'recipe.sha256', 'recipe.revision'] as $field) yield $field => [$field, ''];
    yield 'version' => ['brief_version', 2];
    yield 'integer not string' => ['customer_id', '21'];
    yield 'changed brief' => ['website_discovery_v3', ['business_name' => 'changed']];
    yield 'six directions' => ['direction_ids', ['a', 'b', 'c', 'd', 'e', 'f']];
    yield 'tools missing' => ['tool_allowlist', []];
    yield 'tool substitution' => ['tool_allowlist', ['real-provider']];
    yield 'duplicate tool' => ['tool_allowlist', ['synthetic-record-only', 'synthetic-record-only']];
    yield 'unknown cost' => ['cost_policy.id', 'unreviewed'];
    yield 'no calls bound' => ['cost_policy.max_calls', 0];
    yield 'changed reserve' => ['cost_policy.reservation_cents', 25];
    yield 'cost overflow' => ['cost_policy.max_cost_cents', 101];
    yield 'unfrozen extra' => ['provider_url', 'https://forbidden.example.test'];
  }

  #[DataProvider('badJobs')]
  public function testWrongJobHistoricalAndReservationInputsFailClosed(array $fields, int $reserve): void {
    $this->db->update('famtastic_job')->fields($fields)->condition('id', 901)->execute();
    $this->rejects(fn() => $this->enroll($reserve));
  }
  public static function badJobs(): iterable {
    foreach ([['job_type' => 'site_studio_staging_prepare'], ['job_key' => 'wrong'], ['prospect_id' => 99], ['attempts' => 1], ['status' => 'failed']] as $i => $fields) yield 'job ' . $i => [$fields, 100];
    foreach ([0, 25, 251] as $reserve) yield 'reserve ' . $reserve => [['status' => 'queued'], $reserve];
  }

  public function testStoredProfileCorruptionOrRemovedPolicyCannotReserve(): void {
    $this->enroll();
    $this->db->update('famtastic_worker_claim')->fields(['policy_version' => WorkerCoordinator::POLICY])->execute();
    $this->rejects(fn() => $this->claim(), 'policy mismatch');
    $this->db->update('famtastic_worker_claim')->fields(['policy_version' => WorkerCapabilityPolicy::PROOF_POLICY])->execute();
    $this->coordinator = new WorkerCoordinator($this->db, $this->clock, $this->lock);
    $this->rejects(fn() => $this->claim(), 'cost policy');
  }

  #[DataProvider('unboundedPolicies')]
  public function testEvenInjectedCatalogCannotExceedSourceBounds(string $field, mixed $value): void {
    $this->payload['cost_policy'][$field] = $value;
    $this->catalog['synthetic-hold-only-v1']['cost_policy'] = $this->payload['cost_policy'];
    $this->coordinator = new WorkerCoordinator($this->db, $this->clock, $this->lock, $this->catalog);
    $this->db->update('famtastic_job')->fields(['payload' => $this->wire()])->condition('id', 901)->execute();
    $this->rejects(fn() => $this->enroll(), 'cost policy');
  }
  public static function unboundedPolicies(): iterable {
    foreach ([0, 33, '1'] as $i => $value) yield 'calls ' . $i => ['max_calls', $value];
    foreach ([0, 101, '100'] as $i => $value) yield 'cost ' . $i => ['max_cost_cents', $value];
    yield 'currency' => ['currency', 'EUR'];
    yield 'revision' => ['revision', 'unreviewed'];
  }

  public function testMonthRolloverNeverRefundsOldUnknownHold(): void {
    $this->enroll(); $c = $this->claim();
    $month = gmdate('Y-m', $this->now);
    $this->coordinator->fail(901, 'mac-creative', $c['lease_token'], self::CAPS, 1);
    $this->now += 32 * 86400;
    self::assertSame(2, $this->claim('cloud-creative')['attempt']);
    $holds = $this->db->select('famtastic_worker_budget', 'b')->fields('b')->orderBy('attempt')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    self::assertCount(2, $holds);
    self::assertSame($month, $holds[0]['month']);
    self::assertSame(100, (int) $holds[0]['reserved_cents']);
    self::assertNotSame($holds[0]['month'], $holds[1]['month']);
  }

  public function testProofEnrollmentRollsBackClaimWhenQueueTransitionFails(): void {
    $this->db->query("CREATE TRIGGER reject_proof_enrollment BEFORE UPDATE ON famtastic_job WHEN NEW.status = 'worker_queued' BEGIN SELECT RAISE(ABORT, 'synthetic_enrollment_failure'); END", [], ['allow_delimiter_in_query' => TRUE]);
    $before = $this->snapshot();
    try { $this->enroll(); self::fail('Expected transactional failure.'); }
    catch (\Exception $e) { self::assertStringContainsString('synthetic_enrollment_failure', $e->getMessage()); }
    self::assertSame($before, $this->snapshot());
  }

  private function installController(array $creativeCaps = self::CAPS): void {
    new Settings(['famtastic_bounded_workers_enabled' => TRUE, 'famtastic_worker_registry' => [
      'mac-creative' => ['secret' => str_repeat('x', 32), 'capabilities' => $creativeCaps],
      'cloud-creative' => ['secret' => str_repeat('x', 32), 'capabilities' => self::CAPS],
      'static-only' => ['secret' => str_repeat('x', 32), 'capabilities' => [WorkerCoordinator::CAPABILITY]],
      'review-only' => ['secret' => str_repeat('x', 32), 'capabilities' => ['proof.review']],
    ]]);
    $container = new ContainerBuilder();
    $container->set('famtastic_pipeline.worker_coordinator', $this->coordinator);
    $container->set('famtastic_pipeline.pilot_exact_dispatch_lock', new class { public function isActive(): bool { return FALSE; } });
    $container->set('famtastic_pipeline.customer_portal', new class {
      public function assertCurrentSelectedStagingPacket(array $packet): void {}
      public function releaseWebsiteRequestProofAfterQa(): never { throw new \RuntimeException('Reviewer service not exercised here.'); }
    });
    \Drupal::setContainer($container);
  }
  private function http(string $operation, string $worker, array $body = []): array {
    $wire = json_encode($body, JSON_THROW_ON_ERROR); $nonce = bin2hex(random_bytes(16)); $timestamp = (string) time();
    $path = '/api/pipeline/worker/' . $operation;
    $request = Request::create('https://authority.example.test/web' . $path, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $wire);
    $signature = hash_hmac('sha256', implode("\n", ['POST', $path, $worker, $timestamp, $nonce, hash('sha256', $wire)]), str_repeat('x', 32));
    $request->headers->add(['X-FAMtastic-Worker' => $worker, 'X-FAMtastic-Timestamp' => $timestamp, 'X-FAMtastic-Nonce' => $nonce, 'X-FAMtastic-Signature' => 'sha256=' . $signature]);
    $response = (new WorkerCoordinatorController())->handle($request, $operation);
    return [$response->getStatusCode(), json_decode($response->getContent(), TRUE)];
  }

  public function testSignedControllerUsesRegistryNotBodyAndKeepsReviewSeparate(): void {
    $this->enroll();
    self::assertSame([200, ['claim' => NULL]], $this->http('claim', 'static-only', ['capabilities' => self::CAPS]));
    self::assertSame(403, $this->http('claim', 'review-only', ['capabilities' => self::CAPS])[0]);
    self::assertSame(403, $this->http('review', 'mac-creative', ['capabilities' => ['proof.review']])[0]);
    self::assertSame(409, $this->http('claim', 'static-only', ['capability' => WorkerCapabilityPolicy::PROOF])[0]);
    [$status, $body] = $this->http('claim', 'mac-creative', ['capability' => WorkerCapabilityPolicy::PROOF]);
    self::assertSame(200, $status); $c = $body['claim'];
    self::assertSame(WorkerCapabilityPolicy::PROOF, $c['capability']);
    self::assertSame([200, ['claim' => NULL]], $this->http('claim', 'cloud-creative', ['capability' => WorkerCapabilityPolicy::PROOF]));
    $args = ['job_id' => 901, 'attempt' => 1, 'lease_token' => $c['lease_token'], 'capability' => WorkerCoordinator::CAPABILITY, 'lease_seconds' => 99999, 'execution_deadline' => 9999999999];
    $this->now += 30;
    self::assertSame([200, ['result' => ['lease_until' => $this->now + 180]]], $this->http('renew', 'mac-creative', $args));
    self::assertSame(409, $this->http('renew', 'cloud-creative', $args)[0]);
    self::assertSame(409, $this->http('renew', 'mac-creative', array_replace($args, ['attempt' => 2]))[0]);
    self::assertSame(409, $this->http('renew', 'mac-creative', array_replace($args, ['attempt' => '1']))[0]);
    self::assertSame(409, $this->http('finish', 'mac-creative', $args + ['result' => ['status' => 'accepted_waiting_callback']])[0]);
    $this->installController([WorkerCoordinator::CAPABILITY]);
    self::assertSame(409, $this->http('renew', 'mac-creative', $args)[0]);
    self::assertSame('leased', $this->row('famtastic_worker_claim')['state']);
  }

  public function testMultiCapabilityIdentityStillDefaultsToStaticAndBodyCannotChangeItsProfile(): void {
    $this->enroll(); $this->enrollStatic();
    $this->installController([WorkerCoordinator::CAPABILITY, WorkerCapabilityPolicy::PROOF]);
    [$status, $body] = $this->http('claim', 'mac-creative');
    self::assertSame(200, $status);
    self::assertSame(902, $body['claim']['job_id']);
    self::assertSame(90, $body['claim']['lease_until'] - $this->now);
    $this->now += 30;
    $args = ['job_id' => 902, 'lease_token' => $body['claim']['lease_token'], 'capability' => WorkerCapabilityPolicy::PROOF, 'lease_seconds' => 180];
    self::assertSame([200, ['result' => ['lease_until' => $this->now + 90]]], $this->http('renew', 'mac-creative', $args));
  }

  public function testActiveProofRechecksStoredPolicyAndImmutablePayload(): void {
    $this->enroll(); $c = $this->claim();
    $this->db->update('famtastic_worker_claim')->fields(['policy_version' => WorkerCoordinator::POLICY])->execute();
    $this->rejects(fn() => $this->coordinator->renew(901, 'mac-creative', $c['lease_token'], self::CAPS, 1), 'policy mismatch');
    $this->db->update('famtastic_worker_claim')->fields(['policy_version' => WorkerCapabilityPolicy::PROOF_POLICY])->execute();
    $this->payload['customer_id'] = 99;
    $this->db->update('famtastic_job')->fields(['payload' => $this->wire()])->condition('id', 901)->execute();
    $this->rejects(fn() => $this->coordinator->renew(901, 'mac-creative', $c['lease_token'], self::CAPS, 1), 'changed payload');
  }
}
