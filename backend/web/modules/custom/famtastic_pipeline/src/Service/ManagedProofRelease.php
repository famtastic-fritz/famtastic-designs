<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/**
 * Receipt-bound independent QA, research and existing outbox in one root commit.
 *
 * Unregistered. The reader authenticates the real reviewer principal. The local
 * verifyEvidence(evidence, context): bool dependency must verify retained actual
 * evidence for every check, bound to this reviewer and exact import; a worker's
 * green booleans alone are not that verification. authenticateReplay(principal,
 * committed, request): ?string authenticates current access to historical release
 * facts, not a read/release grant. Both are trusted server dependencies, default
 * NULL. They run outside transactions, never make paid/provider/network calls,
 * and must not call back into this service. No test attestor is installed here.
 */
final class ManagedProofRelease {
  public const SCHEMA = 'famtastic.managed-proof-release.v1';
  public const EVENT = 'website_request.managed_proofs_qa_approved.v1';
  private const MAX_WIRE = 250000;

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly CustomerPortalService $portal,
    private readonly ManagedProofReader $reader,
    private readonly ?\Closure $verifyEvidence = NULL,
    private readonly ?\Closure $authenticateReplay = NULL,
  ) {}

  /** Normalized research comes from the existing portal wrapper, not a new form. */
  public function release(int $id, array $research, array $evidence, string $reviewer, array $notification, object $principal): array {
    $this->outsideTransaction();
    if ($this->verifyEvidence === NULL || $this->authenticateReplay === NULL) throw new \RuntimeException('Managed QA release is unconfigured.');
    ProofOperationContract::integer($id, 1, PHP_INT_MAX);
    $notification = $this->notice($notification);
    $intentHash = $this->hash([$research, $evidence, $reviewer, $notification]);
    $request = $this->request($id);
    if (!$request || !FreshProofBinding::isManagedReadOnly($this->database, $request)) throw new \RuntimeException('Managed QA request is absent.');
    $prior = $this->event($id, (int) $request['proof_campaign_id']);
    if ($prior) {
      // Historical acknowledgment only. It neither re-releases nor grants reads,
      // even if the customer has since selected, revised or withdrawn an asset.
      $committed = $this->committedForRequest($request);
      $actor = ($this->authenticateReplay)($principal, $committed, $request);
      if ($actor !== $reviewer || !is_string($actor) || in_array($actor, $committed['receipt']['producer_ids'], TRUE)) throw new \RuntimeException('Managed QA replay is unauthorized.');
      $record = $this->verifiedRecord($request, $committed);
      if ($record['input_sha256'] !== $intentHash || $record['decision']['actor'] !== $reviewer) throw new \RuntimeException('Managed QA retry differs from immutable release.');
      $this->verifyRetained($record['decision']['evidence'], $this->reviewContext($committed, $record['decision']['actor'], $record['research']));
      return $this->receipt($record, TRUE);
    }

    // Current files, evidence and reviewer authentication precede all write locks.
    $handle = $this->reader->context($id, $principal, 'reviewer');
    $facts = $this->reader->facts($handle);
    if ($reviewer !== $facts['actor']) throw new \RuntimeException('Managed QA reviewer differs from authenticated principal.');
    $context = [
      'request_id' => $id, 'customer_id' => $facts['customer_id'], 'campaign_id' => $facts['campaign_id'],
      'artifact_hashes' => $facts['artifact_hashes'], 'research_sha256' => $this->hash($research),
      'policy_version' => AutomatedProofPolicy::VERSION, 'managed_import' => $this->importBinding($facts), 'reviewer' => $reviewer,
    ];
    $decision = $this->decision($request, $context, $evidence, $reviewer);
    $this->verifyRetained($evidence, $context);
    // Detect changes observed while the trusted evidence resolver was reading.
    if ($facts !== $this->reader->facts($handle)) throw new \RuntimeException('Managed QA context changed during verification.');

    $tx = $this->database->startTransaction();
    try {
      $locked = ManagedProofCurrentBinding::lockPending($this->database, $id, $this->time->getCurrentTime(), $facts['receipt_sha256']);
      $row = $locked['request']; $committed = $locked['committed'];
      if ($committed['receipt_id'] !== $facts['receipt_id'] || $this->reviewContext($committed, $reviewer, $research) !== $context) throw new \RuntimeException('Managed QA receipt changed before release.');
      $this->validateNotice($row, $locked['customer'], $notification);
      $key = $notification['notification_key'];
      if ($this->event($id, (int) $row['proof_campaign_id'], TRUE)
        || $this->database->select('famtastic_notification_outbox', 'n')->fields('n', ['id'])->condition('notification_key', $key)->forUpdate()->execute()->fetchField()
        || $this->database->select('famtastic_website_proof_research_snapshot', 's')->fields('s', ['website_request_id'])->condition('website_request_id', $id)->forUpdate()->execute()->fetchField()) throw new \RuntimeException('Managed release history already exists; reconcile the exact operation.');
      $now = $this->time->getCurrentTime();
      $snapshot = $this->researchSnapshot($research, $reviewer, $now);
      $wire = $this->wire($snapshot);
      $this->database->insert('famtastic_website_proof_research_snapshot')->fields([
        'website_request_id' => $id, 'proof_campaign_id' => (int) $row['proof_campaign_id'],
        'snapshot_json' => $wire, 'snapshot_hash' => hash('sha256', $wire), 'approved_by_uid' => 0,
        'approved_at' => $now, 'created' => $now, 'changed' => $now,
      ])->execute();
      // The existing renderer and queue remain the only mail system. This never sends.
      $this->portal->queueNotification($key, 'transactional', $notification['recipient'], $notification['subject'], $notification['body'],
        OutreachMailer::TEMPLATE_CUSTOMER_PROOF_READY, OutreachMailer::TEMPLATE_CUSTOMER_PROOF_READY_VERSION);
      if ($this->database->update('famtastic_notification_outbox')->fields(['max_attempts' => 1])->condition('notification_key', $key)->condition('status', 'queued')->condition('attempts', 0)->execute() !== 1) throw new \RuntimeException('Managed notice queue write failed.');
      $outbox = $this->outbox($key);
      $record = [
        'schema' => self::SCHEMA, 'request_id' => $id, 'customer_id' => (int) $row['customer_id'],
        'campaign_id' => (int) $row['proof_campaign_id'], 'prospect_id' => (int) $row['prospect_id'],
        'managed_import' => $context['managed_import'], 'input_sha256' => $intentHash,
        'decision' => $decision, 'research' => $research, 'research_snapshot_sha256' => hash('sha256', $wire),
        'notification_key' => $key, 'notification_sha256' => $this->hash($notification), 'outbox_id' => (int) $outbox['id'], 'approved_at' => $now,
      ];
      // All upstream rows are already owned. Recheck after dependent writers;
      // a trigger/hook must not silently revoke/change authority before reveal.
      $latest = ManagedProofCurrentBinding::lockPending($this->database, $id, $this->time->getCurrentTime(), $facts['receipt_sha256']);
      if ($latest !== $locked) throw new \RuntimeException('Managed QA current authority changed before reveal.');
      $reveal = [
        'proof_review_status' => 'customer_ready', 'proof_approved_by_uid' => NULL, 'proof_approved_at' => $now, 'changed' => $now,
      ];
      $query = $this->database->update('famtastic_project_request')->fields($reveal);
      foreach ($row as $field => $value) $value === NULL ? $query->isNull($field) : $query->condition($field, $value);
      if ($query->execute() !== 1) throw new \RuntimeException('Managed proof reveal lost its current binding.');
      $this->database->insert('famtastic_event')->fields([
        'event_key' => self::key($id, (int) $row['proof_campaign_id']), 'event_type' => self::EVENT,
        'prospect_id' => (int) $row['prospect_id'], 'campaign_id' => (int) $row['proof_campaign_id'],
        'payload' => $this->wire($record), 'occurred_at' => $now, 'recorded_at' => $now,
      ])->execute();
      // An insert can be silently ignored or changed by a database hook. Verify
      // the actual rows while rollback is still possible, not only after commit.
      // These are primary, locked DB reads; no evidence/file/provider I/O here.
      $persistedRequest = $this->request($id, TRUE);
      if (!$persistedRequest || !$this->sameRow($persistedRequest, array_replace($row, $reveal))) throw new \RuntimeException('Managed proof reveal readback differs.');
      if ($this->verifiedRecord($persistedRequest, $committed, TRUE) !== $record) throw new \RuntimeException('Managed release decision readback differs.');
      $persistedNotice = $this->outbox($key, TRUE);
      $queueTime = $this->time->getRequestTime();
      $freshNotice = $notification + [
        'id' => $record['outbox_id'], 'category' => 'transactional',
        'template_id' => OutreachMailer::TEMPLATE_CUSTOMER_PROOF_READY,
        'template_version' => OutreachMailer::TEMPLATE_CUSTOMER_PROOF_READY_VERSION,
        'status' => 'queued', 'attempts' => 0, 'max_attempts' => 1,
        'available_at' => $queueTime, 'created' => $queueTime, 'changed' => $queueTime,
        'sent_at' => NULL, 'claimed_at' => NULL, 'claim_token' => '', 'provider_message_id' => '', 'last_error' => NULL,
      ];
      if (!$this->sameRow($persistedNotice, $freshNotice)) throw new \RuntimeException('Managed fresh notice readback differs.');
      ManagedProofCurrentBinding::assertNewReleaseUnchanged($this->database, $locked, $this->time->getCurrentTime(), $now);
      // No blanket queue drain, human reminder rewrite, budget release or job mutation.
      $tx->commitOrRelease(); unset($tx);
      $this->outsideTransaction();
      $committed = ManagedProofImportReceipt::committed($this->database, $committed['receipt']['job_id'], $facts['receipt_sha256']);
      return $this->receipt($this->verifiedRecord($this->request($id), $committed), FALSE);
    }
    catch (\Throwable $error) {
      if (isset($tx) && $this->database->inTransaction()) { try { $tx->rollBack(); } catch (\Throwable) {} }
      try { unset($tx); } catch (\Throwable) {}
      // Never undo a confirmed commit or claim success after lost acknowledgment.
      throw $error;
    }
  }

  /** Trusted ManagedProofReader verifier; checks stored real release, not flags. */
  public function customerGrant(array $committed, array $request, AccountInterface $principal): array {
    $this->outsideTransaction();
    if (!$principal->isAuthenticated() || (int) $principal->id() < 1) throw new \RuntimeException('Managed customer release principal is invalid.');
    $customer = $this->database->select('famtastic_customer', 'c')->fields('c')->condition('id', $request['customer_id'])->execute()->fetchAssoc();
    if (!$customer || (int) $customer['uid'] !== (int) $principal->id()) throw new \RuntimeException('Managed customer release belongs to another account.');
    $record = $this->verifiedRecord($request, $committed);
    if (!in_array($request['proof_review_status'], ['customer_ready', 'notified'], TRUE) || $request['proof_approved_by_uid'] !== NULL
      || (int) $request['proof_approved_at'] !== $record['approved_at']) throw new \RuntimeException('Managed release state differs from its immutable record.');
    $this->verifyRetained($record['decision']['evidence'], $this->reviewContext($committed, $record['decision']['actor'], $record['research']));
    return $record['managed_import'] + [
      'request_id' => $record['request_id'], 'customer_id' => $record['customer_id'], 'campaign_id' => $record['campaign_id'],
      'proof_review_status' => $request['proof_review_status'], 'proof_approved_at' => $record['approved_at'],
      'evidence_sha256' => $record['decision']['evidence_sha256'], 'release_sha256' => $this->hash($record),
    ];
  }

  private function decision(array $request, array $context, array $evidence, string $reviewer): array {
    ProofOperationContract::keys($evidence, ['request_id', 'customer_id', 'campaign_id', 'artifact_hashes', 'research_sha256', 'policy_version',
      'managed_import', 'reviewer', 'producer', 'scope_in_bounds', 'exceptions', 'checks']);
    if (!is_array($evidence['checks'])) throw new \InvalidArgumentException('Managed QA checks are invalid.');
    ProofOperationContract::keys($evidence['checks'], AutomatedProofPolicy::CHECKS);
    foreach ($evidence['checks'] as $check) {
      if (!is_array($check)) throw new \InvalidArgumentException('Managed QA check is invalid.');
      ProofOperationContract::keys($check, ['passed', 'evidence_ref', 'evidence_sha256']);
    }
    if (($evidence['managed_import'] ?? NULL) !== $context['managed_import'] || ($evidence['reviewer'] ?? NULL) !== $reviewer
      || !in_array($evidence['producer'] ?? NULL, $context['managed_import']['producer_ids'], TRUE)
      || in_array($reviewer, $context['managed_import']['producer_ids'], TRUE)) throw new \RuntimeException('Managed independent QA import or producer binding differs.');
    return AutomatedProofPolicy::decide($request, $context['artifact_hashes'], $context['research_sha256'], $evidence, $reviewer);
  }

  private function reviewContext(array $committed, string $reviewer, array $research): array {
    $r = $committed['receipt']; $hashes = [];
    foreach (['a', 'b', 'c'] as $d) $hashes[$d] = $r['variants'][$d]['html_sha256'];
    return ['request_id' => $r['request_id'], 'customer_id' => $r['customer_id'], 'campaign_id' => $r['campaign_entity_id'],
      'artifact_hashes' => $hashes, 'research_sha256' => $this->hash($research), 'policy_version' => AutomatedProofPolicy::VERSION,
      'managed_import' => ['receipt_id' => $committed['receipt_id'], 'receipt_sha256' => $committed['receipt_sha256'],
        'package_manifest_sha256' => $r['package_manifest_sha256'], 'producer_ids' => $r['producer_ids']], 'reviewer' => $reviewer];
  }

  private function importBinding(array $facts): array {
    return array_intersect_key($facts, array_flip(['receipt_id', 'receipt_sha256', 'package_manifest_sha256', 'producer_ids']));
  }

  /** DB-only immutable record verification. No authority supplied by caller hashes. */
  private function verifiedRecord(array $request, array $committed, bool $lock = FALSE): array {
    $event = $this->event((int) $request['id'], (int) $request['proof_campaign_id'], $lock);
    if (!$event || $event['event_type'] !== self::EVENT) throw new \RuntimeException('Managed QA release record is absent.');
    if (strlen($event['payload']) > self::MAX_WIRE) throw new \RuntimeException('Managed QA release is oversized.');
    $record = json_decode($event['payload'], TRUE, 32, JSON_THROW_ON_ERROR);
    ProofOperationContract::keys($record, ['schema', 'request_id', 'customer_id', 'campaign_id', 'prospect_id', 'managed_import', 'input_sha256',
      'decision', 'research', 'research_snapshot_sha256', 'notification_key', 'notification_sha256', 'outbox_id', 'approved_at']);
    $r = $committed['receipt'];
    if ($this->wire($record) !== $event['payload'] || $record['schema'] !== self::SCHEMA
      || $record['request_id'] !== (int) $request['id'] || $record['request_id'] !== $r['request_id']
      || $record['customer_id'] !== (int) $request['customer_id'] || $record['customer_id'] !== $r['customer_id']
      || $record['campaign_id'] !== (int) $request['proof_campaign_id'] || $record['campaign_id'] !== $r['campaign_entity_id']
      || $record['prospect_id'] !== $r['prospect_id'] || (int) $event['prospect_id'] !== $r['prospect_id']
      || (int) $event['campaign_id'] !== $r['campaign_entity_id'] || !is_int($record['approved_at']) || $record['approved_at'] < 1
      || (int) $event['occurred_at'] !== $record['approved_at'] || (int) $event['recorded_at'] !== $record['approved_at']) throw new \RuntimeException('Managed QA release identity differs.');
    $reviewer = $record['decision']['actor'] ?? '';
    if (!is_string($reviewer) || !preg_match('/\Aautomation:[a-z][a-z0-9._-]{2,63}\z/', $reviewer)) throw new \RuntimeException('Managed QA record reviewer is invalid.');
    $context = $this->reviewContext($committed, $reviewer, $record['research']);
    $pending = $request; $pending['status'] = 'submitted'; $pending['proof_review_status'] = 'owner_review';
    if ($record['managed_import'] !== $context['managed_import']
      || $record['decision'] !== $this->decision($pending, $context, $record['decision']['evidence'], $reviewer)) throw new \RuntimeException('Managed QA stored decision differs.');
    $query = $this->database->select('famtastic_website_proof_research_snapshot', 's')->fields('s')->condition('website_request_id', $request['id']);
    if ($lock) $query->forUpdate();
    $snapshot = $query->execute()->fetchAssoc();
    $expectedSnapshot = $this->wire($this->researchSnapshot($record['research'], $reviewer, $record['approved_at']));
    if (!$snapshot || $snapshot['snapshot_json'] !== $expectedSnapshot || $snapshot['snapshot_hash'] !== hash('sha256', $expectedSnapshot)
      || $record['research_snapshot_sha256'] !== $snapshot['snapshot_hash'] || (int) $snapshot['proof_campaign_id'] !== $record['campaign_id']
      || (int) $snapshot['approved_by_uid'] !== 0 || (int) $snapshot['approved_at'] !== $record['approved_at']
      || (int) $snapshot['created'] !== $record['approved_at'] || (int) $snapshot['changed'] !== $record['approved_at']) throw new \RuntimeException('Managed QA research record differs.');
    $notice = $this->outbox($record['notification_key'], $lock);
    $noticeInput = $this->notice(array_intersect_key($notice, array_flip(['notification_key', 'recipient', 'subject', 'body'])));
    if ((int) $notice['id'] !== $record['outbox_id'] || $notice['category'] !== 'transactional'
      || $notice['template_id'] !== OutreachMailer::TEMPLATE_CUSTOMER_PROOF_READY || (int) $notice['template_version'] !== OutreachMailer::TEMPLATE_CUSTOMER_PROOF_READY_VERSION
      || (int) $notice['max_attempts'] !== 1 || $this->hash($noticeInput) !== $record['notification_sha256']
      || $record['input_sha256'] !== $this->hash([$record['research'], $record['decision']['evidence'], $reviewer, $noticeInput])) throw new \RuntimeException('Managed QA notification record differs.');
    return $record;
  }

  private function validateNotice(array $request, array $customer, array $notice): void {
    $expectedKey = 'website-request:' . $request['id'] . ':proofs:' . $request['proof_campaign_id'] . ':qa-v1';
    if ((int) $customer['verified_at'] < 1 || $notice['recipient'] !== mb_strtolower((string) $customer['email'])
      || $notice['notification_key'] !== $expectedKey) throw new \RuntimeException('Managed notice recipient or key differs.');
    $url = 'https://famtasticdesigns.com/portal/?section=projects&request=' . rawurlencode((string) $request['public_id']);
    preg_match_all('~https?://[^\s]+~i', $notice['body'], $links);
    if ($links[0] !== [$url]) throw new \RuntimeException('Managed notice requires one exact account-bound portal destination.');
  }

  private function notice(array $notice): array {
    ProofOperationContract::keys($notice, ['notification_key', 'recipient', 'subject', 'body']);
    $result = [];
    foreach (['notification_key', 'recipient', 'subject', 'body'] as $key) {
      if (!is_string($notice[$key]) || trim($notice[$key]) === '') throw new \InvalidArgumentException('Managed notice must contain exact nonempty text.');
      $result[$key] = $notice[$key];
    }
    if (!filter_var($result['recipient'], FILTER_VALIDATE_EMAIL) || strpbrk($result['subject'], "\r\n") !== FALSE) throw new \InvalidArgumentException('Managed notice headers are invalid.');
    $this->wire($result); return $result;
  }

  private function researchSnapshot(array $research, string $reviewer, int $now): array {
    foreach (['reviewed_by', 'review_policy', 'reviewed_at'] as $key) if (array_key_exists($key, $research)) throw new \InvalidArgumentException('Research cannot supply review metadata.');
    return $research + ['reviewed_by' => $reviewer, 'review_policy' => AutomatedProofPolicy::VERSION, 'reviewed_at' => gmdate(DATE_ATOM, $now)];
  }
  private function verifyRetained(array $evidence, array $context): void {
    $this->outsideTransaction();
    if ($this->verifyEvidence === NULL || ($this->verifyEvidence)($evidence, $context) !== TRUE) throw new \RuntimeException('Managed retained independent QA evidence is unverified.');
    $this->outsideTransaction();
  }
  private function committedForRequest(array $request): array {
    $event = FreshProofBinding::event($this->database, (int) $request['id']);
    $admission = $event ? json_decode($event['payload'], TRUE, 32, JSON_THROW_ON_ERROR) : [];
    if (!is_int($admission['job_id'] ?? NULL)) throw new \RuntimeException('Managed admission is invalid.');
    return ManagedProofImportReceipt::committed($this->database, $admission['job_id']);
  }
  private function receipt(array $record, bool $duplicate): array {
    $n = $this->outbox($record['notification_key']);
    return ['request_id' => $record['request_id'], 'duplicate' => $duplicate, 'historical_acknowledgment_only' => $duplicate,
      'release_sha256' => $this->hash($record), 'outbox' => array_intersect_key($n, array_flip(['id', 'status', 'attempts', 'notification_key'])),
      'policy_version' => AutomatedProofPolicy::VERSION, 'email_sent_by_this_operation' => FALSE];
  }
  private function outbox(string $key, bool $lock = FALSE): array {
    $query = $this->database->select('famtastic_notification_outbox', 'n')->fields('n')->condition('notification_key', $key);
    if ($lock) $query->forUpdate();
    return $query->execute()->fetchAssoc()
      ?: throw new \RuntimeException('Managed QA notification is missing.');
  }
  private function request(int $id, bool $lock = FALSE): array|false {
    $query = $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $id);
    if ($lock) $query->forUpdate(); return $query->execute()->fetchAssoc();
  }
  /** Drivers may return integer columns as strings; NULL is never an empty string. */
  private function sameRow(array $actual, array $expected): bool {
    if (count($actual) !== count($expected) || array_diff_key($actual, $expected)) return FALSE;
    foreach ($expected as $key => $value) {
      if ($value === NULL ? $actual[$key] !== NULL : ($actual[$key] === NULL || (string) $actual[$key] !== (string) $value)) return FALSE;
    }
    return TRUE;
  }
  private static function key(int $id, int $campaign): string { return 'website-proof:automated-release:' . $id . ':' . $campaign; }
  private function event(int $id, int $campaign, bool $lock = FALSE): array|false {
    $query = $this->database->select('famtastic_event', 'e')->fields('e')->condition('event_key', self::key($id, $campaign));
    if ($lock) $query->forUpdate(); return $query->execute()->fetchAssoc();
  }
  private function outsideTransaction(): void { if ($this->database->inTransaction()) throw new \LogicException('Managed QA release requires a committed root connection.'); }
  private function wire(array $value): string {
    $wire = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    if (strlen($wire) > self::MAX_WIRE) throw new \InvalidArgumentException('Managed QA payload exceeds its bound.');
    return $wire;
  }
  private function hash(array $value): string { return hash('sha256', $this->wire($value)); }
}
