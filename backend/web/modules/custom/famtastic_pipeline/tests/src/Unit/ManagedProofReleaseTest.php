<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Controller\WorkerCoordinatorController;
use Drupal\famtastic_pipeline\Service\{AutomatedProofPolicy, AutomatedProofRelease, CustomerPortalService, ManagedProofArtifactPackage,
  ManagedProofPackageFiles, ManagedProofReader, ManagedProofRelease, OperationalLedger, WorkerRequestAuthenticator};
use Drupal\Tests\famtastic_pipeline\Unit\Fixtures\ManagedProofImportFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/Fixtures/ManagedProofImportFixture.php';

/** Real import/package/release/outbox; identity/provenance/QA content are synthetic. */
final class ManagedProofReleaseTest extends ManagedProofImportFixture {
  private CustomerPortalService $portal;
  private ManagedProofReader $reader;
  private ManagedProofRelease $managedRelease;
  private object $principal;
  private string $reviewer = 'automation:synthetic-independent';
  private array $committed;
  private array $context;
  private array $evidence;
  private array $notice;
  private bool $evidenceAllowed = TRUE;
  private bool $replayAllowed = TRUE;
  private ?\Closure $afterEvidence = NULL;
  private ?WorkerRequestAuthenticator $signedAuth = NULL;
  private array $research = ['overview' => 'Synthetic research, not a customer deliverable.',
    'direction_rationale' => ['a' => 'Synthetic A.', 'b' => 'Synthetic B.', 'c' => 'Synthetic C.'],
    'sources' => ['https://example.test/fixture'], 'researched_at' => '2026-09-22'];

  protected function setUp(): void {
    parent::setUp();
    $this->db->query('CREATE TABLE users_field_data (uid INTEGER, status INTEGER, default_langcode INTEGER)');
    $this->db->insert('users_field_data')->fields(['uid' => 1, 'status' => 1, 'default_langcode' => 1])->execute();
    $this->db->schema()->createTable('famtastic_website_proof_research_snapshot', _famtastic_pipeline_website_proof_research_snapshot_schema());
    $this->committed = $this->import();
    $this->principal = new \stdClass();
    $logo = realpath(getenv('FAMTASTIC_TEST_CANONICAL_LOGO') ?: dirname(__DIR__, 8) . '/frontend/public/brand/famtastic-designs-logo-v1.png');
    $this->reader = new ManagedProofReader($this->db, $this->clock,
      fn(\Closure $resolver) => new ManagedProofArtifactPackage($this->store,
        new ManagedProofPackageFiles($this->temporary . '/packages', $this->temporary . '/web'), $logo, $resolver),
      fn(object $principal, array $receipt, array $request) => $this->signedAuth
        ? $this->signedAuth->reviewer($principal, (int) $request['id'])
        : ($principal === $this->principal ? $this->reviewer : NULL),
      fn(array $committed, array $request, AccountInterface $principal) => $this->managedRelease->customerGrant($committed, $request, $principal));
    $this->portal = (new \ReflectionClass(CustomerPortalService::class))->newInstanceWithoutConstructor();
    foreach (['database' => $this->db, 'time' => $this->clock] as $p => $v) (new \ReflectionProperty($this->portal, $p))->setValue($this->portal, $v);
    $this->managedRelease = $this->makeRelease();
    $existing = new AutomatedProofRelease($this->db, $this->entities, $this->clock, new OperationalLedger($this->db, $this->clock),
      $this->portal, $this->reader, $this->managedRelease);
    $container = new ContainerBuilder(); $container->set('famtastic_pipeline.automated_proof_release', $existing); \Drupal::setContainer($container);
    $this->context = $this->portal->websiteRequestAutomatedProofQaContext(1, $this->research, $this->principal);
    $this->evidence = $this->context + ['producer' => 'automation:synthetic-mac', 'scope_in_bounds' => TRUE, 'exceptions' => []];
    mkdir($this->temporary . '/qa', 0700);
    foreach (AutomatedProofPolicy::CHECKS as $check) {
      $bytes = 'Explicitly synthetic QA bytes for ' . $check;
      file_put_contents($this->temporary . '/qa/' . $check, $bytes);
      $this->evidence['checks'][$check] = ['passed' => TRUE, 'evidence_ref' => 'evidence:synthetic/' . $check, 'evidence_sha256' => hash('sha256', $bytes)];
    }
    $row = $this->rows('famtastic_project_request')[0];
    $this->notice = ['notification_key' => 'website-request:1:proofs:1:qa-v1', 'recipient' => 'synthetic@example.test',
      'subject' => 'Synthetic proof notice — never send',
      'body' => "Your synthetic fixture proofs. Always FAMtastic, Shay\nhttps://famtasticdesigns.com/portal/?section=projects&request=" . $row['public_id']];
  }

  private function makeRelease(bool $withEvidence = TRUE, bool $withReplay = TRUE): ManagedProofRelease {
    return new ManagedProofRelease($this->db, $this->clock, $this->portal, $this->reader,
      $withEvidence ? function(array $evidence, array $context): bool {
        self::assertFalse($this->db->inTransaction(), 'Evidence I/O must precede root locks.');
        if (!$this->evidenceAllowed || $context !== $this->context) return FALSE;
        foreach (AutomatedProofPolicy::CHECKS as $check) {
          $path = $this->temporary . '/qa/' . $check;
          if (!is_file($path) || $evidence['checks'][$check]['evidence_ref'] !== 'evidence:synthetic/' . $check
            || hash_file('sha256', $path) !== $evidence['checks'][$check]['evidence_sha256']) return FALSE;
        }
        if ($this->afterEvidence) { $callback = $this->afterEvidence; $this->afterEvidence = NULL; $callback(); }
        return TRUE;
      } : NULL,
      $withReplay ? function(object $principal, array $committed, array $request): ?string {
        self::assertFalse($this->db->inTransaction());
        if ($this->signedAuth) return $this->signedAuth->reviewer($principal, (int) $request['id']);
        return $this->replayAllowed && $principal === $this->principal ? $this->reviewer : NULL;
      } : NULL);
  }
  private function deliver(): array {
    return $this->portal->releaseWebsiteRequestProofAfterQa(1, $this->research, $this->evidence, $this->reviewer, $this->notice, $this->principal);
  }
  private function customer(int $uid = 1): AccountInterface {
    $account = $this->createMock(AccountInterface::class); $account->method('isAuthenticated')->willReturn(TRUE); $account->method('id')->willReturn($uid); return $account;
  }
  private function notices(): array { return array_values(array_filter($this->rows('famtastic_notification_outbox'), fn($r) => $r['notification_key'] === $this->notice['notification_key'])); }
  private function configureSignedAuthentication(): void {
    new Settings(['famtastic_bounded_workers_enabled' => TRUE, 'famtastic_worker_registry' => [
      'synthetic-independent' => ['secret' => str_repeat('s', 32), 'capabilities' => ['proof.review']],
    ]]);
    $this->signedAuth = new WorkerRequestAuthenticator($this->coordinator, $this->clock);
    $container = \Drupal::getContainer();
    $container->set('famtastic_pipeline.worker_request_authenticator', $this->signedAuth);
    $container->set('famtastic_pipeline.worker_coordinator', $this->coordinator);
    $container->set('famtastic_pipeline.customer_portal', $this->portal);
    $container->set('famtastic_pipeline.pilot_exact_dispatch_lock', new class { public function isActive(): bool { return FALSE; } });
  }
  private function signedRequest(array $overrides = []): Request {
    $wire = json_encode(array_replace(['request_id' => 1, 'research' => $this->research, 'evidence' => $this->evidence,
      'notification' => $this->notice, 'reviewer' => 'Fritz', 'uid' => 1], $overrides), JSON_THROW_ON_ERROR);
    $path = '/api/pipeline/worker/review'; $nonce = bin2hex(random_bytes(16));
    $request = Request::create('https://authority.example.test/web' . $path, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $wire);
    $signature = hash_hmac('sha256', implode("\n", ['POST', $path, 'synthetic-independent', (string) $this->now, $nonce, hash('sha256', $wire)]), str_repeat('s', 32));
    $request->headers->add(['X-FAMtastic-Worker' => 'synthetic-independent', 'X-FAMtastic-Timestamp' => (string) $this->now,
      'X-FAMtastic-Nonce' => $nonce, 'X-FAMtastic-Signature' => 'sha256=' . $signature]);
    return $request;
  }
  private function deny(callable $call, string $message): void {
    $error = NULL; try { $call(); } catch (\Throwable $e) { if ($e instanceof \PHPUnit\Framework\AssertionFailedError) throw $e; $error = $e; }
    self::assertNotNull($error, 'Expected rejection: ' . $message); self::assertStringContainsString($message, $error->getMessage());
  }

  public function testExistingWrapperAtomicallyReleasesAndQueuesOneBrandedNotice(): void {
    $before = $this->snapshot(); $result = $this->deliver();
    self::assertFalse($this->db->inTransaction()); self::assertFalse($result['duplicate']); self::assertFalse($result['email_sent_by_this_operation']);
    $row = $this->rows('famtastic_project_request')[0];
    self::assertSame('customer_ready', $row['proof_review_status']); self::assertNull($row['proof_approved_by_uid']);
    self::assertSame($this->now, (int) $row['proof_approved_at']);
    $notices = $this->notices(); self::assertCount(1, $notices);
    self::assertSame('customer_proof_ready', $notices[0]['template_id']); self::assertSame(4, (int) $notices[0]['template_version']);
    self::assertSame('queued', $notices[0]['status']); self::assertSame(0, (int) $notices[0]['attempts']); self::assertSame(1, (int) $notices[0]['max_attempts']);
    self::assertSame($this->notice['body'], $notices[0]['body']);
    foreach (['famtastic_job','famtastic_worker_claim','famtastic_worker_budget','famtastic_proof_operation','proof_variant','famtastic_portal_activity'] as $t) self::assertSame($before[$t], $this->rows($t));
    $released = $this->snapshot(); $retry = $this->deliver();
    self::assertTrue($retry['duplicate']); self::assertTrue($retry['historical_acknowledgment_only']); self::assertSame($result['release_sha256'], $retry['release_sha256']);
    self::assertSame($released, $this->snapshot());
  }

  public function testActualStoredReleaseEnablesReaderAndTamperedResearchRevokesIt(): void {
    $this->deliver();
    $handle = $this->reader->context(1, $this->customer(), 'customer');
    $html = $this->reader->readRole($handle, 'a', 'html');
    self::assertSame($this->committed['receipt']['variants']['a']['html_sha256'], $html['sha256']);
    $this->db->update('famtastic_website_proof_research_snapshot')->fields(['snapshot_hash' => str_repeat('f', 64)])->execute();
    $this->deny(fn() => $this->reader->readRole($handle, 'a', 'html'), 'research record differs');
  }

  public function testActualSignedControllerReleasesOnceAndNewNonceAcknowledgesSameRelease(): void {
    $this->configureSignedAuthentication(); $controller = new WorkerCoordinatorController(); $request = $this->signedRequest();
    $response = $controller->handle($request, 'review');
    self::assertSame(200, $response->getStatusCode(), $response->getContent());
    $result = json_decode($response->getContent(), TRUE)['result'];
    self::assertFalse($result['duplicate']); self::assertFalse($result['email_sent_by_this_operation']);
    $event = $this->db->select('famtastic_event', 'e')->fields('e', ['payload'])->condition('event_type', ManagedProofRelease::EVENT)->execute()->fetchField();
    self::assertSame($this->reviewer, json_decode($event, TRUE)['decision']['actor']);
    self::assertCount(1, $this->notices()); self::assertCount(1, $this->rows('famtastic_worker_nonce'));
    $before = $this->snapshot();
    self::assertSame(403, $controller->handle($request, 'review')->getStatusCode());
    self::assertSame($before, $this->snapshot());
    $retry = $controller->handle($this->signedRequest(), 'review');
    self::assertSame(200, $retry->getStatusCode(), $retry->getContent());
    self::assertTrue(json_decode($retry->getContent(), TRUE)['result']['historical_acknowledgment_only']);
    self::assertSame($before, $this->snapshot()); self::assertCount(2, $this->rows('famtastic_worker_nonce'));
    $handle = $this->reader->context(1, $this->customer(), 'customer');
    self::assertSame($this->committed['receipt']['variants']['a']['html_sha256'], $this->reader->readRole($handle, 'a', 'html')['sha256']);
  }

  public function testSignedStringIdCannotBecomeManagedReviewerAuthorityByControllerCast(): void {
    $this->configureSignedAuthentication(); $before = $this->snapshot();
    self::assertSame(409, (new WorkerCoordinatorController())->handle($this->signedRequest(['request_id' => '1']), 'review')->getStatusCode());
    self::assertSame($before, $this->snapshot()); self::assertCount(0, $this->notices());
  }

  public function testSignedAuthorityExpiringDuringEvidenceReadCannotRelease(): void {
    $this->configureSignedAuthentication(); $before = $this->snapshot();
    $this->afterEvidence = function(): void { $this->now += 91; };
    self::assertSame(409, (new WorkerCoordinatorController())->handle($this->signedRequest(), 'review')->getStatusCode());
    self::assertSame($before, $this->snapshot()); self::assertCount(0, $this->notices());
  }

  #[DataProvider('badInputs')]
  public function testUnsafeOrUnretainedReviewHasNoDatabaseEffects(string $case, string $error): void {
    switch ($case) {
      case 'principal': $this->principal = new \stdClass(); // Authenticator closure reads this property, so use a foreign call below.
        break;
      case 'self': $this->reviewer = 'automation:synthetic-mac'; break;
      case 'producer': $this->evidence['producer'] = 'automation:invented'; break;
      case 'receipt': $this->evidence['managed_import']['receipt_sha256'] = str_repeat('f', 64); break;
      case 'producers': $this->evidence['managed_import']['producer_ids'] = []; break;
      case 'research': $this->research['overview'] = 'Changed'; break;
      case 'rights': $this->db->update('famtastic_request_asset')->fields(['status' => 'withdrawn'])->execute(); break;
      case 'blocked': $this->db->update('users_field_data')->fields(['status' => 0])->execute(); break;
      case 'failed': $this->evidence['checks']['mobile']['passed'] = FALSE; break;
      case 'check': unset($this->evidence['checks']['links']); break;
      case 'scope': $this->evidence['exceptions'] = ['merchant']; break;
      case 'false-evidence': $this->evidenceAllowed = FALSE; break;
      case 'missing-file': unlink($this->temporary . '/qa/desktop'); break;
      case 'changed-file': file_put_contents($this->temporary . '/qa/desktop', 'not reviewed'); break;
      case 'recipient': $this->notice['recipient'] = 'foreign@example.test'; break;
      case 'key': $this->notice['notification_key'] = 'foreign'; break;
      case 'admin-link': $this->notice['body'] = 'https://famtasticdesigns.com/web/admin/famtastic/website-request/1'; break;
      case 'extra-link': $this->notice['body'] .= "\nhttps://example.test/extra"; break;
    }
    $before = $this->snapshot();
    $this->deny($case === 'principal' ? fn() => $this->managedRelease->release(1, $this->research, $this->evidence, $this->reviewer, $this->notice, new \stdClass()) : fn() => $this->deliver(), $error);
    self::assertFalse($this->db->inTransaction()); self::assertSame($before, $this->snapshot());
    self::assertCount(0, $this->rows('famtastic_website_proof_research_snapshot'));
  }
  public static function badInputs(): iterable {
    foreach (['principal'=>'unauthenticated','self'=>'producer','producer'=>'producer binding','receipt'=>'import','producers'=>'import',
      'research'=>'QA must bind','rights'=>'asset authority','blocked'=>'account authority','failed'=>'mobile','check'=>'closed schema','scope'=>'exception',
      'false-evidence'=>'unverified','missing-file'=>'unverified','changed-file'=>'unverified','recipient'=>'recipient','key'=>'key',
      'admin-link'=>'portal destination','extra-link'=>'portal destination'] as $case=>$error) yield $case=>[$case,$error];
  }

  public function testOldHumanWritersCannotBypassManagedAtomicRelease(): void {
    $before = $this->snapshot();
    $this->deny(fn() => $this->portal->saveWebsiteRequestProofResearchSnapshot(1,1,$this->research),'commit with its independent');
    $this->deny(fn() => $this->portal->approveWebsiteRequestProof(1,1),'not legacy owner approval');
    self::assertSame($before,$this->snapshot()); self::assertCount(0,$this->rows('famtastic_website_proof_research_snapshot'));
    // Existing normalization strips caller-authored review metadata. Only the
    // actual independent automation identity is attributed by the writer.
    $this->research['reviewed_by']='uid:1'; $this->research['approved_by_uid']=1;
    $this->deliver();
    $snapshot=$this->rows('famtastic_website_proof_research_snapshot')[0];
    self::assertSame(0,(int)$snapshot['approved_by_uid']);
    self::assertSame($this->reviewer,json_decode($snapshot['snapshot_json'],TRUE)['reviewed_by']);
  }

  public function testChangesDuringEvidenceReadRejectBeforeAnyReleaseWrite(): void {
    $this->afterEvidence = fn() => $this->db->update('famtastic_request_asset')->fields(['status' => 'withdrawn'])->execute();
    $this->deny(fn() => $this->deliver(), 'asset authority');
    self::assertCount(0, $this->notices()); self::assertSame('owner_review', $this->rows('famtastic_project_request')[0]['proof_review_status']);
    self::assertSame('withdrawn', $this->rows('famtastic_request_asset')[0]['status']);
  }

  #[DataProvider('lateFailures')]
  public function testLateWriteFailureRollsBackEverything(string $table): void {
    $before = $this->snapshot();
    $this->db->query("CREATE TRIGGER fail_release BEFORE INSERT ON $table BEGIN SELECT RAISE(ABORT, 'synthetic release write failure'); END", [], ['allow_delimiter_in_query' => TRUE]);
    $this->deny(fn() => $this->deliver(), 'synthetic release write failure');
    self::assertFalse($this->db->inTransaction()); self::assertSame($before, $this->snapshot());
    self::assertCount(0, $this->rows('famtastic_website_proof_research_snapshot'));
  }
  public static function lateFailures(): iterable { foreach (['famtastic_notification_outbox', 'famtastic_event'] as $t) yield [$t]; }

  #[DataProvider('ignoredOrAlteredWrites')]
  public function testSilentlyIgnoredOrAlteredWritesRollbackBeforeCommit(string $trigger): void {
    $before = $this->snapshot();
    $this->db->query($trigger, [], ['allow_delimiter_in_query' => TRUE]);
    $this->deny(fn() => $this->deliver(), '');
    // A rejection after a successful commit is NOT sufficient. Nothing from
    // this attempted release, including the research row, may survive.
    self::assertFalse($this->db->inTransaction());
    self::assertSame($before, $this->snapshot());
    self::assertCount(0, $this->rows('famtastic_website_proof_research_snapshot'));
    self::assertCount(0, $this->notices());
  }
  public static function ignoredOrAlteredWrites(): iterable {
    foreach (['famtastic_event', 'famtastic_website_proof_research_snapshot', 'famtastic_notification_outbox'] as $table) {
      yield 'ignore-' . $table => ["CREATE TRIGGER ignore_release BEFORE INSERT ON $table BEGIN SELECT RAISE(IGNORE); END"];
    }
    // Alter everything at the final event insert, after earlier write checks.
    $mutations = [
      'event-type' => "UPDATE famtastic_event SET event_type = 'changed' WHERE id = NEW.id",
      'event-time' => 'UPDATE famtastic_event SET recorded_at = recorded_at + 1 WHERE id = NEW.id',
      'event-decision' => "UPDATE famtastic_event SET payload = '{}' WHERE id = NEW.id",
      'research-bytes' => "UPDATE famtastic_website_proof_research_snapshot SET snapshot_json = '{}'",
      'research-time' => 'UPDATE famtastic_website_proof_research_snapshot SET changed = changed + 1',
      'notice-content' => "UPDATE famtastic_notification_outbox SET body = 'changed' WHERE notification_key = 'website-request:1:proofs:1:qa-v1'",
      'notice-recipient' => "UPDATE famtastic_notification_outbox SET recipient = 'foreign@example.test' WHERE notification_key = 'website-request:1:proofs:1:qa-v1'",
      'reveal-state' => "UPDATE famtastic_project_request SET proof_review_status = 'owner_review' WHERE id = 1",
      'reveal-human' => 'UPDATE famtastic_project_request SET proof_approved_by_uid = 1 WHERE id = 1',
      'reveal-other-column' => "UPDATE famtastic_project_request SET public_id = 'changed' WHERE id = 1",
      'late-variant-dna' => "UPDATE proof_variant SET design_dna__value = '{}'",
      'late-variant-html' => "UPDATE proof_variant SET preview_url = 'changed'",
      'late-asset-withdrawal' => "UPDATE famtastic_request_asset SET status = 'withdrawn'",
      'late-asset-consent' => 'UPDATE famtastic_request_asset SET ai_use_consent = 0',
      'late-account-block' => 'UPDATE users_field_data SET status = 0',
      'late-membership' => "UPDATE famtastic_membership SET status = 'inactive'",
      'late-customer-email' => "UPDATE famtastic_customer SET email = 'changed@example.test'",
      'late-campaign-selection' => "UPDATE proof_campaign SET selected_variant = 'a'",
      'late-import-receipt' => "UPDATE famtastic_event SET payload = '{}' WHERE event_type != NEW.event_type",
    ];
    foreach (["status = 'sent'", 'attempts = 1', 'max_attempts = 2', 'claimed_at = 1', "claim_token = 'changed'",
      'sent_at = 1', "provider_message_id = 'changed'", "last_error = 'changed'", 'available_at = available_at + 1',
      'created = created + 1', 'changed = changed + 1'] as $field) {
      $mutations['notice-state-' . $field] = "UPDATE famtastic_notification_outbox SET $field WHERE notification_key = 'website-request:1:proofs:1:qa-v1'";
    }
    foreach (['famtastic_website_proof_research_snapshot', 'famtastic_notification_outbox'] as $table) $mutations['delete-' . $table] = "DELETE FROM $table";
    foreach ($mutations as $name => $sql) yield $name => ["CREATE TRIGGER alter_release AFTER INSERT ON famtastic_event BEGIN $sql; END"];
  }

  public function testRootCommitLossPreservesOneReleaseAndExactReplay(): void {
    $this->db->commitFault = 'after_commit';
    $this->deny(fn() => $this->deliver(), 'acknowledgement loss');
    self::assertFalse($this->db->inTransaction()); self::assertCount(1, $this->notices());
    $before = $this->snapshot(); self::assertTrue($this->deliver()['duplicate']); self::assertSame($before, $this->snapshot());
  }

  public function testFailureBeforeRootCommitDoesNotExposeARelease(): void {
    $before = $this->snapshot(); $this->db->commitFault = 'before_commit';
    $this->deny(fn() => $this->deliver(), 'before SQLite commit');
    self::assertFalse($this->db->inTransaction()); self::assertSame($before, $this->snapshot());
    self::assertCount(0, $this->rows('famtastic_website_proof_research_snapshot'));
  }

  public function testNoticeHookCannotChangeCurrentRightsThenReveal(): void {
    $before = $this->snapshot();
    $this->db->query("CREATE TRIGGER change_rights AFTER INSERT ON famtastic_notification_outbox BEGIN UPDATE famtastic_request_asset SET status = 'withdrawn'; END", [], ['allow_delimiter_in_query' => TRUE]);
    $this->deny(fn() => $this->deliver(), 'asset authority');
    self::assertFalse($this->db->inTransaction()); self::assertSame($before, $this->snapshot());
    self::assertCount(0, $this->rows('famtastic_website_proof_research_snapshot'));
  }

  public function testCallerTransactionIsRejectedWithoutTouchingItsSentinel(): void {
    $tx = $this->db->startTransaction(); $this->db->update('famtastic_customer')->fields(['display_name' => 'Caller sentinel'])->execute();
    $this->deny(fn() => $this->deliver(), 'committed root connection');
    self::assertTrue($this->db->inTransaction()); self::assertSame('Caller sentinel', $this->rows('famtastic_customer')[0]['display_name']);
    $tx->rollBack(); unset($tx); self::assertSame('Synthetic', $this->rows('famtastic_customer')[0]['display_name']);
  }

  public function testHistoricalRetryNeverRestoresVisibilityOrResendsAfterSelectionAndWithdrawal(): void {
    $this->deliver();
    $this->db->update('famtastic_project_request')->fields(['proof_review_status'=>'selected','selected_proof_direction'=>'a','selected_proof_at'=>$this->now])->execute();
    $this->db->update('proof_campaign')->fields(['selected_variant'=>'a'])->execute();
    $this->db->update('famtastic_request_asset')->fields(['status'=>'withdrawn'])->execute();
    $this->db->update('famtastic_notification_outbox')->fields(['status'=>'sent','attempts'=>1,'provider_message_id'=>'synthetic-never-sent'])->condition('notification_key',$this->notice['notification_key'])->execute();
    $before = $this->snapshot(); $ack = $this->deliver();
    self::assertTrue($ack['historical_acknowledgment_only']); self::assertSame($before, $this->snapshot());
    $this->deny(fn() => $this->reader->context(1, $this->customer(), 'customer'), 'not eligible');
    $this->notice['body'] .= ' Changed'; $this->deny(fn() => $this->deliver(), 'retry differs'); self::assertSame($before,$this->snapshot());
  }

  public function testUnconfiguredAndUnauthorizedReplayDoNotAdoptExistingRelease(): void {
    foreach ([[FALSE,TRUE],[TRUE,FALSE]] as [$ev,$replay]) {
      $before = $this->snapshot();
      $this->deny(fn() => $this->makeRelease($ev,$replay)->release(1,$this->research,$this->evidence,$this->reviewer,$this->notice,$this->principal),'unconfigured');
      self::assertSame($before,$this->snapshot());
    }
    $this->deliver(); $before = $this->snapshot(); $this->replayAllowed = FALSE;
    $this->deny(fn() => $this->deliver(),'unauthorized'); self::assertSame($before,$this->snapshot());
  }

  #[DataProvider('tampering')]
  public function testStoredRecordTamperingAndLostEvidenceRevokeCustomerGrant(string $case): void {
    $this->deliver();
    switch ($case) {
      case 'missing-event': $this->db->delete('famtastic_event')->condition('event_type',ManagedProofRelease::EVENT)->execute(); break;
      case 'event': $this->db->update('famtastic_event')->fields(['payload'=>'{}'])->condition('event_type',ManagedProofRelease::EVENT)->execute(); break;
      case 'research': $this->db->update('famtastic_website_proof_research_snapshot')->fields(['approved_by_uid'=>1])->execute(); break;
      case 'notice': $this->db->update('famtastic_notification_outbox')->fields(['body'=>'Changed'])->condition('notification_key',$this->notice['notification_key'])->execute(); break;
      case 'template': $this->db->update('famtastic_notification_outbox')->fields(['template_id'=>'standard'])->condition('notification_key',$this->notice['notification_key'])->execute(); break;
      case 'time': $this->db->update('famtastic_project_request')->fields(['proof_approved_at'=>$this->now+1])->execute(); break;
      case 'human': $this->db->update('famtastic_project_request')->fields(['proof_approved_by_uid'=>1])->execute(); break;
      case 'evidence': $this->evidenceAllowed=FALSE; break;
    }
    $before = $this->snapshot(); $error=NULL;
    try { $this->reader->context(1,$this->customer(),'customer'); } catch (\Throwable $e) { if($e instanceof \PHPUnit\Framework\AssertionFailedError) throw $e; $error=$e; }
    self::assertNotNull($error); self::assertSame($before,$this->snapshot());
  }
  public static function tampering(): iterable { foreach (['missing-event','event','research','notice','template','time','human','evidence'] as $case) yield [$case]; }
}
