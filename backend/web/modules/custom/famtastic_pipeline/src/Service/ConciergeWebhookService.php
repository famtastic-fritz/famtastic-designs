<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Records Concierge lifecycle facts without authorizing outbound communication.
 *
 * Inkbox remains the channel mailbox. This service stores only the durable
 * lifecycle facts needed by FAMtastic Connections: provider IDs, channel,
 * direction, delivery state, and a hashed contact match. It deliberately does
 * not copy message bodies into Drupal or send/reply to a customer.
 */
final class ConciergeWebhookService {

  private const EVENT_TYPES = [
    'message.received',
    'message.sent',
    'message.forwarded',
    'message.delivered',
    'message.bounced',
    'message.failed',
    'imessage.received',
    'imessage.reaction_received',
    'imessage.sent',
    'imessage.delivered',
    'imessage.delivery_failed',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly OperationalLedger $ledger,
  ) {}

  /**
   * Adds the web-form lead itself to the shared Connections timeline.
   */
  public function recordPublicLead(int $prospectId, int $intakeId, string $source): void {
    $this->ledger->recordEvent(
      sprintf('concierge:lead:%d:intake:%d', $prospectId, $intakeId),
      'concierge.lead_received',
      [
        'source' => mb_substr($source, 0, 128),
        'intake_id' => $intakeId,
        'account_continuation_available' => TRUE,
        'account_continuation_invited' => FALSE,
        'communication_mode' => 'human_review_required',
      ],
      $prospectId,
      provider: 'famtastic_public_intake',
    );
  }

  /**
   * Validates and records one signed Inkbox event exactly once.
   */
  public function ingest(array $envelope): array {
    $eventId = trim((string) ($envelope['id'] ?? ''));
    $eventType = trim((string) ($envelope['event_type'] ?? ''));
    if ($eventId === '' || strlen($eventId) > 255 || !in_array($eventType, self::EVENT_TYPES, TRUE)) {
      throw new \InvalidArgumentException('inkbox_event_invalid');
    }

    $data = is_array($envelope['data'] ?? NULL) ? $envelope['data'] : [];
    $message = is_array($data['message'] ?? NULL) ? $data['message'] : [];
    $contact = $this->contactFor($eventType, $message);
    $prospectId = $contact === '' ? NULL : $this->matchingProspect($contact, str_starts_with($eventType, 'imessage.'));
    $occurredAt = $this->timestamp((string) ($envelope['timestamp'] ?? ''));
    $summary = [
      'channel' => str_starts_with($eventType, 'message.') ? 'email' : 'imessage',
      'direction' => str_ends_with($eventType, '.received') ? 'inbound' : 'outbound',
      'delivery_state' => $this->deliveryState($eventType, $message),
      'message_id' => $this->scalar($message['id'] ?? ''),
      'conversation_id' => $this->scalar($message['thread_id'] ?? $message['conversation_id'] ?? ''),
      'contact_hash' => $contact === '' ? '' : $this->ledger->contactHash($contact),
      'matched_prospect' => $prospectId !== NULL,
      'human_review_required' => TRUE,
    ];
    $new = $this->ledger->recordEvent(
      'inkbox:' . $eventId,
      'concierge.' . $this->normalizedType($eventType),
      $summary,
      $prospectId,
      provider: 'inkbox',
      providerEventId: $eventId,
      occurredAt: $occurredAt,
    );

    return [
      'accepted' => TRUE,
      'duplicate' => !$new,
      'event_type' => $eventType,
      'prospect_id' => $prospectId,
    ];
  }

  private function contactFor(string $eventType, array $message): string {
    if (str_starts_with($eventType, 'message.')) {
      $recipients = $message['to_addresses'] ?? [];
      if (is_array($recipients)) {
        $recipients = reset($recipients) ?: '';
      }
      $candidate = str_ends_with($eventType, '.received')
        ? ($message['from_address'] ?? '')
        : $recipients;
      $email = mb_strtolower(trim((string) $candidate));
      return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
    $candidate = str_ends_with($eventType, '.received')
      ? ($message['sender_number'] ?? $message['remote_number'] ?? '')
      : ($message['remote_number'] ?? $message['sender_number'] ?? '');
    $phone = preg_replace('/[^0-9+]/', '', (string) $candidate);
    return $phone === '+' || strlen($phone) < 7 ? '' : $phone;
  }

  private function matchingProspect(string $contact, bool $phone): ?int {
    $storage = $this->entityTypeManager->getStorage('famtastic_prospect');
    $field = $phone ? 'public_phone' : 'public_email';
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->sort('id', 'DESC')
      ->range(0, 1);
    if ($phone) {
      $digits = ltrim($contact, '+');
      $query->condition($field, array_values(array_unique([$contact, $digits, '+' . $digits])), 'IN');
    }
    else {
      $query->condition($field, $contact);
    }
    $ids = $query->execute();
    return $ids ? (int) reset($ids) : NULL;
  }

  private function normalizedType(string $eventType): string {
    return str_starts_with($eventType, 'message.')
      ? 'email.' . substr($eventType, strlen('message.'))
      : $eventType;
  }

  private function deliveryState(string $eventType, array $message): string {
    if (isset($message['status']) && is_scalar($message['status'])) {
      return mb_substr((string) $message['status'], 0, 64);
    }
    return mb_substr(substr($eventType, strrpos($eventType, '.') + 1), 0, 64);
  }

  private function timestamp(string $value): ?int {
    if ($value === '') {
      return NULL;
    }
    try {
      return (new \DateTimeImmutable($value))->getTimestamp();
    }
    catch (\Exception) {
      return NULL;
    }
  }

  private function scalar(mixed $value): string {
    return is_scalar($value) ? mb_substr(trim((string) $value), 0, 255) : '';
  }

}
