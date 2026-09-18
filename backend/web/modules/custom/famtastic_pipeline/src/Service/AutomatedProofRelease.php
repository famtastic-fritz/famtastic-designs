<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Datetime\TimeInterface;

/** Trusted staff/worker boundary, never a public or customer approval endpoint. */
final class AutomatedProofRelease {
  public function __construct(private readonly Connection $database, private readonly EntityTypeManagerInterface $entities,
    private readonly TimeInterface $time, private readonly OperationalLedger $ledger, private readonly CustomerPortalService $portal) {}

  /** Read-only current bytes and normalized research for independent QA binding. */
  public function context(int $id, array $research): array {
    $row = $this->request($id);
    if (!$row || empty($row['proof_campaign_id'])) throw new \InvalidArgumentException('Request has no bound proof campaign.');
    $campaign = $this->entities->getStorage('proof_campaign')->load((int) $row['proof_campaign_id']);
    if (!$campaign || $campaign->get('generation_status')->value !== 'ready'
      || (int) $campaign->get('prospect_id')->target_id !== (int) $row['prospect_id']) throw new \RuntimeException('Proof campaign is not ready or has a foreign owner.');
    $storage = $this->entities->getStorage('proof_variant');
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('campaign_id', (int) $row['proof_campaign_id'])->sort('direction_id')->execute();
    $hashes = [];
    $root = realpath(dirname(\Drupal::root()) . '/web/proofs/' . $campaign->get('campaign_id')->value);
    if (!$root) throw new \RuntimeException('Proof artifact root is absent.');
    foreach ($storage->loadMultiple($ids) as $variant) {
      $direction = (string) $variant->get('direction_id')->value;
      $stored = (string) $variant->get('artifact_path')->value;
      $path = realpath(str_starts_with($stored, '/') ? $stored : dirname(\Drupal::root()) . '/' . $stored);
      if (!in_array($direction, ['a', 'b', 'c'], TRUE) || isset($hashes[$direction]) || !$path
        || $path !== $root . '/' . $direction . '/index.html' || !is_file($path)) throw new \RuntimeException('Proof bytes are missing, outside their campaign or not a core direction.');
      $dna = json_decode((string) $variant->get('design_dna')->value, TRUE) ?: [];
      if (in_array($dna['source'] ?? '', ['stub', 'no_image_pilot_v1'], TRUE)) throw new \RuntimeException('Synthetic proofs cannot pass automatic release.');
      $hashes[$direction] = hash_file('sha256', $path);
    }
    ksort($hashes);
    if (array_keys($hashes) !== ['a', 'b', 'c']) throw new \RuntimeException('Exactly three directions are required.');
    return ['request_id' => $id, 'customer_id' => (int) $row['customer_id'], 'campaign_id' => (int) $row['proof_campaign_id'],
      'artifact_hashes' => $hashes, 'research_sha256' => hash('sha256', json_encode($research, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
      'policy_version' => AutomatedProofPolicy::VERSION];
  }

  /** Atomic reveal + personal standard/v2 outbox. No send; no generic mail. */
  public function release(int $id, array $research, array $evidence, string $reviewer, array $notification): array {
    $tx = $this->database->startTransaction();
    try {
      $row = $this->request($id);
      if (!$row) throw new \InvalidArgumentException('Unknown request.');
      $context = $this->context($id, $research);
      $key = 'website-request:' . $id . ':proofs:' . $row['proof_campaign_id'] . ':qa-v1';
      $customer = $this->database->select('famtastic_customer', 'c')->fields('c')->condition('id', $row['customer_id'])->execute()->fetchAssoc();
      if (!$customer || empty($customer['verified_at']) || ($notification['notification_key'] ?? '') !== $key
        || ($notification['recipient'] ?? '') !== mb_strtolower((string) $customer['email'])
        || trim((string) ($notification['subject'] ?? '')) === '' || trim((string) ($notification['body'] ?? '')) === '') throw new \InvalidArgumentException('Exact verified recipient, scoped key and approved personal content are required.');
      $notificationHash = hash('sha256', json_encode($notification, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
      // Retry validates identical current bytes, QA, research and email, not just a key.
      $candidate = $row;
      $candidate['proof_review_status'] = 'owner_review';
      $decision = AutomatedProofPolicy::decide($candidate, $context['artifact_hashes'], $context['research_sha256'], $evidence, $reviewer);
      $decision['notification_key'] = $key;
      $decision['notification_sha256'] = $notificationHash;
      $eventKey = 'website-proof:automated-release:' . $id . ':' . $row['proof_campaign_id'];
      $prior = $this->database->select('famtastic_event', 'e')->fields('e', ['payload'])->condition('event_key', $eventKey)->execute()->fetchField();
      if ($prior !== FALSE) {
        if (json_decode($prior, TRUE, flags: JSON_THROW_ON_ERROR) !== $decision
          || !in_array($row['proof_review_status'], ['customer_ready', 'notified', 'selected'], TRUE)) throw new \RuntimeException('Previous QA release differs; require a new campaign, never overwrite sent history.');
        return $this->receipt($id, $key, TRUE);
      }
      if ($row['proof_review_status'] !== 'owner_review') throw new \RuntimeException('This proof set is not awaiting QA.');
      $now = $this->time->getCurrentTime();
      // NULL means no human approved this. Evidence records the automation actor.
      $changed = $this->database->update('famtastic_project_request')->fields([
        'proof_review_status' => 'customer_ready', 'proof_approved_by_uid' => NULL, 'proof_approved_at' => $now, 'changed' => $now,
      ])->condition('id', $id)->condition('proof_campaign_id', $row['proof_campaign_id'])->condition('proof_review_status', 'owner_review')->execute();
      if ($changed !== 1) throw new \RuntimeException('Concurrent proof release; retry the exact operation.');
      $snapshot = $research + ['reviewed_by' => $reviewer, 'review_policy' => AutomatedProofPolicy::VERSION, 'reviewed_at' => gmdate(DATE_ATOM, $now)];
      $wire = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
      $this->database->merge('famtastic_website_proof_research_snapshot')->key('website_request_id', $id)->fields([
        'proof_campaign_id' => (int) $row['proof_campaign_id'], 'snapshot_json' => $wire, 'snapshot_hash' => hash('sha256', $wire),
        'approved_by_uid' => 0, 'approved_at' => $now, 'created' => $now, 'changed' => $now,
      ])->execute();
      $existingMail = $this->database->select('famtastic_notification_outbox', 'n')->fields('n')->condition('notification_key', $key)->execute()->fetchAssoc();
      if ($existingMail) throw new \RuntimeException('Personalized notification key already exists without this QA release.');
      $this->portal->queueNotification($key, 'transactional', $notification['recipient'], $notification['subject'], $notification['body'], 'standard', 2);
      // Uncertain SMTP delivery must be reconciled, not automatically retried.
      $this->database->update('famtastic_notification_outbox')->fields(['max_attempts' => 1])->condition('notification_key', $key)->execute();
      $this->database->update('famtastic_notification_outbox')->fields(['status' => 'superseded', 'changed' => $now])
        ->condition('notification_key', 'website-request:' . $id . ':owner-proof-review:%', 'LIKE')->condition('status', ['queued', 'retry'], 'IN')->execute();
      if (!$this->ledger->recordEvent($eventKey, 'website_request.proofs_automated_qa_approved', $decision, (int) $row['prospect_id'], (int) $row['proof_campaign_id'])) throw new \RuntimeException('Concurrent QA event; retry the exact operation.');
      return $this->receipt($id, $key, FALSE);
    }
    catch (\Throwable $e) { $tx->rollBack(); throw $e; }
  }

  private function request(int $id): array|false { return $this->database->select('famtastic_project_request', 'r')->fields('r')->condition('id', $id)->execute()->fetchAssoc(); }
  private function receipt(int $id, string $key, bool $duplicate): array {
    $outbox = $this->database->select('famtastic_notification_outbox', 'n')->fields('n', ['id', 'status', 'attempts', 'notification_key'])->condition('notification_key', $key)->execute()->fetchAssoc();
    if (!$outbox) throw new \RuntimeException('QA decision is missing its notification record.');
    return ['request_id' => $id, 'duplicate' => $duplicate, 'proof_review_status' => $this->request($id)['proof_review_status'], 'outbox' => $outbox,
      'policy_version' => AutomatedProofPolicy::VERSION, 'email_sent_by_this_operation' => FALSE];
  }
}
