<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;

/**
 * First-response SLA clocks for inbound support (AUTONOMOUS_CUSTOMER_SERVICE B4).
 *
 * Targets are per intent and intentionally conservative; the clock runs from
 * the inbound message's received_at until an owner decision lands on the
 * draft. Breaches queue owner alerts through the proven outbox — idempotent
 * per draft, so repeat scans never spam.
 */
final class SupportSlaService {

  private const TARGETS = [
    'technical' => 4 * 3600,
    'status' => 8 * 3600,
    'billing' => 8 * 3600,
    'revision' => 24 * 3600,
    'other' => 24 * 3600,
  ];

  private const OWNER_ALERT_CATEGORY = 'operational';

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /** First-response target in seconds for an intent. */
  public function targetSeconds(string $intent): int {
    return self::TARGETS[$intent] ?? self::TARGETS['other'];
  }

  /**
   * Returns pending drafts whose first response is overdue. The clock runs
   * from when the CUSTOMER actually sent the message (inbound received_at),
   * not from when our draft row was created.
   *
   * @return array<int, array{id: int, intent: string, minutes_over: int, thread_public_id: string}>
   */
  public function breaches(): array {
    $now = $this->time->getRequestTime();
    $rows = $this->database->select('famtastic_support_draft', 'd');
    $rows->join('famtastic_inbound_message', 'm', 'm.id = d.message_id');
    $rows->fields('d', ['id', 'intent', 'thread_public_id', 'sla_target_seconds'])
      ->fields('m', ['received_at'])
      ->condition('d.status', 'pending');
    $breached = [];
    foreach ($rows->execute() as $row) {
      $target = (int) $row->sla_target_seconds ?: $this->targetSeconds((string) $row->intent);
      $age = $now - (int) $row->received_at;
      if ($age > $target) {
        $breached[] = [
          'id' => (int) $row->id,
          'intent' => (string) $row->intent,
          'minutes_over' => (int) round(($age - $target) / 60),
          'thread_public_id' => (string) $row->thread_public_id,
        ];
      }
    }
    usort($breached, static fn(array $a, array $b): int => $b['minutes_over'] <=> $a['minutes_over']);
    return $breached;
  }

  /** Queues one owner alert per breached draft. Idempotent via outbox key. */
  public function alertBreaches(): int {
    $ownerEmail = mb_strtolower(trim((string) $this->configFactory
      ->get('famtastic_pipeline.settings')->get('notification_to_email')));
    if ($ownerEmail === '') {
      return 0;
    }
    $now = $this->time->getRequestTime();
    $queued = 0;
    foreach ($this->breaches() as $breach) {
      $key = 'support-sla-breach:' . $breach['id'];
      $existing = (int) $this->database->select('famtastic_notification_outbox', 'o')
        ->condition('notification_key', $key)
        ->countQuery()->execute()->fetchField();
      if ($existing > 0) {
        continue;
      }
      $this->database->insert('famtastic_notification_outbox')->fields([
        'notification_key' => $key,
        'category' => self::OWNER_ALERT_CATEGORY,
        'recipient' => $ownerEmail,
        'subject' => sprintf('SLA breach: %s reply overdue by %d min', $breach['intent'], $breach['minutes_over']),
        'body' => sprintf(
          "Draft #%d (%s intent, thread %s) passed its %s first-response target and is still awaiting your decision.\nApprove or reject it in the support drafts queue.",
          $breach['id'], $breach['intent'], $breach['thread_public_id'], $breach['intent']
        ),
        'status' => 'queued', 'attempts' => 0, 'max_attempts' => 5,
        'available_at' => $now, 'created' => $now, 'changed' => $now,
      ])->execute();
      $queued++;
    }
    return $queued;
  }

}
