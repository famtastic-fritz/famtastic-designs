<?php

declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;

/** Staff working copies. No transport or outbox dependency. */
final class CommunicationDraftService {
  public function __construct(private readonly Connection $database, private readonly ClientMessagingService $messages) {}

  public static function recipes(): array {
    return ['reply' => 'Personal reply', 'clarify' => 'Ask for missing information', 'follow_up' => 'Follow up on a conversation', 'acknowledge' => 'Acknowledge a message'];
  }

  public static function recipe(string $purpose, string $name, string $detail): string {
    if (!isset(self::recipes()[$purpose])) throw new \InvalidArgumentException('Choose a supported message purpose.');
    if (trim($detail) === '') throw new \InvalidArgumentException('Add the question or update before using this template.');
    $intro = match ($purpose) {
      'clarify' => 'Could you help me with one detail?',
      'follow_up' => 'I’m following up on our conversation.',
      'acknowledge' => 'Thank you for your message.',
      default => 'Thank you for getting in touch.',
    };
    return 'Hi ' . ($name ?: 'there') . ",\n\n" . $intro . "\n\n" . trim($detail) . "\n\nShay-Shay";
  }

  public function source(AccountInterface $account, string $thread): array {
    if (!$account->hasPermission('administer famtastic pipeline')) throw new \RuntimeException('Staff access required.');
    $detail = $this->messages->detail($account, $thread);
    $record = $detail['thread'];
    $source = ['thread' => $thread, 'recipient' => $record['customer_email'], 'subject' => $record['subject'], 'messages' => array_map(static fn(array $m): array => ['id' => $m['id'], 'body' => $m['body'], 'author_type' => $m['author_type']], $detail['messages'])];
    return ['detail' => $detail, 'source' => $source, 'digest' => hash('sha256', json_encode($source, JSON_THROW_ON_ERROR))];
  }

  public function load(AccountInterface $account, string $thread): array {
    $this->source($account, $thread);
    return $this->database->select('famtastic_message_draft', 'd')->fields('d')->condition('thread', $thread)->condition('uid', (int) $account->id())->execute()->fetchAssoc() ?: ['revision' => 0, 'body' => '', 'purpose' => 'reply', 'source_digest' => '', 'status' => 'draft'];
  }

  public function save(AccountInterface $account, string $thread, string $body, string $purpose, int $revision, string $sourceDigest): array {
    $source = $this->source($account, $thread);
    if (!hash_equals($source['digest'], $sourceDigest)) throw new \RuntimeException('This conversation changed. Reload and review the newest message before saving.');
    if (!isset(self::recipes()[$purpose]) || trim($body) === '' || mb_strlen($body) > 20000) throw new \InvalidArgumentException('Choose a purpose and enter 1–20,000 characters.');
    $fields = ['body' => trim(strip_tags($body)), 'purpose' => $purpose, 'source_digest' => $sourceDigest, 'revision' => $revision + 1, 'status' => 'draft', 'changed' => time()];
    $transaction = $this->database->startTransaction();
    try {
    if ($revision === 0) {
      try { $this->database->insert('famtastic_message_draft')->fields($fields + ['thread' => $thread, 'uid' => (int) $account->id()])->execute(); }
      catch (\Exception $e) { throw new \RuntimeException('A draft already exists. Reload before editing.', 0, $e); }
    }
    else {
      $updated = $this->database->update('famtastic_message_draft')->fields($fields)->condition('thread', $thread)->condition('uid', (int) $account->id())->condition('revision', $revision)->execute();
      if (!$updated) throw new \RuntimeException('The draft changed in another window. Reload before editing.');
    }
    $this->database->insert('famtastic_message_draft_revision')->fields($fields + ['thread' => $thread, 'uid' => (int) $account->id()])->execute();
    return $fields;
    }
    catch (\Throwable $e) { $transaction->rollBack(); throw $e; }
  }

  /** Review is bound to exact text, revision, source and recipient. */
  public function reviewDigest(array $draft, array $source): string {
    return hash('sha256', json_encode([$draft['body'], (int) $draft['revision'], $source['digest'], $source['source']['recipient']], JSON_THROW_ON_ERROR));
  }

  public function send(AccountInterface $account, string $thread, int $revision, string $reviewDigest): array {
    $transaction = $this->database->startTransaction();
    try {
      $draft = $this->load($account, $thread);
      $source = $this->source($account, $thread);
      if ($draft['status'] === 'queued' && (int) $draft['revision'] === $revision) return $this->messages->reply($account, $thread, $draft['body'], hash('sha256', 'staff-draft:' . $thread . ':' . $account->id() . ':' . $revision));
      if ((int) $draft['revision'] !== $revision || !hash_equals($draft['source_digest'], $source['digest']) || !hash_equals($this->reviewDigest($draft, $source), $reviewDigest)) throw new \RuntimeException('The draft or recipient changed. Preview and review again.');
      $claimed = $this->database->update('famtastic_message_draft')->fields(['status' => 'queued'])->condition('thread', $thread)->condition('uid', (int) $account->id())->condition('revision', $revision)->condition('status', 'draft')->execute();
      if ($claimed !== 1) throw new \RuntimeException('The draft changed while sending. Reload and review again.');
      $result = $this->messages->reply($account, $thread, $draft['body'], hash('sha256', 'staff-draft:' . $thread . ':' . $account->id() . ':' . $revision), $source['source']);
      return $result;
    }
    catch (\Throwable $e) { $transaction->rollBack(); throw $e; }
  }
}
