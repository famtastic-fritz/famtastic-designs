<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * L0 support triage drafts (AUTONOMOUS_CUSTOMER_SERVICE step B2).
 *
 * Every inbound message gets exactly one deterministic draft reply, classified
 * by SupportIntentClassifier. Nothing sends from here: approval happens in the
 * admin queue, and only an owner decision moves a draft toward the proven
 * outbox path. Low-confidence and "other" intents are flagged escalate so the
 * human sees they need personal attention first.
 */
final class SupportDraftService {

  /** Intent => [template_id, safe draft body]. No prices, no promises. */
  private const TEMPLATES = [
    'status' => ['status_v1', "Thanks for checking in \u2014 I'm reviewing your project right now and will follow up with a concrete status shortly."],
    'revision' => ['revision_v1', "Got your change request. I'll review the details and confirm the plan and timing with you before anything is applied."],
    'billing' => ['billing_v1', "Thanks for the billing note \u2014 I'm pulling up your record now and will come back to you with specifics."],
    'technical' => ['technical_v1', "Sorry for the trouble \u2014 I'm looking into this now and will reply with what I find and the next steps."],
    'other' => ['other_v1', "Thanks for reaching out \u2014 someone will get back to you shortly about this."],
  ];

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly SupportIntentClassifier $classifier,
    private readonly SupportSlaService $sla,
  ) {}

  /**
   * Creates the L0 draft for one inbound message. Idempotent per message.
   *
   * @return array{draft_id: int|null, intent: string, confidence: float, escalate: bool}
   */
  public function createForMessage(int $messageId): array {
    $existing = $this->database->select('famtastic_support_draft', 'd')
      ->fields('d', ['id', 'intent', 'confidence', 'escalate'])
      ->condition('message_id', $messageId)
      ->execute()->fetchAssoc();
    if ($existing) {
      return [
        'draft_id' => (int) $existing['id'], 'intent' => (string) $existing['intent'],
        'confidence' => (float) $existing['confidence'], 'escalate' => (bool) $existing['escalate'],
      ];
    }

    $message = $this->database->select('famtastic_inbound_message', 'm')
      ->fields('m', ['id', 'thread_public_id', 'subject', 'body', 'received_at'])
      ->condition('id', $messageId)
      ->execute()->fetchAssoc();
    if (!$message) {
      return ['draft_id' => NULL, 'intent' => SupportIntentClassifier::FALLBACK, 'confidence' => 0.0, 'escalate' => TRUE];
    }

    $result = $this->classifier->classify((string) $message['subject'], (string) $message['body']);
    [$templateId, $body] = self::TEMPLATES[$result['intent']];

    $now = $this->time->getRequestTime();
    $id = (int) $this->database->insert('famtastic_support_draft')->fields([
      'message_id' => $messageId,
      'thread_public_id' => (string) $message['thread_public_id'],
      'intent' => $result['intent'],
      'confidence' => $result['confidence'],
      'escalate' => (int) $result['escalate'],
      'template_id' => $templateId,
      'body' => $body,
      'status' => 'pending',
      'sla_target_seconds' => $this->sla->targetSeconds($result['intent']),
      'created' => $now,
    ])->execute();

    return ['draft_id' => $id, 'intent' => $result['intent'], 'confidence' => $result['confidence'], 'escalate' => $result['escalate']];
  }

  /**
   * Records an owner decision on a pending draft.
   *
   * Approve queues the reply into the notification outbox addressed to the
   * original sender (resolved through the customer directory hash). This is
   * still not auto-send: it reuses the same reviewed outbox path as every
   * other transactional message.
   */
  public function decide(int $draftId, int $reviewerUid, bool $approve, string $editedBody = ''): bool {
    $draft = $this->database->select('famtastic_support_draft', 'd')
      ->fields('d')
      ->condition('id', $draftId)
      ->condition('status', 'pending')
      ->execute()->fetchAssoc();
    if (!$draft) {
      return FALSE;
    }

    $now = $this->time->getRequestTime();
    $this->database->update('famtastic_support_draft')
      ->fields([
        'status' => $approve ? 'approved' : 'rejected',
        'body' => $editedBody !== '' ? $editedBody : (string) $draft['body'],
        'reviewer_uid' => $reviewerUid,
        'decided_at' => $now,
      ])
      ->condition('id', $draftId)
      ->execute();

    if ($approve) {
      $recipient = $this->resolveSenderEmail((int) $draft['message_id']);
      $key = 'support-draft:' . $draftId;
      if ($recipient === '') {
        // Fail closed: sender not in the customer directory — alert the owner
        // instead of queueing customer mail to an unknown address.
        $this->database->merge('famtastic_notification_outbox')
          ->key('notification_key', $key . ':unresolved')
          ->insertFields([
            'notification_key' => $key . ':unresolved',
            'category' => 'operational', 'recipient' => '',
            'subject' => 'Support draft approved but sender unresolved',
            'body' => 'Draft #' . $draftId . ' (thread ' . $draft['thread_public_id'] . ') could not be matched to a customer email. Resolve manually before sending.',
            'status' => 'queued', 'attempts' => 0, 'max_attempts' => 5,
            'available_at' => $now, 'created' => $now, 'changed' => $now,
          ])
          ->execute();
        return TRUE;
      }
      $this->database->merge('famtastic_notification_outbox')
        ->key('notification_key', $key)
        ->insertFields([
          'notification_key' => $key,
          'category' => 'operational',
          'recipient' => mb_strtolower($recipient),
          'subject' => 'Re: your FAMtastic message',
          'body' => $editedBody !== '' ? $editedBody : (string) $draft['body'],
          'status' => 'queued', 'attempts' => 0, 'max_attempts' => 5,
          'available_at' => $now, 'created' => $now, 'changed' => $now,
        ])
        ->execute();
    }
    return TRUE;
  }

  /** Resolves a sender email from the thread/customer directory; fails closed. */
  private function resolveSenderEmail(int $messageId): string {
    $hash = (string) $this->database->select('famtastic_inbound_message', 'm')
      ->fields('m', ['sender_hash'])
      ->condition('id', $messageId)
      ->execute()->fetchField();
    if ($hash === '') {
      return '';
    }
    $customers = $this->database->select('famtastic_customer', 'c')
      ->fields('c', ['email'])
      ->execute();
    foreach ($customers as $row) {
      if (hash_equals($hash, hash('sha256', mb_strtolower((string) $row->email)))) {
        return (string) $row->email;
      }
    }
    return '';
  }

}
