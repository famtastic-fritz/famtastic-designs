<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/** Native held 0/3/7 schedule and stop authority. Intentionally has no sender. */
final class AcquisitionSampleSequenceService {

  public const MESSAGE_KIND = 'acquisition_sample_v1';
  public const DAYS = [0, 3, 7];

  public function __construct(private readonly Connection $database, private readonly TimeInterface $time, private readonly OperationalLedger $ledger, private readonly ?\Drupal\Core\Config\ConfigFactoryInterface $configFactory = NULL) {}

  /** Exact immutable content, stored in existing native email ledger for review. */
  public function stage(int $invitationId, string $email, string $token, array $drafts): array {
    if (array_keys($drafts) !== self::DAYS) throw new \InvalidArgumentException('three_sample_messages_required');
    $email = mb_strtolower(trim($email));
    $transaction = $this->database->startTransaction();
    $sample = $this->database->select('famtastic_acquisition_sample', 's')->fields('s')->condition('id', $invitationId)->condition('token_hash', AcquisitionSampleGuard::tokenHash($token))->forUpdate()->execute()->fetchAssoc();
    if (!$sample || !AcquisitionSampleGuard::live($sample, $this->time->getRequestTime()) || !hash_equals((string) $sample['recipient_hash'], $this->ledger->contactHash($email)) || $this->ledger->isSuppressed($email)) throw new \InvalidArgumentException('sample_recipient_unavailable');
    if (empty($sample['eligible_at']) || empty($sample['qualification_ref'])) throw new \InvalidArgumentException('sample_outreach_qualification_required');
    if (isset(json_decode((string) $sample['bindings'], TRUE)['_preparation'])) throw new \InvalidArgumentException('generic_d0_stage_required');
    $now = $this->time->getRequestTime();
    $sequence = $this->database->select('famtastic_acquisition_sequence', 's')->fields('s')->condition('invitation_id', $invitationId)->execute()->fetchAssoc();
    if (!$sequence) {
      $id = (int) $this->database->insert('famtastic_acquisition_sequence')->fields([
        'invitation_id' => $invitationId, 'recipient_hash' => $sample['recipient_hash'], 'campaign_id' => $sample['campaign_id'], 'prospect_id' => $sample['prospect_id'], 'status' => 'held', 'created' => $now, 'changed' => $now,
      ])->execute();
    }
    else $id = (int) $sequence['id'];
    $ids = [];
    foreach ($drafts as $day => $draft) {
      $key = 'sample-v1:' . $invitationId . ':day:' . $day;
      $old = $this->database->select('famtastic_email_message', 'm')->fields('m')->condition('message_key', $key)->execute()->fetchAssoc();
      $tracking = $old ? (string) $old['tracking_key'] : bin2hex(random_bytes(24));
      $unsubscribe = $old ? (string) $old['unsubscribe_key'] : bin2hex(random_bytes(24));
      if ((int) ($draft['day'] ?? -1) !== $day || ($draft['niche'] ?? '') !== $sample['niche']) throw new \InvalidArgumentException('sample_content_identity_mismatch');
      $snapshot = AcquisitionSampleEmail::compile($draft, json_decode((string) $sample['bindings'], TRUE, 32, JSON_THROW_ON_ERROR), $token, $unsubscribe, (string) ($draft['qr_sha256'] ?? ''), (string) ($this->configFactory?->get('famtastic_pipeline.settings')->get('outreach_postal_address') ?? ''), $tracking);
      $subject = trim((string) ($snapshot['subject'] ?? ''));
      $body = trim((string) ($snapshot['body'] ?? ''));
      if ($subject === '' || mb_strlen($subject) > 512 || preg_match('/[\r\n]/', $subject) || $body === '' || strlen($body) > 60000 || !str_contains($body, 'https://famtasticdesigns.com/connect') || !str_contains($body, 'https://famtasticdesigns.com/connect/commercial.mp4')) throw new \InvalidArgumentException('sample_email_content_invalid');
      $snapshotJson = json_encode($snapshot, JSON_THROW_ON_ERROR);
      $contentHash = hash('sha256', $snapshotJson);
      if ($old) {
        $content = $this->database->select('famtastic_acquisition_message', 'm')->fields('m')->condition('message_id', (int) $old['id'])->execute()->fetchAssoc();
        if ($old['subject'] !== $subject || $old['body_snapshot'] !== $body || !$content || !hash_equals((string) $content['content_hash'], $contentHash)) throw new \InvalidArgumentException('sample_content_replay_changed');
        $ids[] = (int) $old['id'];
        continue;
      }
      $messageId = (int) $this->database->insert('famtastic_email_message')->fields([
        'message_key' => $key, 'recipient_hash' => $sample['recipient_hash'], 'recipient_address' => $email,
        'prospect_id' => (int) $sample['prospect_id'], 'campaign_id' => (int) $sample['campaign_id'],
        'template_key' => self::MESSAGE_KIND, 'template_version' => 1, 'subject' => $subject, 'body_snapshot' => $body,
        'proof_url' => 'https://famtasticdesigns.com/samples/' . $token, 'status' => 'held',
        'tracking_key' => $tracking, 'unsubscribe_key' => $unsubscribe, 'created' => $now, 'changed' => $now,
      ])->execute();
      $this->database->insert('famtastic_acquisition_message')->fields([
        'message_id' => $messageId, 'invitation_id' => $invitationId, 'day' => $day,
        'content_id' => $snapshot['content_id'], 'content_hash' => $contentHash, 'draft_hash' => $snapshot['draft_hash'], 'snapshot' => $snapshotJson,
      ])->execute();
      $ids[] = $messageId;
    }
    // Re-evaluate historical reply/purchase state even for a newly staged sequence.
    $this->reconcile($id);
    unset($transaction);
    return ['sequence_id' => $id, 'message_ids' => $ids, 'status' => 'held', 'dispatch_implemented' => TRUE, 'real_dispatch_enabled' => FALSE];
  }

  public function senderAccount(): array {
    return AcquisitionSampleGuard::senderAccount($this->configFactory);
  }

  /** Revalidate the append-only signed evidence, including current account. */
  public function genericAuthorization(array $sample, ?string $frozenCreative = NULL): array {
    if (empty($sample['eligible_at']) || empty($sample['qualification_ref'])) throw new \InvalidArgumentException('sample_outreach_qualification_required');
    $payload = $this->database->select('famtastic_event', 'e')->fields('e', ['payload'])->condition('event_key', 'acquisition:generic:authorization:' . $sample['id'] . ':' . $sample['qualification_ref'])->condition('event_type', 'acquisition.generic_authorized')->execute()->fetchField();
    $event = $payload ? json_decode((string) $payload, TRUE, 32, JSON_THROW_ON_ERROR) : [];
    $authorization = $event['authorization'] ?? [];
    $json = json_encode($authorization, JSON_THROW_ON_ERROR);
    $secret = (string) getenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET');
    if (strlen($secret) < 32 || !hash_equals((string) $sample['qualification_ref'], hash('sha256', $json)) || !hash_equals(hash_hmac('sha256', $json, $secret), (string) ($event['signature'] ?? ''))) throw new \InvalidArgumentException('generic_signed_authorization_required');
    AcquisitionSampleGuard::genericAuthorization($authorization, $sample, $this->senderAccount(), $this->time->getRequestTime(), $frozenCreative);
    return $authorization;
  }

  /** Only the approved new generic D0; native held ledger, no dispatch effect. */
  public function stageGenericD0(int $invitationId, string $email, string $token): array {
    $email = mb_strtolower(trim($email));
    $transaction = $this->database->startTransaction();
    $sample = $this->database->select('famtastic_acquisition_sample', 's')->fields('s')->condition('id', $invitationId)->condition('token_hash', AcquisitionSampleGuard::tokenHash($token))->forUpdate()->execute()->fetchAssoc();
    if (!$sample || !AcquisitionSampleGuard::live($sample, $this->time->getRequestTime()) || !hash_equals($sample['recipient_hash'], $this->ledger->contactHash($email)) || $this->ledger->isSuppressed($email) || (json_decode((string) $sample['bindings'], TRUE)['_preparation']['classification'] ?? '') !== 'supplied_generic_preparation') throw new \InvalidArgumentException('sample_recipient_unavailable');
    $authorization = $this->genericAuthorization($sample);
    $key = 'sample-v1:' . $invitationId . ':day:0';
    $old = $this->database->select('famtastic_email_message', 'm')->fields('m')->condition('message_key', $key)->execute()->fetchAssoc();
    $tracking = $old ? $old['tracking_key'] : bin2hex(random_bytes(24));
    $unsubscribe = $old ? $old['unsubscribe_key'] : bin2hex(random_bytes(24));
    $snapshot = AcquisitionSampleEmail::compileGeneric($authorization, $token, $unsubscribe, $tracking, (string) $this->configFactory?->get('famtastic_pipeline.settings')->get('outreach_postal_address'));
    $json = json_encode($snapshot, JSON_THROW_ON_ERROR);
    $hash = hash('sha256', $json);
    if ($old) {
      $content = $this->database->select('famtastic_acquisition_message', 'c')->fields('c')->condition('message_id', $old['id'])->execute()->fetchAssoc();
      if (!$content || !hash_equals($content['content_hash'], $hash)) throw new \InvalidArgumentException('sample_content_replay_changed');
      $sequenceId = $this->database->select('famtastic_acquisition_sequence', 's')->fields('s', ['id'])->condition('invitation_id', $invitationId)->execute()->fetchField();
      return ['sequence_id' => (int) $sequenceId, 'message_ids' => [(int) $old['id']], 'status' => 'held', 'duplicate' => TRUE, 'real_dispatch_enabled' => FALSE];
    }
    $now = $this->time->getRequestTime();
    $sequenceId = (int) $this->database->insert('famtastic_acquisition_sequence')->fields(['invitation_id' => $invitationId, 'recipient_hash' => $sample['recipient_hash'], 'campaign_id' => $sample['campaign_id'], 'prospect_id' => $sample['prospect_id'], 'status' => 'held', 'created' => $now, 'changed' => $now])->execute();
    $messageId = (int) $this->database->insert('famtastic_email_message')->fields(['message_key' => $key, 'recipient_hash' => $sample['recipient_hash'], 'recipient_address' => $email, 'prospect_id' => $sample['prospect_id'], 'campaign_id' => $sample['campaign_id'], 'template_key' => self::MESSAGE_KIND, 'template_version' => 1, 'subject' => $snapshot['subject'], 'body_snapshot' => $snapshot['body'], 'proof_url' => 'https://famtasticdesigns.com/samples/' . $token, 'status' => 'held', 'tracking_key' => $tracking, 'unsubscribe_key' => $unsubscribe, 'created' => $now, 'changed' => $now])->execute();
    $this->database->insert('famtastic_acquisition_message')->fields(['message_id' => $messageId, 'invitation_id' => $invitationId, 'day' => 0, 'content_id' => $snapshot['content_id'], 'content_hash' => $hash, 'draft_hash' => $snapshot['draft_hash'], 'snapshot' => $json])->execute();
    $this->reconcile($sequenceId);
    unset($transaction);
    return ['sequence_id' => $sequenceId, 'message_ids' => [$messageId], 'status' => 'held', 'duplicate' => FALSE, 'real_dispatch_enabled' => FALSE];
  }

  /** Scheduling authorization is separate from exact provider/customer dispatch. */
  public function activate(int $id, int $start, string $approval): void {
    if ($start < $this->time->getRequestTime() || preg_match('/^[a-zA-Z0-9:_.-]{8,128}$/D', $approval) !== 1) throw new \InvalidArgumentException('exact_schedule_approval_required');
    $this->reconcile($id);
    if ($this->database->update('famtastic_acquisition_sequence')->fields(['status' => 'active', 'started_at' => $start, 'approval_ref' => $approval, 'changed' => $this->time->getRequestTime()])->condition('id', $id)->condition('status', 'held')->execute() !== 1) throw new \RuntimeException('sample_sequence_not_held');
  }

  /** Native due projection only. Never queues or sends through a generic worker. */
  public function due(int $id): array {
    $this->reconcile($id);
    $sequence = $this->database->select('famtastic_acquisition_sequence', 's')->fields('s')->condition('id', $id)->execute()->fetchAssoc();
    if (!$sequence || $sequence['status'] !== 'active') return [];
    $result = [];
    foreach (self::DAYS as $day) {
      $available = (int) $sequence['started_at'] + $day * 86400;
      if ($available > $this->time->getRequestTime()) continue;
      $message = $this->database->select('famtastic_email_message', 'm')->fields('m')->condition('message_key', 'sample-v1:' . $sequence['invitation_id'] . ':day:' . $day)->condition('status', 'held')->condition('template_key', self::MESSAGE_KIND)->execute()->fetchAssoc();
      if ($message) $result[] = ['message_id' => (int) $message['id'], 'day' => $day, 'available_at' => $available, 'dispatch_approval_required' => TRUE];
    }
    return $result;
  }

  /** Matches native facts at dispatch/readiness boundary, including preexisting facts. */
  private function reconcile(int $id): void {
    $sequence = $this->database->select('famtastic_acquisition_sequence', 's')->fields('s')->condition('id', $id)->execute()->fetchAssoc();
    if (!$sequence || $sequence['status'] === 'stopped') return;
    $sample = $this->database->select('famtastic_acquisition_sample', 's')->fields('s')->condition('id', (int) $sequence['invitation_id'])->execute()->fetchAssoc();
    $reason = !$sample || !AcquisitionSampleGuard::live($sample, $this->time->getRequestTime()) ? 'expired_or_revoked' : NULL;
    if (!$reason && isset(json_decode((string) $sample['bindings'], TRUE)['_preparation'])) {
      $snapshot = $this->database->select('famtastic_acquisition_message', 'c')->fields('c', ['snapshot'])->condition('invitation_id', (int) $sample['id'])->condition('day', 0)->execute()->fetchField();
      try { $this->genericAuthorization($sample, $snapshot ? (json_decode((string) $snapshot, TRUE)['creative_approval_record'] ?? NULL) : NULL); }
      catch (\InvalidArgumentException) { $reason = 'generic_authorization_stale'; }
    }
    if (!$reason && $this->database->select('famtastic_consent', 'c')->condition('contact_hash', $sequence['recipient_hash'])->condition('consent_type', 'outreach')->condition('status', ['unsubscribed', 'bounced', 'complained', 'suppressed'], 'IN')->countQuery()->execute()->fetchField()) $reason = 'suppressed';
    // Current Commerce fulfillment is authoritative, never legacy paid-order math.
    if (!$reason && $this->database->schema()->tableExists('famtastic_commerce_fulfillment')) {
      $customer = $sample['customer_id'] ?? $sample['pending_customer_id'] ?? NULL;
      if (!$customer) $customer = $this->database->select('famtastic_customer', 'c')->fields('c', ['id'])->condition('prospect_id', (int) $sequence['prospect_id'])->execute()->fetchField();
      if (!$customer && isset(json_decode((string) $sample['bindings'], TRUE)['_preparation'])) {
        $email = $this->database->select('famtastic_prospect', 'p')->fields('p', ['public_email'])->condition('id', (int) $sample['prospect_id'])->execute()->fetchField();
        $customer = $this->database->select('famtastic_customer', 'c')->fields('c', ['id'])->condition('email', mb_strtolower(trim((string) $email)))->condition('verified_at', 0, '>')->execute()->fetchField();
      }
      if ($customer && $this->database->select('famtastic_commerce_fulfillment', 'f')->condition('customer_id', $customer)->countQuery()->execute()->fetchField()) $reason = 'purchase';
    }
    if (!$reason && $this->database->schema()->tableExists('famtastic_inbound_message') && $this->database->select('famtastic_inbound_message', 'm')->condition('sender_hash', $sequence['recipient_hash'])->countQuery()->execute()->fetchField()) $reason = 'incoming_reply_review';
    if (!$reason && $this->database->select('famtastic_event', 'e')->condition('prospect_id', (int) $sequence['prospect_id'])->condition('event_type', ['email.replied', 'payment.fulfillment_started'], 'IN')->countQuery()->execute()->fetchField()) $reason = 'reply_or_purchase';
    if ($reason) self::stopContact($this->database, (string) $sequence['recipient_hash'], $reason, $this->time->getRequestTime());
  }

  /** Synchronous mutation from trusted inbox/provider/Commerce boundaries. */
  public static function stopContact(Connection $database, string $hash, string $reason, int $now): void {
    if (!$database->schema()->tableExists('famtastic_acquisition_sequence')) return;
    if (preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) return;
    $sequences = $database->select('famtastic_acquisition_sequence', 's')->fields('s', ['invitation_id'])->condition('recipient_hash', $hash)->condition('status', ['held', 'active'], 'IN')->execute()->fetchCol();
    foreach ($sequences as $invitation) {
      if ($database->schema()->tableExists('famtastic_email_message')) $database->update('famtastic_email_message')->fields(['status' => 'suppressed', 'changed' => $now])->condition('template_key', self::MESSAGE_KIND)->condition('message_key', 'sample-v1:' . $invitation . ':day:%', 'LIKE')->condition('status', ['held', 'staged', 'queued'], 'IN')->execute();
    }
    $database->update('famtastic_acquisition_sequence')->fields(['status' => 'stopped', 'stop_reason' => mb_substr($reason, 0, 32), 'stopped_at' => $now, 'changed' => $now])->condition('recipient_hash', $hash)->condition('status', ['held', 'active'], 'IN')->execute();
  }

  /** Native reply dedup; imported inbox messages retain unknown machine quality. */
  public static function recordReply(Connection $database, string $contactHash, string $sourceId, bool $authenticatedHuman, int $now): void {
    if (!$database->schema()->tableExists('famtastic_acquisition_sample') || !$database->schema()->tableExists('famtastic_event')) return;
    $samples = $database->select('famtastic_acquisition_sample', 's')->fields('s', ['id','prospect_id','campaign_id'])->condition('recipient_hash',$contactHash)->execute()->fetchAll();
    foreach ($samples as $sample) {
      $key = 'acquisition:reply:' . $sample->id . ':' . hash('sha256',$sourceId);
      if ($database->select('famtastic_event','e')->condition('event_key',$key)->countQuery()->execute()->fetchField()) continue;
      try { $database->insert('famtastic_event')->fields(['event_key'=>$key,'event_type'=>$authenticatedHuman?'acquisition.human_reply':'acquisition.reply_received','prospect_id'=>$sample->prospect_id,'campaign_id'=>$sample->campaign_id,'payload'=>json_encode(['source_id_hash'=>hash('sha256',$sourceId),'machine_activity'=>$authenticatedHuman?FALSE:'unknown','human_verified'=>$authenticatedHuman],JSON_THROW_ON_ERROR),'occurred_at'=>$now,'recorded_at'=>$now,'provider'=>$authenticatedHuman?'native_portal':'native_inbox'])->execute(); }
      catch (\Drupal\Core\Database\IntegrityConstraintViolationException) { /* Native dedup race. */ }
    }
  }

  public static function observeEvent(Connection $database, string $type, ?int $prospect, int $now): void {
    $reason = match ($type) {
      'email.replied' => 'human_reply', 'payment.fulfillment_started' => 'purchase',
      'email.bounced' => 'hard_bounce', 'email.complained' => 'complaint',
      default => NULL,
    };
    if (!$reason || !$prospect || !$database->schema()->tableExists('famtastic_acquisition_sequence')) return;
    if ($database->schema()->tableExists('famtastic_acquisition_request')) {
      $mappings = $database->select('famtastic_acquisition_request', 'm')->fields('m', ['invitation_id'])->condition('request_prospect_id', $prospect)->execute()->fetchCol();
      foreach ($mappings as $invitation) {
        $mappedHash = $database->select('famtastic_acquisition_sample', 's')->fields('s', ['recipient_hash'])->condition('id', $invitation)->execute()->fetchField();
        if ($mappedHash) self::stopContact($database, (string) $mappedHash, $reason, $now);
      }
    }
    $hashes = $database->select('famtastic_acquisition_sequence', 's')->fields('s', ['recipient_hash'])->condition('prospect_id', $prospect)->execute()->fetchCol();
    foreach ($hashes as $hash) self::stopContact($database, (string) $hash, $reason, $now);
  }

  public function clickDestination(string $tracking): array {
    $message = $this->database->select('famtastic_email_message', 'm')->fields('m')->condition('tracking_key', $tracking)->execute()->fetchAssoc();
    if (!$message || $message['template_key'] !== self::MESSAGE_KIND) return ['is_sample' => FALSE, 'destination' => NULL];
    $url = (string) $message['proof_url'];
    if (!preg_match('#^https://famtasticdesigns\.com/samples/([a-f0-9]{64})$#D', $url, $match)) return ['is_sample' => TRUE, 'destination' => NULL];
    $sample = $this->database->select('famtastic_acquisition_sample', 's')->fields('s')->condition('token_hash', AcquisitionSampleGuard::tokenHash($match[1]))->condition('recipient_hash', $message['recipient_hash'])->condition('campaign_id', (int) $message['campaign_id'])->execute()->fetchAssoc();
    return ['is_sample' => TRUE, 'destination' => $sample && AcquisitionSampleGuard::live($sample, $this->time->getRequestTime()) ? $url : NULL];
  }

  /** POST only caller boundary; GET unsubscribe is never allowed for sample mail. */
  public function unsubscribe(string $key): bool {
    $message = $this->database->select('famtastic_email_message', 'm')->fields('m')->condition('unsubscribe_key', $key)->condition('template_key', self::MESSAGE_KIND)->execute()->fetchAssoc();
    if (!$message) return FALSE;
    $this->ledger->recordConsent((string) $message['recipient_address'], 'unsubscribed', (int) $message['prospect_id']);
    self::stopContact($this->database, (string) $message['recipient_hash'], 'opt_out', $this->time->getRequestTime());
    return TRUE;
  }

}
