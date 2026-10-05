<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/** One exact owner-approved message through the existing native SMTP boundary. */
final class AcquisitionSampleExactAdapter {

  public function __construct(private readonly Connection $database, private readonly TimeInterface $time, private readonly OperationalLedger $ledger, private readonly AcquisitionSampleSequenceService $sequences, private readonly OutreachMailer $mailer) {}

  /** No route/worker calls this. Disabled gates are checked before reservation. */
  public function dispatch(array $manifest, string $signature): array {
    $secret = (string) getenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET');
    $json = json_encode($manifest, JSON_THROW_ON_ERROR);
    if (strlen($secret) < 32 || !hash_equals(hash_hmac('sha256', $json, $secret), $signature)) throw new \InvalidArgumentException('acquisition_owner_signature_invalid');
    $now = $this->time->getRequestTime();
    if (($manifest['schema'] ?? '') !== 'famtastic.acquisition-exact-send.v1' || ($manifest['transport'] ?? '') !== 'native_smtp' || ($manifest['cap'] ?? 0) !== 1 || (int) ($manifest['expires'] ?? 0) <= $now || (int) $manifest['expires'] > $now + 3600 || !preg_match('/^[a-zA-Z0-9:_.-]{8,128}$/D', (string) ($manifest['approval_ref'] ?? ''))) throw new \InvalidArgumentException('acquisition_exact_manifest_invalid');
    foreach (['provider_permission_receipt', 'history_receipt', 'release_proof'] as $receipt) {
      if (($manifest[$receipt]['status'] ?? '') !== 'owner_reviewed' || !preg_match('/^[a-f0-9]{64}$/D', (string) ($manifest[$receipt]['sha256'] ?? '')) || empty($manifest[$receipt]['reference'])) throw new \InvalidArgumentException('acquisition_reviewed_receipts_required');
    }
    $messageId = (int) ($manifest['message_id'] ?? 0);
    $sequenceId = (int) ($manifest['sequence_id'] ?? 0);
    if (!$messageId || !$sequenceId || !hash_equals($this->mailer->fromAddress(), (string) ($manifest['from'] ?? ''))) throw new \InvalidArgumentException('acquisition_exact_sender_required');
    // Native sequence reconciliation observes current suppression, replies and purchases.
    $due = array_column($this->sequences->due($sequenceId), 'message_id');
    $transaction = $this->database->startTransaction();
    try {
      $sequence = $this->database->select('famtastic_acquisition_sequence', 's')->fields('s')->condition('id', $sequenceId)->forUpdate()->execute()->fetchAssoc();
      $message = $this->database->select('famtastic_email_message', 'm')->fields('m')->condition('id', $messageId)->condition('template_key', AcquisitionSampleSequenceService::MESSAGE_KIND)->forUpdate()->execute()->fetchAssoc();
      $content = $this->database->select('famtastic_acquisition_message', 'c')->fields('c')->condition('message_id', $messageId)->execute()->fetchAssoc();
      $sample = $sequence ? $this->database->select('famtastic_acquisition_sample', 's')->fields('s')->condition('id', $sequence['invitation_id'])->execute()->fetchAssoc() : FALSE;
      if (!$sequence || !$message || !$content || !$sample || (int) $content['invitation_id'] !== (int) $sample['id'] || (int) $message['campaign_id'] !== (int) $sample['campaign_id'] || !hash_equals((string) $sample['recipient_hash'], (string) $message['recipient_hash']) || !hash_equals((string) $sample['recipient_hash'], (string) $sequence['recipient_hash']) || !hash_equals((string) $sample['recipient_hash'], $this->ledger->contactHash((string) $message['recipient_address'])) || (int) $sample['prospect_id'] !== (int) $message['prospect_id'] || (int) $sample['prospect_id'] !== (int) $sequence['prospect_id'] || !hash_equals((string) $message['recipient_address'], (string) ($manifest['recipient'] ?? '')) || !hash_equals((string) $content['content_id'], (string) ($manifest['content_id'] ?? '')) || !hash_equals((string) $content['content_hash'], (string) ($manifest['content_hash'] ?? '')) || !hash_equals((string) $content['content_hash'], hash('sha256', (string) $content['snapshot'])) || !hash_equals((string) $sample['qualification_ref'], (string) ($manifest['qualification_ref'] ?? '')) || !hash_equals((string) $sample['evidence_hash'], (string) ($manifest['invitation_evidence_hash'] ?? ''))) throw new \InvalidArgumentException('acquisition_exact_binding_required');
      $manifestHash = hash('sha256', $json);
      $old = $this->database->select('famtastic_acquisition_dispatch', 'd')->fields('d')->condition('message_id', $messageId)->execute()->fetchAssoc();
      if ($old) {
        if ($old['status'] === 'accepted' && hash_equals((string) $old['manifest_hash'], $manifestHash)) return ['message_id' => $messageId, 'provider_message_id' => $old['provider_message_id'], 'duplicate' => TRUE, 'inbox_delivery' => FALSE];
        throw new \RuntimeException('acquisition_dispatch_reserved_or_uncertain_no_retry');
      }
      if (!in_array($messageId, $due, TRUE) || $message['status'] !== 'held' || $sequence['status'] !== 'active' || !AcquisitionSampleGuard::live($sample, $now) || $this->ledger->isSuppressed((string) $message['recipient_address'])) throw new \RuntimeException('acquisition_message_not_due_or_stopped');
      $snapshot = json_decode((string) $content['snapshot'], TRUE, 32, JSON_THROW_ON_ERROR);
      if (!hash_equals((string) ($snapshot['tracking_key'] ?? ''), (string) $message['tracking_key']) || !hash_equals((string) ($snapshot['unsubscribe_key'] ?? ''), (string) $message['unsubscribe_key']) || !hash_equals((string) ($snapshot['invitation_token_hash'] ?? ''), (string) $sample['token_hash']) || trim((string) ($snapshot['subject'] ?? '')) !== $message['subject'] || trim((string) ($snapshot['body'] ?? '')) !== $message['body_snapshot']) throw new \InvalidArgumentException('acquisition_native_header_content_drift');
      if (empty($snapshot['postal_address']) || !str_contains((string) $snapshot['html'], 'data-famtastic-email-brand=') || !str_contains((string) $snapshot['html'], 'cid:connect-qr') || !str_contains((string) $snapshot['body'], 'Shay-Shay') || count($snapshot['attachments'] ?? []) !== ((int) $content['day'] === 0 ? 3 : 1)) throw new \InvalidArgumentException('acquisition_frozen_release_content_required');
      foreach ($snapshot['attachments'] as $attachment) {
        $bytes = base64_decode((string) ($attachment['bytes_base64'] ?? ''), TRUE);
        if ($bytes === FALSE || strlen($bytes) > 1048576 || !hash_equals((string) ($attachment['sha256'] ?? ''), hash('sha256', $bytes))) throw new \InvalidArgumentException('acquisition_frozen_media_integrity_required');
      }
      if ($this->database->select('famtastic_acquisition_dispatch','d')->condition('approval_ref',$manifest['approval_ref'])->countQuery()->execute()->fetchField()) throw new \RuntimeException('acquisition_approval_cap_exhausted');
      $this->mailer->assertAcquisitionTransportAllowed();
      $this->database->insert('famtastic_acquisition_dispatch')->fields(['message_id' => $messageId, 'content_hash' => $content['content_hash'], 'manifest_hash' => $manifestHash, 'approval_ref' => $manifest['approval_ref'], 'status' => 'reserved', 'created' => $now, 'changed' => $now])->execute();
      if ($this->database->update('famtastic_email_message')->fields(['status' => 'dispatching', 'changed' => $now])->condition('id', $messageId)->condition('status', 'held')->execute() !== 1) throw new \RuntimeException('acquisition_reservation_conflict');
    }
    catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
    unset($transaction); // Durable reservation before any network side effect.
    try {
      if ($this->database->select('famtastic_acquisition_sequence','s')->fields('s',['status'])->condition('id',$sequenceId)->execute()->fetchField() !== 'active' || $this->ledger->isSuppressed((string) $message['recipient_address'])) throw new \RuntimeException('acquisition_suppression_after_reservation');
      $providerId = $this->mailer->sendFrozenAcquisition((string) $message['recipient_address'], $snapshot, 'https://famtasticdesigns.com/web/api/pipeline/email/unsubscribe/confirm/' . $message['unsubscribe_key']);
      $this->database->update('famtastic_acquisition_dispatch')->fields(['status' => 'accepted', 'provider_message_id' => $providerId, 'changed' => $now])->condition('message_id', $messageId)->execute();
      $this->database->update('famtastic_email_message')->fields(['status' => 'sent', 'provider' => 'smtp', 'provider_message_id' => $providerId, 'sent_at' => $now, 'changed' => $now])->condition('id', $messageId)->execute();
      $this->ledger->recordEvent('acquisition:smtp:' . $messageId, 'email.sent', ['message_id' => $messageId, 'content_id' => $content['content_id'], 'content_hash' => $content['content_hash'], 'approval_ref' => $manifest['approval_ref'], 'smtp_accepted' => TRUE, 'inbox_delivery' => FALSE], (int) $message['prospect_id'], (int) $message['campaign_id'], provider: 'smtp');
      return ['message_id' => $messageId, 'provider_message_id' => $providerId, 'duplicate' => FALSE, 'inbox_delivery' => FALSE];
    }
    catch (\Throwable $error) {
      $this->database->update('famtastic_acquisition_dispatch')->fields(['status' => 'uncertain', 'changed' => $now])->condition('message_id', $messageId)->execute();
      $this->ledger->recordEvent('acquisition:uncertain:' . $messageId, 'acquisition.dispatch_uncertain', ['message_id' => $messageId, 'content_hash' => $content['content_hash'], 'approval_ref' => $manifest['approval_ref'], 'retry_allowed' => FALSE], (int) $message['prospect_id'], (int) $message['campaign_id']);
      throw new \RuntimeException('acquisition_dispatch_uncertain_manual_reconciliation_required', 0, $error);
    }
  }

}
