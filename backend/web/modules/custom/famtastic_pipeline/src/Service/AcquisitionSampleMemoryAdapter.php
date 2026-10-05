<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/** Synthetic capture adapter: no network/mail/provider capability exists here. */
final class AcquisitionSampleMemoryAdapter {

  public function __construct(private readonly Connection $database, private readonly TimeInterface $time, private readonly OperationalLedger $ledger, private readonly AcquisitionSampleSequenceService $sequences) {}

  /** Manifest authorizes exact synthetic address, message ID and frozen hash. */
  public function capture(int $sequenceId, int $messageId, array $manifest, string $signature): array {
    $secret = (string) getenv('FAMTASTIC_ACQUISITION_MEMORY_SECRET');
    if (strlen($secret) < 32 || !hash_equals(hash_hmac('sha256', json_encode($manifest, JSON_THROW_ON_ERROR), $secret), $signature)) throw new \InvalidArgumentException('synthetic_manifest_signature_invalid');
    if (($manifest['transport'] ?? '') !== 'synthetic_memory_only' || (int) ($manifest['sequence_id'] ?? 0) !== $sequenceId || (int) ($manifest['expires'] ?? 0) <= $this->time->getRequestTime() || empty($manifest['approval_ref']) || !isset($manifest['messages'][(string) $messageId])) throw new \InvalidArgumentException('synthetic_manifest_required');
    $transaction = $this->database->startTransaction();
    $sequence = $this->database->select('famtastic_acquisition_sequence', 's')->fields('s')->condition('id', $sequenceId)->forUpdate()->execute()->fetchAssoc();
    $message = $this->database->select('famtastic_email_message', 'm')->fields('m')->condition('id', $messageId)->condition('template_key', AcquisitionSampleSequenceService::MESSAGE_KIND)->forUpdate()->execute()->fetchAssoc();
    $content = $this->database->select('famtastic_acquisition_message', 'm')->fields('m')->condition('message_id', $messageId)->execute()->fetchAssoc();
    $exact = $manifest['messages'][(string) $messageId];
    if (!$sequence || !$message || !$content || (int) $content['invitation_id'] !== (int) $sequence['invitation_id'] || !preg_match('/^[^@\s]+@[a-z0-9.-]+\.test$/iD', (string) $message['recipient_address']) || !hash_equals((string) $message['recipient_address'], (string) ($exact['recipient'] ?? '')) || !hash_equals((string) $content['content_hash'], (string) ($exact['content_hash'] ?? '')) || !hash_equals((string) $content['content_hash'], hash('sha256', (string) $content['snapshot']))) throw new \InvalidArgumentException('synthetic_exact_binding_required');
    if ($message['status'] === 'sent' && $message['provider'] === 'acquisition_memory') return ['message_id' => $messageId, 'provider_message_id' => $message['provider_message_id'], 'duplicate' => TRUE, 'inbox_delivery' => FALSE];
    if (!in_array($messageId, array_column($this->sequences->due($sequenceId), 'message_id'), TRUE)) throw new \RuntimeException('sample_message_not_due_or_stopped');
    $snapshot = json_decode((string) $content['snapshot'], TRUE, 32, JSON_THROW_ON_ERROR);
    if (empty($snapshot['html']) || !str_contains($snapshot['html'], 'data-famtastic-email-brand=') || !str_contains($snapshot['html'], 'cid:connect-qr') || !str_contains($snapshot['html'], 'Shay-Shay') || !str_contains($snapshot['html'], 'https://famtasticdesigns.com/connect/commercial.mp4') || !str_contains($snapshot['body'], 'https://famtasticdesigns.com/connect') || preg_match('/^[a-f0-9]{64}$/D', (string) ($snapshot['draft_hash'] ?? '')) !== 1) throw new \InvalidArgumentException('synthetic_content_invariants_required');
    $providerId = '<sample-test-' . $messageId . '-' . substr((string) $content['content_hash'], 0, 16) . '@memory.invalid>';
    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_email_message')->fields(['status' => 'sent', 'provider' => 'acquisition_memory', 'provider_message_id' => $providerId, 'sent_at' => $now, 'changed' => $now])->condition('id', $messageId)->condition('status', 'held')->execute();
    $this->ledger->recordEvent('acquisition:memory:' . $messageId, 'email.sent', ['message_id' => $messageId, 'content_id' => $content['content_id'], 'transport' => 'synthetic_memory_only', 'inbox_delivery' => FALSE, 'approval_ref' => $manifest['approval_ref']], (int) $message['prospect_id'], (int) $message['campaign_id'], provider: 'acquisition_memory');
    unset($transaction);
    return ['message_id' => $messageId, 'provider_message_id' => $providerId, 'duplicate' => FALSE, 'inbox_delivery' => FALSE, 'captured' => $snapshot];
  }

}
