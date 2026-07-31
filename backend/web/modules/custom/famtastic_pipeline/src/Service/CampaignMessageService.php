<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Entity\Prospect;

/**
 * Stages and sends attributable campaign email behind an explicit gate.
 */
final class CampaignMessageService {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly OperationalLedger $ledger,
    private readonly TokenManager $tokens,
    private readonly OutreachMailer $mailer,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Creates one idempotent staged proof-ready message.
   */
  public function prepare(Prospect $prospect, int $proofCampaignId): array {
    $email = mb_strtolower(trim((string) $prospect->get('public_email')->value));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new \RuntimeException('Prospect has no valid outreach email.');
    }
    if ($this->ledger->isSuppressed($email)) {
      throw new \RuntimeException('Prospect email is suppressed.');
    }
    $campaignId = $this->campaignId((string) $prospect->get('campaign')->value);
    if (!$campaignId) {
      throw new \RuntimeException('Prospect campaign attribution is missing.');
    }
    $messageKey = sprintf('proof-ready:%d:%d:%d', $campaignId, $prospect->id(), $proofCampaignId);
    $existing = $this->loadBy('message_key', $messageKey);
    if ($existing) {
      return $existing;
    }
    $now = $this->time->getRequestTime();
    $id = (int) $this->database->insert('famtastic_email_message')
      ->fields([
        'message_key' => $messageKey,
        'prospect_id' => (int) $prospect->id(),
        'campaign_id' => $campaignId,
        'recipient_hash' => $this->ledger->contactHash($email),
        'template_key' => 'proof_ready',
        'template_version' => 1,
        'subject' => sprintf('Three website directions for %s', $prospect->label()),
        'status' => 'staged',
        'tracking_key' => bin2hex(random_bytes(24)),
        'unsubscribe_key' => bin2hex(random_bytes(24)),
        'created' => $now,
        'changed' => $now,
      ])
      ->execute();
    $this->ledger->recordEvent(
      'email.staged:' . $id,
      'email.staged',
      ['message_id' => $id, 'template' => 'proof_ready', 'template_version' => 1],
      (int) $prospect->id(),
      $campaignId,
    );
    return $this->load($id);
  }

  /**
   * Queues staged messages only after the campaign is approved.
   */
  public function queueApprovedCampaign(string $campaignKey): int {
    $campaign = $this->database->select('famtastic_campaign', 'c')
      ->fields('c')
      ->condition('campaign_key', $campaignKey)
      ->execute()
      ->fetchAssoc();
    if (!$campaign || $campaign['status'] !== 'approved') {
      throw new \RuntimeException('Campaign must be explicitly approved before messages can be queued.');
    }
    $ids = $this->database->select('famtastic_email_message', 'm')
      ->fields('m', ['id', 'prospect_id'])
      ->condition('campaign_id', $campaign['id'])
      ->condition('status', 'staged')
      ->execute()
      ->fetchAllKeyed();
    foreach ($ids as $messageId => $prospectId) {
      $this->ledger->enqueue(
        'outreach.send:message:' . $messageId,
        'outreach.send',
        ['message_id' => (int) $messageId],
        (int) $prospectId,
      );
      $this->database->update('famtastic_email_message')
        ->fields(['status' => 'queued', 'changed' => $this->time->getRequestTime()])
        ->condition('id', $messageId)
        ->execute();
    }
    return count($ids);
  }

  /**
   * Sends through memory transport or the explicitly enabled real transport.
   */
  public function send(int $messageId): array {
    $message = $this->load($messageId);
    if (!$message) {
      throw new \RuntimeException('Email message does not exist.');
    }
    if (in_array($message['status'], ['sent', 'delivered', 'opened', 'clicked'], TRUE)) {
      return $message;
    }
    $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load($message['prospect_id']);
    if (!$prospect) {
      throw new \RuntimeException('Prospect no longer exists.');
    }
    $email = mb_strtolower(trim((string) $prospect->get('public_email')->value));
    if ($this->ledger->isSuppressed($email)) {
      $this->setStatus($messageId, 'suppressed');
      throw new \RuntimeException('Message suppressed before send.');
    }
    $campaignStatus = $this->database->select('famtastic_campaign', 'c')
      ->fields('c', ['status'])
      ->condition('id', $message['campaign_id'])
      ->execute()
      ->fetchField();
    if ($campaignStatus !== 'approved') {
      throw new \RuntimeException('Campaign is not approved at send time.');
    }
    $transport = getenv('FAMTASTIC_EMAIL_TRANSPORT') ?: Settings::get('famtastic_email_transport', 'disabled');
    if (!in_array($transport, ['memory', 'real'], TRUE)) {
      throw new \RuntimeException('Outreach transport is disabled.');
    }
    if ($transport === 'real') {
      $allowed = filter_var(
        getenv('FAMTASTIC_ALLOW_REAL_OUTREACH') ?: Settings::get('famtastic_allow_real_outreach', FALSE),
        FILTER_VALIDATE_BOOL,
      );
      if (!$allowed) {
        throw new \RuntimeException('Real outreach requires explicit environment approval.');
      }
      $base = rtrim((string) (getenv('FAMTASTIC_PUBLIC_BASE_URL') ?: 'https://famtasticdesigns.com'), '/');
      $body = sprintf(
        "We created three website directions for %s.\n\nView them: %s/api/pipeline/email/click/%s\n\nUnsubscribe: %s/api/pipeline/email/unsubscribe/%s",
        $prospect->label(),
        $base,
        $message['tracking_key'],
        $base,
        $message['unsubscribe_key'],
      );
      $this->mailer->send($email, $message['subject'], $body);
      $provider = 'drupal_mail';
    }
    else {
      $provider = 'memory';
    }
    $now = $this->time->getRequestTime();
    $providerMessageId = $provider . '_' . $messageId . '_' . bin2hex(random_bytes(8));
    $this->database->update('famtastic_email_message')
      ->fields([
        'status' => $transport === 'memory' ? 'delivered' : 'sent',
        'provider' => $provider,
        'provider_message_id' => $providerMessageId,
        'sent_at' => $now,
        'delivered_at' => $transport === 'memory' ? $now : NULL,
        'changed' => $now,
      ])
      ->condition('id', $messageId)
      ->execute();
    $this->ledger->recordEvent(
      'email.sent:' . $providerMessageId,
      'email.sent',
      ['message_id' => $messageId, 'provider' => $provider],
      (int) $prospect->id(),
      (int) $message['campaign_id'],
      provider: $provider,
      providerEventId: $providerMessageId,
    );
    if ($transport === 'memory') {
      $this->ledger->recordEvent(
        'email.delivered:' . $providerMessageId,
        'email.delivered',
        ['message_id' => $messageId],
        (int) $prospect->id(),
        (int) $message['campaign_id'],
        provider: $provider,
        providerEventId: $providerMessageId . ':delivered',
      );
    }
    return $this->load($messageId);
  }

  /**
   * Persists one idempotent signed provider lifecycle event.
   */
  public function providerEvent(string $eventId, string $providerMessageId, string $type, array $payload): bool {
    if ($eventId === '' || strlen($eventId) > 255 || $providerMessageId === '') {
      throw new \InvalidArgumentException('Provider event id and message id are required.');
    }
    $allowed = ['delivered', 'bounced', 'complained'];
    if (!in_array($type, $allowed, TRUE)) {
      throw new \InvalidArgumentException('Unsupported provider event type.');
    }
    $message = $this->loadBy('provider_message_id', $providerMessageId);
    if (!$message) {
      throw new \InvalidArgumentException('Unknown provider message.');
    }
    $isNew = $this->ledger->recordEvent(
      'email.provider:' . $eventId,
      'email.' . $type,
      ['message_id' => (int) $message['id'], 'provider_payload' => $payload],
      (int) $message['prospect_id'],
      (int) $message['campaign_id'],
      provider: (string) $message['provider'],
      providerEventId: $eventId,
    );
    if (!$isNew) {
      return FALSE;
    }
    $this->setStatus((int) $message['id'], $type);
    if (in_array($type, ['bounced', 'complained'], TRUE)) {
      $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load($message['prospect_id']);
      if ($prospect && ($email = (string) $prospect->get('public_email')->value)) {
        $this->ledger->recordConsent($email, $type === 'bounced' ? 'bounced' : 'complained', (int) $prospect->id());
      }
    }
    return TRUE;
  }

  /**
   * Records an open or click exactly once per event key.
   */
  public function track(string $trackingKey, string $type): ?Prospect {
    if (!in_array($type, ['opened', 'clicked'], TRUE)) {
      throw new \InvalidArgumentException('Unsupported tracking type.');
    }
    $message = $this->loadBy('tracking_key', $trackingKey);
    if (!$message) {
      return NULL;
    }
    $this->ledger->recordEvent(
      'email.' . $type . ':' . $message['id'],
      'email.' . $type,
      ['message_id' => (int) $message['id']],
      (int) $message['prospect_id'],
      (int) $message['campaign_id'],
    );
    $this->setStatus((int) $message['id'], $type);
    return $this->entityTypeManager->getStorage('famtastic_prospect')->load($message['prospect_id']);
  }

  /**
   * Suppresses a recipient from an opaque unsubscribe link.
   */
  public function unsubscribe(string $unsubscribeKey): bool {
    $message = $this->loadBy('unsubscribe_key', $unsubscribeKey);
    if (!$message) {
      return FALSE;
    }
    $prospect = $this->entityTypeManager->getStorage('famtastic_prospect')->load($message['prospect_id']);
    if (!$prospect) {
      return FALSE;
    }
    $this->ledger->recordConsent((string) $prospect->get('public_email')->value, 'unsubscribed', (int) $prospect->id());
    $this->setStatus((int) $message['id'], 'unsubscribed');
    return TRUE;
  }

  public function load(int $id): ?array {
    return $this->loadBy('id', $id);
  }

  private function loadBy(string $field, string|int $value): ?array {
    $allowed = ['id', 'message_key', 'tracking_key', 'unsubscribe_key', 'provider_message_id'];
    if (!in_array($field, $allowed, TRUE)) {
      throw new \InvalidArgumentException('Invalid email lookup.');
    }
    $record = $this->database->select('famtastic_email_message', 'm')
      ->fields('m')
      ->condition($field, $value)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    return $record ?: NULL;
  }

  private function campaignId(string $campaignKey): ?int {
    $id = $this->database->select('famtastic_campaign', 'c')
      ->fields('c', ['id'])
      ->condition('campaign_key', $campaignKey)
      ->execute()
      ->fetchField();
    return $id ? (int) $id : NULL;
  }

  private function setStatus(int $messageId, string $status): void {
    $now = $this->time->getRequestTime();
    $timestampField = match ($status) {
      'delivered' => 'delivered_at',
      'opened' => 'opened_at',
      'clicked' => 'clicked_at',
      'bounced' => 'bounced_at',
      'complained' => 'complained_at',
      'unsubscribed' => 'unsubscribed_at',
      default => NULL,
    };
    $fields = ['status' => $status, 'changed' => $now];
    if ($timestampField) {
      $fields[$timestampField] = $now;
    }
    $this->database->update('famtastic_email_message')
      ->fields($fields)
      ->condition('id', $messageId)
      ->execute();
  }

}
