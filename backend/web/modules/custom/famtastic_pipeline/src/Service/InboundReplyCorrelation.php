<?php

declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;
use Drupal\Core\Database\Connection;

/** Correlation uses sent receipts plus exact durable conversation bindings. */
final class InboundReplyCorrelation {
  public function __construct(private readonly Connection $database) {}
  public function resolve(array $message): string {
    $threads = [];
    foreach (array_slice((array) ($message['references'] ?? []), -50) as $reference) {
      if (!is_string($reference) || strlen($reference) > 1000) continue;
      $rows = $this->database->select('famtastic_notification_outbox', 'n')->fields('n', ['notification_key'])
        ->condition('status', 'sent')->condition('provider_message_id', $reference)
        ->condition('recipient', strtolower(trim((string) ($message['from'] ?? ''))))->execute()->fetchCol();
      foreach ($rows as $key) {
        $query = $this->database->select('famtastic_portal_message', 'm');
        $query->join('famtastic_portal_thread', 't', 't.id = m.thread_id');
        $ids = $query->fields('t', ['public_id'])->condition('m.notification_key', $key)->execute()->fetchCol();
        if (preg_match('/^support:(\d+):reply:\d+$/D', $key, $match)) {
          $query = $this->database->select('famtastic_support_case', 's');
          $query->join('famtastic_portal_thread', 't', 't.id = s.thread_id');
          $ids = array_merge($ids, $query->fields('t', ['public_id'])->condition('s.id', (int) $match[1])->execute()->fetchCol());
        }
        if (preg_match('/^support-draft:(\d+)$/D', $key, $match)) {
          $ids = array_merge($ids, $this->database->select('famtastic_support_draft', 'd')->fields('d', ['thread_public_id'])
            ->condition('id', (int) $match[1])->condition('status', 'approved')->execute()->fetchCol());
        }
        if (preg_match('/^website-request:(\d+):/', $key, $match)) {
          // Existing explicit request-to-conversation bindings only. Never
          // choose the sender's newest project or invent a new thread here.
          $ids = array_merge($ids, $this->database->select('famtastic_portal_thread', 't')->fields('t', ['public_id'])
            ->condition('source_key', 'website-request:' . $match[1] . ':direct-project')->execute()->fetchCol());
        }
        foreach ($ids as $id) $threads[$id] = TRUE;
      }
    }
    return count($threads) === 1 ? (string) array_key_first($threads) : '';
  }
}
