<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;

/**
 * Runs notifications, mailbox ingestion, support SLAs, and worker protection.
 */
final class LifecycleOperationsService {

  /**
   * Seconds without a completed run after next_due before a worker pages.
   */
  private const WORKER_LATE_GRACE_SECONDS = 1800;

  /** Seconds after which an interrupted generic outbox claim is retried. */
  private const OUTBOX_CLAIM_TIMEOUT_SECONDS = 1800;

  /** Submitted work should have either a proof state or an owner task in one day. */
  private const SUBMITTED_REQUEST_DEADLINE_SECONDS = 86400;

  /** Owner proof review and revision work receive a two-day internal deadline. */
  private const PROOF_OWNER_ACTION_DEADLINE_SECONDS = 172800;

  /** A visible proof may wait a week for a recorded selection before a draft follow-up is due. */
  private const PROOF_SELECTION_DEADLINE_SECONDS = 604800;

  /** A selected direction without Commerce evidence receives a three-day owner follow-up task. */
  private const SELECTED_NOT_PAID_DEADLINE_SECONDS = 259200;

  /** A delivery project is stale after a week without a durable update. */
  private const PROJECT_STALE_SECONDS = 604800;

  /** An approved or launched project must retain its release receipt within one day. */
  private const RELEASE_RECEIPT_DEADLINE_SECONDS = 86400;

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly OutreachMailer $mailer,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileSystemInterface $fileSystem,
    private readonly AutomationWorker $automationWorker,
    private readonly SupportDraftService $supportDrafts,
    private readonly PilotExactDispatchLock $pilotExactDispatchLock,
  ) {}

  public function dispatchNotifications(int $limit = 25): array {
    // This is the shared outbox boundary. Do not claim it during an exact-ID
    // pilot; targeted preview delivery uses PublicPreviewDeliveryService and
    // therefore remains separately owner-gated. Drupal account/auth mail and
    // direct operational mail are not globally disabled by this narrow guard.
    if ($this->pilotExactDispatchLock->isActive()) {
      return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'retried' => 0, 'skipped' => 'pilot_exact_dispatch_lock'];
    }
    $now = $this->time->getRequestTime();
    $recoveredClaims = $this->releaseExpiredNotificationClaims($now);
    $rows = $this->database->select('famtastic_notification_outbox', 'n')->fields('n')
      ->condition('status', ['queued', 'retry'], 'IN')->condition('available_at', $now, '<=')
      ->orderBy('created')->range(0, max(1, min(100, $limit)))->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $result = ['processed' => 0, 'claimed' => 0, 'sent' => 0, 'failed' => 0, 'retried' => 0, 'recovered_claims' => $recoveredClaims];
    foreach ($rows as $row) {
      $claim = $this->claimNotification($row, $now);
      if ($claim === NULL) {
        // A sibling runner claimed or updated this row after this runner's
        // candidate read. It is deliberately not a failed delivery.
        continue;
      }
      $result['processed']++;
      $result['claimed']++;
      try {
        $template = $this->notificationTemplate($row);
        $messageId = $this->mailer->send($row['recipient'], $row['subject'], $row['body'], NULL, $template['id'], $template['version']);
        $updated = $this->database->update('famtastic_notification_outbox')->fields([
          'status' => 'sent', 'attempts' => (int) $row['attempts'] + 1, 'sent_at' => $now,
          'provider_message_id' => $messageId, 'last_error' => NULL, 'claim_token' => '', 'claimed_at' => NULL, 'changed' => $now,
        ])->condition('id', $row['id'])->condition('status', 'dispatching')->condition('claim_token', $claim)->execute();
        if ($updated !== 1) {
          throw new \RuntimeException('notification_claim_lost_before_receipt');
        }
        if (preg_match('/^website-request:(\d+):proofs:\d+(?::(?:3|6))?$/', (string) $row['notification_key'], $matches)) {
          $this->database->update('famtastic_project_request')->fields([
            'proof_review_status' => 'notified',
            'proof_notified_at' => $now,
            'changed' => $now,
          ])->condition('id', (int) $matches[1])->condition('proof_review_status', 'customer_ready')->execute();
        }
        $result['sent']++;
      }
      catch (\Throwable $error) {
        $attempts = (int) $row['attempts'] + 1;
        $retry = $attempts < (int) $row['max_attempts'];
        $updated = $this->database->update('famtastic_notification_outbox')->fields([
          'status' => $retry ? 'retry' : 'dead_letter', 'attempts' => $attempts,
          'available_at' => $now + min(86400, 300 * (2 ** min(8, $attempts))),
          'last_error' => mb_substr($error->getMessage(), 0, 2000), 'claim_token' => '', 'claimed_at' => NULL, 'changed' => $now,
        ])->condition('id', $row['id'])->condition('status', 'dispatching')->condition('claim_token', $claim)->execute();
        if ($updated === 1) {
          $result[$retry ? 'retried' : 'failed']++;
        }
      }
    }
    $this->heartbeat('notification_dispatch', $result, $now + 300);
    return $result;
  }

  /**
   * Releases only abandoned generic claims; no provider call happens here.
   */
  private function releaseExpiredNotificationClaims(int $now): int {
    return $this->database->update('famtastic_notification_outbox')->fields([
      'status' => 'retry',
      'available_at' => $now,
      'claim_token' => '',
      'claimed_at' => NULL,
      'last_error' => 'notification_dispatch_claim_expired',
      'changed' => $now,
    ])->condition('status', 'dispatching')
      ->condition('claimed_at', $now - self::OUTBOX_CLAIM_TIMEOUT_SECONDS, '<=')
      ->execute();
  }

  /**
   * Atomically changes one candidate from available work to this runner's work.
   *
   * Returning the token makes the receipt/failure update conditional on this
   * exact claim. A concurrent runner can read the same candidate, but cannot
   * make a second provider call once this update succeeds.
   */
  private function claimNotification(array $row, int $now): ?string {
    $claim = bin2hex(random_bytes(16));
    $claimed = $this->database->update('famtastic_notification_outbox')->fields([
      'status' => 'dispatching',
      'claim_token' => $claim,
      'claimed_at' => $now,
      'changed' => $now,
    ])->condition('id', (int) $row['id'])
      ->condition('status', ['queued', 'retry'], 'IN')
      ->condition('available_at', $now, '<=')
      ->execute();
    return $claimed === 1 ? $claim : NULL;
  }

  /**
   * Uses the outbox template snapshot. Legacy rows retain a narrow identity
   * fallback so an upgrade never silently downgrades a queued proof notice.
   *
   * @return array{id:string,version:int}
   */
  private function notificationTemplate(array $row): array {
    $id = (string) ($row['template_id'] ?? '');
    $version = (int) ($row['template_version'] ?? 0);
    if (OutreachMailer::supportsTemplate($id, $version)) {
      return ['id' => $id, 'version' => $version];
    }
    $key = (string) ($row['notification_key'] ?? '');
    if (preg_match('/^website-request:\d+:customer$/', $key) === 1) {
      return ['id' => OutreachMailer::TEMPLATE_CUSTOMER_INTAKE_SUBMITTED, 'version' => OutreachMailer::TEMPLATE_CUSTOMER_INTAKE_SUBMITTED_VERSION];
    }
    if (
      preg_match('/^website-request:\d+:proofs:\d+:(?:3|6)$/', $key) === 1
      || preg_match('/^project:\d+:proofs:[^:]+:(?:3|6)$/', $key) === 1
    ) {
      return ['id' => OutreachMailer::TEMPLATE_CUSTOMER_PROOF_READY, 'version' => OutreachMailer::TEMPLATE_CUSTOMER_PROOF_READY_VERSION];
    }
    return ['id' => OutreachMailer::TEMPLATE_STANDARD, 'version' => OutreachMailer::TEMPLATE_STANDARD_VERSION];
  }

  /** Runs bounded durable proof and delivery jobs and records worker health. */
  public function runAutomation(int $limit = 10): array {
    $results = $this->automationWorker->run(max(1, min(50, $limit)));
    $result = [
      'processed' => count($results),
      'failed' => count(array_filter($results, static fn(array $row): bool => $row['status'] === 'failed')),
      'retried' => count(array_filter($results, static fn(array $row): bool => $row['status'] === 'retry')),
    ];
    $this->heartbeat('automation_jobs', $result, $this->time->getRequestTime() + 300);
    return ['summary' => $result, 'jobs' => $results];
  }

  /**
   * Imports one validated mailbox envelope into the correct portal thread.
   */
  public function ingestInbound(array $message): array {
    $messageId = trim((string) ($message['message_id'] ?? ''));
    $sender = mb_strtolower(trim((string) ($message['from'] ?? '')));
    $recipient = mb_strtolower(trim((string) ($message['to'] ?? '')));
    $subject = mb_substr(trim(strip_tags((string) ($message['subject'] ?? ''))), 0, 512);
    $body = trim(strip_tags((string) ($message['body'] ?? '')));
    if ($messageId === '' || !filter_var($sender, FILTER_VALIDATE_EMAIL) || strlen($body) < 1 || strlen($body) > 262144) {
      throw new \InvalidArgumentException('inbound_message_invalid');
    }
    $hash = hash('sha256', $messageId);
    $existing = $this->database->select('famtastic_inbound_message', 'i')->fields('i')->condition('message_id_hash', $hash)->execute()->fetchAssoc();
    if ($existing) return ['accepted' => $existing['status'] === 'matched', 'duplicate' => TRUE, 'status' => $existing['status']];
    preg_match('/support\+([0-9a-f-]{36})@/i', $recipient, $match);
    $publicId = strtolower((string) ($message['thread_public_id'] ?? ($match[1] ?? '')));
    $attachments = $this->validateAttachments((array) ($message['attachments'] ?? []), $publicId ?: 'unmatched');
    $thread = $publicId === '' ? FALSE : $this->database->select('famtastic_portal_thread', 't')->fields('t')->condition('public_id', $publicId)->execute()->fetchAssoc();
    $authorized = FALSE;
    if ($thread) {
      $query = $this->database->select('famtastic_membership', 'm');
      $query->join('famtastic_customer', 'c', 'c.id = m.customer_id');
      $authorized = (bool) $query->condition('m.organization_id', $thread['organization_id'])->condition('m.status', 'active')
        ->condition('c.email', $sender)->countQuery()->execute()->fetchField();
    }
    $status = $thread && $authorized ? 'matched' : 'unmatched';
    $reason = !$thread ? 'thread_not_found' : ($authorized ? '' : 'sender_not_authorized');
    $now = $this->time->getRequestTime();
    $this->database->insert('famtastic_inbound_message')->fields([
      'message_id_hash' => $hash, 'thread_public_id' => $publicId, 'sender_hash' => hash('sha256', $sender),
      'subject' => $subject, 'body' => $body, 'attachment_manifest' => json_encode($attachments, JSON_THROW_ON_ERROR),
      'status' => $status, 'rejection_reason' => $reason, 'received_at' => (int) ($message['received_at'] ?? $now), 'created' => $now,
    ])->execute();
    if ($status === 'matched') {
      $this->database->insert('famtastic_portal_message')->fields([
        'thread_id' => $thread['id'], 'author_type' => 'customer', 'body' => $body, 'created' => $now,
      ])->execute();
      $this->database->update('famtastic_portal_thread')->fields(['status' => 'open', 'changed' => $now])->condition('id', $thread['id'])->execute();
      $this->database->update('famtastic_support_case')->fields(['status' => 'waiting_on_famtastic', 'changed' => $now])
        ->condition('thread_id', $thread['id'])->execute();
    }
    else {
      $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fitzgerald.medine@gmail.com');
      $this->queue('inbound:' . $hash . ':unmatched', $admin, 'Unmatched customer email requires review', "Subject: {$subject}\nReason: {$reason}\nMessage-ID hash: {$hash}");
    }
    // L0 triage (B2): every accepted inbound gets exactly one draft reply.
    // Best-effort — a drafting failure must never break mail ingestion.
    try {
      $draftedId = (int) $this->database->select('famtastic_inbound_message', 'i')
        ->fields('i', ['id'])->condition('message_id_hash', $hash)->execute()->fetchField();
      if ($draftedId > 0) {
        $this->supportDrafts->createForMessage($draftedId);
      }
    }
    catch (\Throwable) {
      // Drafting is additive; ingestion evidence already recorded above.
    }
    return ['accepted' => $status === 'matched', 'duplicate' => FALSE, 'status' => $status, 'reason' => $reason];
  }

  public function staffReply(string $caseNumber, string $body, int $uid = 1): array {
    $case = $this->database->select('famtastic_support_case', 's')->fields('s')->condition('case_number', $caseNumber)->execute()->fetchAssoc();
    if (!$case) throw new \RuntimeException('support_case_not_found');
    $thread = $this->database->select('famtastic_portal_thread', 't')->fields('t')->condition('id', $case['thread_id'])->execute()->fetchAssoc();
    $customerQuery = $this->database->select('famtastic_membership', 'm');
    $customerQuery->join('famtastic_customer', 'c', 'c.id = m.customer_id');
    $customer = $customerQuery->fields('c')->condition('m.organization_id', $thread['organization_id'])->condition('m.role', 'owner')->execute()->fetchAssoc();
    $clean = trim(strip_tags($body));
    if ($clean === '') throw new \InvalidArgumentException('support_reply_required');
    $now = $this->time->getRequestTime();
    $messageId = (int) $this->database->insert('famtastic_portal_message')->fields([
      'thread_id' => $thread['id'], 'author_uid' => $uid, 'author_type' => 'staff', 'body' => $clean, 'created' => $now,
    ])->execute();
    $this->database->update('famtastic_support_case')->fields(['status' => 'waiting_on_customer', 'responded_at' => $now, 'changed' => $now])
      ->condition('id', $case['id'])->execute();
    $this->queue("support:{$case['id']}:reply:{$messageId}", $customer['email'], "Reply to support case {$caseNumber}", $clean . "\n\nReply to support+{$thread['public_id']}@famtasticdesigns.com");
    return ['case_number' => $caseNumber, 'status' => 'waiting_on_customer', 'message_id' => $messageId];
  }

  public function runProtection(): array {
    $now = $this->time->getRequestTime();
    $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fitzgerald.medine@gmail.com');
    // Revenue freshness writes only the owner-task ledger. It intentionally
    // does not queue mail, dispatch a provider, alter a customer request, or
    // create a Commerce order.
    $freshness = $this->reconcileRevenueFreshness($now);
    $overdue = $this->database->select('famtastic_support_case', 's')->fields('s')
      ->condition('status', ['new', 'assigned', 'waiting_on_famtastic'], 'IN')->condition('response_due', $now, '<=')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($overdue as $case) {
      $this->queue("support:{$case['id']}:overdue:" . gmdate('Ymd', $now), $admin,
        "Overdue support case {$case['case_number']}", "Priority: {$case['priority']}\nStatus: {$case['status']}\nResponse deadline has passed.");
    }
    $processed = count($overdue);
    $followups = $this->database->select('famtastic_prospect', 'p')->fields('p', ['id', 'business_name', 'status', 'next_followup_due'])
      ->condition('status', ['contacted', 'qualified', 'proposal', 'nurture'], 'IN')->condition('next_followup_due', 0, '>')->condition('next_followup_due', $now, '<=')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($followups as $lead) {
      $this->queue("lead:{$lead['id']}:followup:" . gmdate('Ymd', $now), $admin, "Lead follow-up due — {$lead['business_name']}", "Stage: {$lead['status']}\nFollow-up deadline has passed.\nOpen: https://famtasticdesigns.com/web/admin/famtastic/prospect/{$lead['id']}/edit");
    }
    $processed += count($followups);

    $renewalQuery = $this->database->select('famtastic_entitlement', 'e');
    $renewalQuery->join('famtastic_membership', 'm', 'm.organization_id = e.organization_id AND m.role = :role', [':role' => 'owner']);
    $renewalQuery->join('famtastic_customer', 'c', 'c.id = m.customer_id');
    $renewalQuery->fields('e', ['id', 'entitlement_type', 'renews_at', 'amount_minor', 'billing_interval'])->fields('c', ['email'])
      ->condition('e.status', 'active')->condition('e.renews_at', $now, '>')->condition('e.renews_at', $now + 2592000, '<=');
    $renewals = $renewalQuery->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($renewals as $renewal) {
      $this->queue("entitlement:{$renewal['id']}:renewal-30d", $renewal['email'], 'Upcoming FAMtastic service renewal',
        'Service: ' . $renewal['entitlement_type'] . "\nRenewal date: " . gmdate('Y-m-d', (int) $renewal['renews_at']) . "\nManage or cancel from your customer portal before renewal.");
    }
    $processed += count($renewals);

    // A worker counts as late only when it shows no sign of life within the
    // grace window. Keying solely on next_due raced against the sibling */5
    // crontab lines sharing this cadence: a due-but-running worker still had
    // its pre-run next_due in the past, so nearly every cycle produced a false
    // "late" alert (237 of the first 267 outbox sends). 1800s covers two full
    // cycles of the slowest worker (lifecycle_protection, +900s) plus jitter.
    // See docs/audits/CEO-FULL-REVIEW-2026-08-24.md gap #4.
    $staleBefore = $now - self::WORKER_LATE_GRACE_SECONDS;
    $lateWorkers = $this->database->select('famtastic_worker_heartbeat', 'w')->fields('w')
      ->condition('next_due', 0, '>')
      ->condition('next_due', $now, '<')
      ->condition('last_finished', $staleBefore, '<')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($lateWorkers as $worker) {
      $this->queue("worker:{$worker['worker_key']}:late:" . gmdate('YmdH', $now), $admin, "Automation worker late — {$worker['worker_key']}", "Last finished: " . gmdate(DATE_ATOM, (int) $worker['last_finished']) . "\nExpected by: " . gmdate(DATE_ATOM, (int) $worker['next_due']));
    }
    $processed += count($lateWorkers);
    $deadLetters = (int) $this->database->select('famtastic_notification_outbox', 'n')->condition('status', 'dead_letter')->countQuery()->execute()->fetchField();
    if ($deadLetters > 0) $this->queue('exceptions:daily:' . gmdate('Ymd', $now), $admin, 'Daily FAMtastic delivery exception summary', "Notifications requiring manual review: {$deadLetters}\nOpen operations: https://famtasticdesigns.com/web/admin/famtastic");

    $result = ['processed' => $processed, 'failed' => 0, 'retried' => 0, 'revenue_freshness' => $freshness];
    $this->heartbeat('lifecycle_protection', $result, $now + 900);
    return $result;
  }

  /**
   * Reconciles durable owner tasks for each point where revenue work can age.
   *
   * This is deliberately a ledger projection, not an automation trigger:
   * it never sends mail, calls a proof provider, charges a customer, changes
   * request/project state, or creates checkout work. The same stable key is
   * reopened when the fact recurs and retains its most recent recovery proof.
   */
  public function reconcileRevenueFreshness(?int $observedAt = NULL): array {
    $now = $observedAt ?? $this->time->getRequestTime();
    $observed = [];
    $created = 0;
    $updated = 0;

    $requests = $this->database->select('famtastic_project_request', 'r')->fields('r', [
      'id', 'public_id', 'status', 'project_name', 'business_name', 'proof_campaign_id', 'proof_review_status',
      'proof_approved_at', 'proof_notified_at', 'selected_proof_direction', 'selected_proof_at', 'commerce_order_id',
      'submitted_at', 'changed',
    ])->condition('status', ['submitted', 'checkout_started'], 'IN')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($requests as $request) {
      $requestId = (int) $request['id'];
      $proofStatus = (string) ($request['proof_review_status'] ?: 'not_started');
      $selected = trim((string) ($request['selected_proof_direction'] ?? '')) !== '';
      $paid = !empty($request['commerce_order_id']);
      $submittedAt = (int) ($request['submitted_at'] ?? 0) ?: (int) $request['changed'];
      $label = trim((string) ($request['project_name'] ?: $request['business_name'])) ?: 'Website request #' . $requestId;

      if (!$selected && !$paid) {
        [$state, $deadline, $ownerAction] = $this->submittedRequestFreshness($proofStatus, $request, $submittedAt);
        $taskKey = 'website_request:' . $requestId . ':submitted_request';
        $result = $this->recordRevenueFreshnessTask(
          $taskKey,
          'submitted_request',
          'website_request',
          $requestId,
          $state,
          $this->severityForDeadline($deadline, $now),
          $deadline,
          [
            'request_public_id' => (string) $request['public_id'],
            'request_label' => $label,
            'request_status' => (string) $request['status'],
            'proof_review_status' => $proofStatus,
            'proof_campaign_id' => (int) ($request['proof_campaign_id'] ?? 0),
            'submitted_at' => $submittedAt,
            'owner_action' => $ownerAction,
          ],
          $now,
        );
        $observed[$taskKey] = TRUE;
        $created += $result === 'created' ? 1 : 0;
        $updated += $result === 'updated' ? 1 : 0;
      }

      $proofTask = $this->proofStateFreshness($proofStatus, $request, $submittedAt);
      if ($proofTask !== NULL) {
        [$state, $deadline, $ownerAction] = $proofTask;
        $taskKey = 'website_request:' . $requestId . ':proof_state';
        $result = $this->recordRevenueFreshnessTask(
          $taskKey,
          'proof_state',
          'website_request',
          $requestId,
          $state,
          $this->severityForDeadline($deadline, $now),
          $deadline,
          [
            'request_public_id' => (string) $request['public_id'],
            'request_label' => $label,
            'proof_review_status' => $proofStatus,
            'proof_campaign_id' => (int) ($request['proof_campaign_id'] ?? 0),
            'request_changed_at' => (int) $request['changed'],
            'owner_action' => $ownerAction,
          ],
          $now,
        );
        $observed[$taskKey] = TRUE;
        $created += $result === 'created' ? 1 : 0;
        $updated += $result === 'updated' ? 1 : 0;
      }

      if ($selected && !$paid) {
        $selectedAt = (int) ($request['selected_proof_at'] ?? 0) ?: (int) $request['changed'];
        $deadline = $selectedAt + self::SELECTED_NOT_PAID_DEADLINE_SECONDS;
        $taskKey = 'website_request:' . $requestId . ':selected_not_paid';
        $result = $this->recordRevenueFreshnessTask(
          $taskKey,
          'selected_not_paid',
          'website_request',
          $requestId,
          'selected_not_paid',
          $this->severityForDeadline($deadline, $now),
          $deadline,
          [
            'request_public_id' => (string) $request['public_id'],
            'request_label' => $label,
            'selected_direction' => (string) $request['selected_proof_direction'],
            'selected_at' => $selectedAt,
            'commerce_order_id' => NULL,
            'owner_action' => 'Review the recorded selection and prepare an owner-approved checkout follow-up draft if appropriate. No offer, charge, or customer contact is created automatically.',
          ],
          $now,
        );
        $observed[$taskKey] = TRUE;
        $created += $result === 'created' ? 1 : 0;
        $updated += $result === 'updated' ? 1 : 0;
      }
    }

    $projects = $this->database->select('famtastic_project', 'p')->fields('p', [
      'id', 'label', 'delivery_status', 'approval_status', 'release_sha', 'artifact_checksum', 'approved_at', 'changed',
    ])->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($projects as $project) {
      $projectId = (int) $project['id'];
      $delivery = (string) ($project['delivery_status'] ?: 'draft');
      $approval = (string) ($project['approval_status'] ?: 'pending');
      $changed = (int) $project['changed'];
      $label = trim((string) $project['label']) ?: 'Project #' . $projectId;
      if (in_array($delivery, ['draft', 'request_generated', 'submitted', 'proof_delivered', 'revision', 'approved'], TRUE)
        && $changed <= $now - self::PROJECT_STALE_SECONDS) {
        $deadline = $changed + self::PROJECT_STALE_SECONDS;
        $taskKey = 'project:' . $projectId . ':stale';
        $result = $this->recordRevenueFreshnessTask(
          $taskKey,
          'stale_project',
          'project',
          $projectId,
          'project_stale',
          $this->severityForDeadline($deadline, $now),
          $deadline,
          [
            'project_label' => $label,
            'delivery_status' => $delivery,
            'approval_status' => $approval,
            'project_changed_at' => $changed,
            'owner_action' => 'Record the next owner-approved project action or a truthful blocked state. This task does not change delivery, launch, or customer communication.',
          ],
          $now,
        );
        $observed[$taskKey] = TRUE;
        $created += $result === 'created' ? 1 : 0;
        $updated += $result === 'updated' ? 1 : 0;
      }

      $requiresReceipt = in_array($delivery, ['approved', 'launched', 'deployed'], TRUE)
        || $approval === 'approved';
      $missingReceipt = trim((string) ($project['release_sha'] ?? '')) === ''
        || trim((string) ($project['artifact_checksum'] ?? '')) === '';
      if ($requiresReceipt && $missingReceipt) {
        $receiptAt = (int) ($project['approved_at'] ?? 0) ?: $changed;
        $deadline = $receiptAt + self::RELEASE_RECEIPT_DEADLINE_SECONDS;
        $taskKey = 'project:' . $projectId . ':release_receipt';
        $result = $this->recordRevenueFreshnessTask(
          $taskKey,
          'release_receipt',
          'project',
          $projectId,
          'release_receipt_missing',
          $this->severityForDeadline($deadline, $now),
          $deadline,
          [
            'project_label' => $label,
            'delivery_status' => $delivery,
            'approval_status' => $approval,
            'approved_at' => (int) ($project['approved_at'] ?? 0),
            'release_sha_recorded' => trim((string) ($project['release_sha'] ?? '')) !== '',
            'artifact_checksum_recorded' => trim((string) ($project['artifact_checksum'] ?? '')) !== '',
            'owner_action' => 'Record the approved release SHA and artifact checksum from an authoritative release receipt before treating this project as released.',
          ],
          $now,
        );
        $observed[$taskKey] = TRUE;
        $created += $result === 'created' ? 1 : 0;
        $updated += $result === 'updated' ? 1 : 0;
      }
    }

    $recovered = $this->recoverUnobservedRevenueFreshnessTasks(array_keys($observed), $now);
    return ['created' => $created, 'updated' => $updated, 'recovered' => $recovered, 'open' => count($observed), 'owner_task_only' => TRUE];
  }

  /**
   * Reports the durable owner-task ledger in dashboard-friendly JSON data.
   */
  public function revenueHealth(bool $refresh = TRUE): array {
    $now = $this->time->getRequestTime();
    $reconciliation = $refresh ? $this->reconcileRevenueFreshness($now) : NULL;
    $rows = $this->database->select('famtastic_revenue_freshness', 'f')->fields('f')
      ->orderBy('status', 'ASC')->orderBy('deadline_at', 'ASC')->orderBy('id', 'ASC')
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $summary = ['open' => 0, 'recovered' => 0, 'overdue' => 0, 'by_type' => []];
    $tasks = [];
    foreach ($rows as $row) {
      $status = (string) $row['status'];
      $taskType = (string) $row['task_type'];
      $overdue = $status === 'open' && (int) $row['deadline_at'] <= $now;
      $summary[$status] = ($summary[$status] ?? 0) + 1;
      $summary['by_type'][$taskType] = ($summary['by_type'][$taskType] ?? 0) + 1;
      if ($overdue) {
        $summary['overdue']++;
      }
      $tasks[] = [
        'task_key' => (string) $row['task_key'],
        'task_type' => $taskType,
        'subject' => ['type' => (string) $row['subject_type'], 'id' => (int) $row['subject_id']],
        'state' => (string) $row['state'],
        'severity' => (string) $row['severity'],
        'status' => $status,
        'deadline_at' => (int) $row['deadline_at'],
        'is_overdue' => $overdue,
        'detected_at' => (int) $row['detected_at'],
        'last_seen_at' => (int) $row['last_seen_at'],
        'recovered_at' => empty($row['recovered_at']) ? NULL : (int) $row['recovered_at'],
        'state_evidence' => $this->decodeFreshnessEvidence($row['state_evidence_json'] ?? ''),
        'recovery_evidence' => $this->decodeFreshnessEvidence($row['recovery_evidence_json'] ?? ''),
      ];
    }
    return [
      'schema' => 'famtastic.revenue-health.v1',
      'generated_at' => $now,
      'owner_task_only' => TRUE,
      'external_effects' => [],
      'reconciliation' => $reconciliation,
      'summary' => $summary,
      'tasks' => $tasks,
    ];
  }

  /** @return array{0:string,1:int,2:string} */
  private function submittedRequestFreshness(string $proofStatus, array $request, int $submittedAt): array {
    return match ($proofStatus) {
      'customer_ready', 'notified' => [
        'awaiting_customer_proof_selection',
        ((int) ($request['proof_notified_at'] ?? 0) ?: (int) ($request['proof_approved_at'] ?? 0) ?: (int) $request['changed']) + self::PROOF_SELECTION_DEADLINE_SECONDS,
        'Review the recorded proof state and, only if appropriate, prepare an owner-approved follow-up draft. No customer contact is created automatically.',
      ],
      'owner_review' => [
        'awaiting_owner_proof_review',
        (int) $request['changed'] + self::PROOF_OWNER_ACTION_DEADLINE_SECONDS,
        'Review the stored proof and research evidence. Customer visibility remains explicitly gated.',
      ],
      'showcase_building' => [
        'awaiting_showcase_review',
        (int) $request['changed'] + self::PROOF_OWNER_ACTION_DEADLINE_SECONDS,
        'Inspect the owner-requested proof expansion and retain the delivery gate.',
      ],
      'revision_requested' => [
        'awaiting_proof_revision_review',
        (int) $request['changed'] + self::PROOF_OWNER_ACTION_DEADLINE_SECONDS,
        'Review the persisted revision request before any proof or customer action.',
      ],
      default => [
        'awaiting_initial_proof_state',
        $submittedAt + self::SUBMITTED_REQUEST_DEADLINE_SECONDS,
        'Confirm the submitted brief has a durable proof state. This check never starts a provider run.',
      ],
    };
  }

  /** @return array{0:string,1:int,2:string}|null */
  private function proofStateFreshness(string $proofStatus, array $request, int $submittedAt): ?array {
    return match ($proofStatus) {
      'not_started', '' => [
        'proof_not_started',
        $submittedAt + self::SUBMITTED_REQUEST_DEADLINE_SECONDS,
        'Confirm the submitted brief has a durable proof job or record an owner decision. No provider run is triggered.',
      ],
      'owner_review' => [
        'proof_owner_review',
        (int) $request['changed'] + self::PROOF_OWNER_ACTION_DEADLINE_SECONDS,
        'Review the proof and required research snapshot before any customer release.',
      ],
      'showcase_building' => [
        'proof_showcase_building',
        (int) $request['changed'] + self::PROOF_OWNER_ACTION_DEADLINE_SECONDS,
        'Review the owner-gated showcase expansion; no customer delivery is implied.',
      ],
      'revision_requested' => [
        'proof_revision_requested',
        (int) $request['changed'] + self::PROOF_OWNER_ACTION_DEADLINE_SECONDS,
        'Review the stored revision request before a new proof action is approved.',
      ],
      default => NULL,
    };
  }

  private function severityForDeadline(int $deadline, int $now): string {
    return $deadline <= $now ? 'critical' : 'warning';
  }

  /** @return 'created'|'updated'|'unchanged' */
  private function recordRevenueFreshnessTask(string $taskKey, string $taskType, string $subjectType, int $subjectId, string $state, string $severity, int $deadline, array $evidence, int $now): string {
    $encodedEvidence = json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $existing = $this->database->select('famtastic_revenue_freshness', 'f')->fields('f')
      ->condition('task_key', $taskKey)->range(0, 1)->execute()->fetchAssoc();
    if (!$existing) {
      $this->database->insert('famtastic_revenue_freshness')->fields([
        'task_key' => $taskKey,
        'task_type' => $taskType,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'state' => $state,
        'severity' => $severity,
        'status' => 'open',
        'deadline_at' => $deadline,
        'detected_at' => $now,
        'last_seen_at' => $now,
        'recovered_at' => NULL,
        'state_evidence_json' => $encodedEvidence,
        'recovery_evidence_json' => NULL,
        'created' => $now,
        'changed' => $now,
      ])->execute();
      return 'created';
    }
    $changed = (string) $existing['status'] !== 'open'
      || (string) $existing['state'] !== $state
      || (string) $existing['severity'] !== $severity
      || (int) $existing['deadline_at'] !== $deadline
      || (string) $existing['state_evidence_json'] !== $encodedEvidence;
    $newStateDetected = (string) $existing['status'] !== 'open' || (string) $existing['state'] !== $state;
    $this->database->update('famtastic_revenue_freshness')->fields([
      'task_type' => $taskType,
      'subject_type' => $subjectType,
      'subject_id' => $subjectId,
      'state' => $state,
      'severity' => $severity,
      'status' => 'open',
      'deadline_at' => $deadline,
      'detected_at' => $newStateDetected ? $now : (int) $existing['detected_at'],
      'last_seen_at' => $now,
      'recovered_at' => NULL,
      'state_evidence_json' => $encodedEvidence,
      'changed' => $now,
    ])->condition('id', (int) $existing['id'])->execute();
    return $changed ? 'updated' : 'unchanged';
  }

  private function recoverUnobservedRevenueFreshnessTasks(array $observedTaskKeys, int $now): int {
    $query = $this->database->select('famtastic_revenue_freshness', 'f')->fields('f')
      ->condition('status', 'open');
    if ($observedTaskKeys !== []) {
      $query->condition('task_key', $observedTaskKeys, 'NOT IN');
    }
    $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $recovered = 0;
    foreach ($rows as $row) {
      $evidence = [
        'recovered_at' => $now,
        'reason' => 'source_condition_not_observed',
        'prior_state' => (string) $row['state'],
        'prior_deadline_at' => (int) $row['deadline_at'],
        'prior_last_seen_at' => (int) $row['last_seen_at'],
      ];
      $updated = $this->database->update('famtastic_revenue_freshness')->fields([
        'status' => 'recovered',
        'recovered_at' => $now,
        'recovery_evidence_json' => json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        'changed' => $now,
      ])->condition('id', (int) $row['id'])->condition('status', 'open')->execute();
      $recovered += $updated === 1 ? 1 : 0;
    }
    return $recovered;
  }

  private function decodeFreshnessEvidence(mixed $json): ?array {
    if (!is_string($json) || $json === '') {
      return NULL;
    }
    $decoded = json_decode($json, TRUE);
    return is_array($decoded) ? $decoded : NULL;
  }

  private function validateAttachments(array $attachments, string $threadPublicId): array {
    $allowed = ['image/png', 'image/jpeg', 'image/webp', 'application/pdf', 'text/plain'];
    $safe = []; $total = 0;
    foreach ($attachments as $attachment) {
      $size = (int) ($attachment['size'] ?? 0); $mime = strtolower((string) ($attachment['mime'] ?? ''));
      if ($size < 0 || $size > 10485760 || !in_array($mime, $allowed, TRUE)) throw new \InvalidArgumentException('inbound_attachment_rejected');
      $total += $size;
      if ($total > 15728640) throw new \InvalidArgumentException('inbound_attachments_too_large');
      $name = mb_substr(basename((string) ($attachment['name'] ?? 'attachment')), 0, 255);
      $record = ['name' => $name, 'mime' => $mime, 'size' => $size, 'sha256' => (string) ($attachment['sha256'] ?? '')];
      if (isset($attachment['content_base64'])) {
        $bytes = base64_decode((string) $attachment['content_base64'], TRUE);
        if ($bytes === FALSE || strlen($bytes) !== $size || !hash_equals(hash('sha256', $bytes), $record['sha256'])) throw new \InvalidArgumentException('inbound_attachment_integrity_failed');
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($detected, $allowed, TRUE) || ($mime !== $detected && !($mime === 'image/jpeg' && $detected === 'image/pjpeg'))) throw new \InvalidArgumentException('inbound_attachment_mime_mismatch');
        $directory = 'private://famtastic-support/' . preg_replace('/[^0-9a-f-]/', '', $threadPublicId);
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $path = $directory . '/' . bin2hex(random_bytes(12)) . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        $saved = $this->fileSystem->saveData($bytes, $path, FileSystemInterface::EXISTS_ERROR);
        if ($saved === FALSE) throw new \RuntimeException('inbound_attachment_save_failed');
        $record['private_path'] = $saved;
      }
      $safe[] = $record;
    }
    return $safe;
  }

  private function queue(string $key, string $recipient, string $subject, string $body): void {
    $now = $this->time->getRequestTime();
    $this->database->merge('famtastic_notification_outbox')->key('notification_key', $key)->insertFields([
      'notification_key' => $key, 'category' => 'operational', 'recipient' => mb_strtolower($recipient), 'subject' => $subject, 'body' => $body,
      'status' => 'queued', 'attempts' => 0, 'max_attempts' => 5, 'available_at' => $now, 'created' => $now, 'changed' => $now,
    ])->execute();
  }

  private function heartbeat(string $key, array $result, int $nextDue): void {
    $now = $this->time->getRequestTime();
    $this->database->merge('famtastic_worker_heartbeat')->key('worker_key', $key)->fields([
      'worker_key' => $key, 'status' => ($result['failed'] ?? 0) > 0 ? 'degraded' : 'healthy', 'last_started' => $now,
      'last_finished' => $now, 'next_due' => $nextDue, 'processed' => (int) ($result['processed'] ?? 0),
      'failed' => (int) ($result['failed'] ?? 0), 'retried' => (int) ($result['retried'] ?? 0), 'changed' => $now,
    ])->execute();
  }
}
