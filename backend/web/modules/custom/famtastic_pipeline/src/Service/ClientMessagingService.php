<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;

/** One durable inbox over contact intake, portal conversations, and the outbox. */
final class ClientMessagingService {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    private readonly ConfigFactoryInterface $config,
    private readonly LockBackendInterface $lock,
  ) {}

  /** Projects a public intake once. This never queues or sends a message. */
  public function importIntake(int $intakeId): ?string {
    $query = $this->database->select('famtastic_intake', 'i');
    $query->join('famtastic_prospect', 'p', 'p.id = i.prospect_ref');
    $record = $query->fields('i', ['id', 'primary_goal', 'about', 'submitted_at'])
      ->fields('p', ['public_email', 'business_name'])->addField('p', 'id', 'prospect_id');
    $record = $query->condition('i.id', $intakeId)->execute()->fetchAssoc();
    if (!$record || !in_array((string) $record['primary_goal'], ['Public contact request', 'Public quote request from Solution Finder'], TRUE)) {
      return NULL;
    }
    $sourceKey = 'public-intake:' . $intakeId;
    $existing = $this->database->select('famtastic_portal_thread', 't')->fields('t', ['public_id'])
      ->condition('source_key', $sourceKey)->execute()->fetchField();
    if ($existing) return (string) $existing;
    $lockKey = 'famtastic:message-import:' . $intakeId;
    if (!$this->lock->acquire($lockKey, 30)) throw new \RuntimeException('Conversation import is already running.');
    try {
      $existing = $this->database->select('famtastic_portal_thread', 't')->fields('t', ['public_id'])
        ->condition('source_key', $sourceKey)->execute()->fetchField();
      if ($existing) return (string) $existing;
      $content = self::intakeContent((string) $record['about']);
      $now = (int) $record['submitted_at'] ?: $this->time->getRequestTime();
      $publicId = $this->uuid->generate();
      $transaction = $this->database->startTransaction();
      $threadId = (int) $this->database->insert('famtastic_portal_thread')->fields([
        'public_id' => $publicId, 'organization_id' => 0, 'kind' => 'contact',
        'subject' => mb_substr($content['subject'] ?: 'Contact from ' . $record['business_name'], 0, 255),
        'status' => 'open', 'source_key' => $sourceKey, 'source_intake_id' => $intakeId,
        'prospect_id' => (int) $record['prospect_id'],
        'contact_name' => mb_substr($content['name'] ?: (string) $record['business_name'], 0, 255),
        'contact_email' => mb_strtolower(trim((string) $record['public_email'])),
        'created' => $now, 'changed' => $now,
      ])->execute();
      $this->database->insert('famtastic_portal_message')->fields([
        'thread_id' => $threadId, 'author_uid' => 0, 'author_type' => 'customer',
        'body' => $content['body'], 'created' => $now,
      ])->execute();
      unset($transaction);
      return $publicId;
    }
    catch (\Throwable $error) {
      if (isset($transaction)) $transaction->rollBack();
      throw $error;
    }
    finally {
      $this->lock->release($lockKey);
    }
  }

  /** Extracts readable submitted content without emailing a raw JSON payload. */
  public static function intakeContent(string $raw): array {
    $offset = strpos($raw, '{');
    $data = $offset === FALSE ? NULL : json_decode(substr($raw, $offset), TRUE);
    if (!is_array($data)) {
      return ['subject' => '', 'name' => '', 'body' => 'The original contact submission is saved on the linked intake record.'];
    }
    $answers = is_array($data['answers'] ?? NULL) ? $data['answers'] : [];
    $text = static fn(mixed $value): string => is_scalar($value) ? trim(strip_tags((string) $value)) : '';
    $body = $text($data['message'] ?? $data['body'] ?? $data['notes'] ?? $answers['message'] ?? '');
    if ($body === '') {
      $lines = [];
      foreach (['businessDescription' => 'What they need', 'description' => 'Details', 'services' => 'Services', 'timeline' => 'Timing', 'budget' => 'Budget', 'referenceSites' => 'References'] as $field => $label) {
        $value = $text($answers[$field] ?? $data[$field] ?? '');
        if ($value !== '') $lines[] = $label . ': ' . $value;
      }
      $body = implode("\n\n", $lines) ?: 'A contact request was submitted. Open the linked intake for the original details.';
    }
    return ['subject' => $text($data['subject'] ?? ''), 'name' => $text($data['name'] ?? $answers['name'] ?? ''), 'body' => mb_substr($body, 0, 20000)];
  }

  /** Inbox authority comes from the signed-in Drupal account, never input. */
  private function actor(AccountInterface $account): array {
    if (!$account->isAuthenticated()) throw new \UnexpectedValueException('authentication_required');
    $staff = $account->hasPermission('administer famtastic pipeline');
    $customer = $this->database->select('famtastic_customer', 'c')->fields('c')
      ->condition('uid', (int) $account->id())->execute()->fetchAssoc();
    if (!$staff && (!$customer || empty($customer['verified_at']))) throw new \UnexpectedValueException('verification_required');
    $organizations = [];
    if ($customer) {
      $organizations = array_map('intval', $this->database->select('famtastic_membership', 'm')->fields('m', ['organization_id'])
        ->condition('customer_id', (int) $customer['id'])->condition('status', 'active')->execute()->fetchCol());
      if (!empty($customer['verified_at'])) $this->claimContacts($customer);
    }
    return ['uid' => (int) $account->id(), 'is_staff' => $staff, 'customer' => $customer ?: NULL, 'organizations' => $organizations];
  }

  /** Only a verified email owner can claim an unowned contact conversation. */
  private function claimContacts(array $customer): void {
    $organization = $this->database->select('famtastic_membership', 'm')->fields('m', ['organization_id'])
      ->condition('customer_id', (int) $customer['id'])->condition('status', 'active')->condition('role', 'owner')
      ->orderBy('created')->range(0, 1)->execute()->fetchField();
    if (!$organization) return;
    $this->database->update('famtastic_portal_thread')->fields(['organization_id' => (int) $organization, 'created_by' => (int) $customer['id']])
      ->condition('organization_id', 0)->condition('contact_email', mb_strtolower(trim((string) $customer['email'])))
      ->isNotNull('source_intake_id')->execute();
  }

  private function authorizedThread(array $actor, string $publicId): array {
    $thread = $this->database->select('famtastic_portal_thread', 't')->fields('t')
      ->condition('public_id', $publicId)->execute()->fetchAssoc();
    if (!$thread || (!$actor['is_staff'] && !in_array((int) $thread['organization_id'], $actor['organizations'], TRUE))) {
      throw new \RuntimeException('Conversation not found.');
    }
    return $thread;
  }

  public function inbox(AccountInterface $account, array $filters = []): array {
    $actor = $this->actor($account);
    $query = $this->database->select('famtastic_portal_thread', 't')->fields('t');
    if (!$actor['is_staff']) $query->condition('organization_id', $actor['organizations'] ?: [-1], 'IN');
    $records = $query->orderBy('changed', 'DESC')->orderBy('id', 'DESC')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $threads = array_map(fn(array $thread): array => $this->summary($actor, $thread), $records);
    $unread = array_sum(array_column($threads, 'unread_count'));
    $needsReply = count(array_filter($threads, static fn(array $thread): bool => $thread['needs_reply']));
    $threads = array_values(array_filter($threads, static function (array $thread) use ($filters): bool {
      if (($filters['status'] ?? '') === 'unread' && !$thread['unread_count']) return FALSE;
      if (($filters['status'] ?? '') === 'needs_reply' && !$thread['needs_reply']) return FALSE;
      if (($filters['status'] ?? '') === 'waiting' && ($thread['needs_reply'] || $thread['status'] === 'closed')) return FALSE;
      $search = trim((string) ($filters['q'] ?? ''));
      return $search === '' || mb_stripos(implode(' ', [$thread['subject'], $thread['customer_name'], $thread['customer_email'], $thread['last_message_preview']]), $search) !== FALSE;
    }));
    return [
      'ok' => TRUE, 'is_staff' => $actor['is_staff'], 'can_reply' => TRUE,
      'unread_count' => $unread, 'needs_reply_count' => $needsReply, 'threads' => $threads,
      'admin_orders_url' => $actor['is_staff'] ? '/web/admin/commerce/orders' : NULL,
      'admin_inbox_url' => $actor['is_staff'] ? '/web/admin/famtastic/messages' : NULL,
    ];
  }

  /** Lightweight navigation count, using the same account/tenant boundary. */
  public function unreadCount(AccountInterface $account): int {
    $actor = $this->actor($account);
    $query = $this->database->select('famtastic_portal_message', 'm');
    $query->join('famtastic_portal_thread', 't', 't.id = m.thread_id');
    $query->leftJoin('famtastic_portal_read', 'r', 'r.thread_id = t.id AND r.uid = :reader_uid', [':reader_uid' => $actor['uid']]);
    $query->condition('m.author_type', $actor['is_staff'] ? 'customer' : 'staff')->where('m.id > COALESCE(r.last_message_id, 0)');
    if (!$actor['is_staff']) $query->condition('t.organization_id', $actor['organizations'] ?: [-1], 'IN');
    return (int) $query->countQuery()->execute()->fetchField();
  }

  public function detail(AccountInterface $account, string $publicId): array {
    $actor = $this->actor($account);
    $thread = $this->authorizedThread($actor, $publicId);
    return ['ok' => TRUE, 'is_staff' => $actor['is_staff'], 'thread' => $this->summary($actor, $thread), 'messages' => $this->messages((int) $thread['id'], $actor['is_staff'])];
  }

  /** Read acknowledgments cover only the message the browser actually loaded. */
  public function markRead(AccountInterface $account, string $publicId, int $lastMessageId): void {
    $actor = $this->actor($account);
    $thread = $this->authorizedThread($actor, $publicId);
    $message = $this->database->select('famtastic_portal_message', 'm')->fields('m', ['id'])
      ->condition('thread_id', (int) $thread['id'])->condition('id', $lastMessageId)->execute()->fetchField();
    if (!$message) throw new \InvalidArgumentException('The read marker must belong to this conversation.');
    $this->database->merge('famtastic_portal_read')
      ->insertFields(['last_message_id' => $lastMessageId, 'changed' => $this->time->getRequestTime()])
      ->keys(['thread_id' => (int) $thread['id'], 'uid' => $actor['uid']])
      ->expression('last_message_id', 'CASE WHEN last_message_id < :last_compare THEN :last_value ELSE last_message_id END', [':last_compare' => $lastMessageId, ':last_value' => $lastMessageId])
      ->updateFields(['changed' => $this->time->getRequestTime()])->execute();
  }

  /** Persists a reply and its notification atomically; retries cannot resend it. */
  public function reply(AccountInterface $account, string $publicId, string $body, string $clientMessageId): array {
    $actor = $this->actor($account);
    $thread = $this->authorizedThread($actor, $publicId);
    $body = trim(strip_tags($body));
    if ($body === '' || mb_strlen($body) > 20000) throw new \InvalidArgumentException('Enter a message of 1–20,000 characters.');
    if (!preg_match('/^[a-zA-Z0-9_-]{16,80}$/', $clientMessageId)) throw new \InvalidArgumentException('A valid message request ID is required.');
    $key = hash('sha256', $thread['id'] . ':' . $actor['uid'] . ':' . $clientMessageId);
    $lockKey = 'famtastic:reply:' . $key;
    if (!$this->lock->acquire($lockKey, 30)) throw new \RuntimeException('This message is being saved. Please retry.');
    try {
      $existing = $this->database->select('famtastic_portal_message', 'm')->fields('m')->condition('client_key', $key)->execute()->fetchAssoc();
      if ($existing) {
        if (!hash_equals((string) $existing['body'], $body)) throw new \InvalidArgumentException('This message request ID was already used for different text.');
        return ['ok' => TRUE, 'message_id' => (int) $existing['id'], 'delivery_status' => $this->delivery($existing)['delivery_status'], 'duplicate' => TRUE];
      }
      $contact = $this->contact($thread);
      if ($actor['is_staff'] && !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('This conversation has no valid recipient address on its source record.');
      $now = $this->time->getRequestTime();
      $transaction = $this->database->startTransaction();
      $messageId = (int) $this->database->insert('famtastic_portal_message')->fields([
        'thread_id' => (int) $thread['id'], 'author_uid' => $actor['uid'], 'author_type' => $actor['is_staff'] ? 'staff' : 'customer',
        'body' => $body, 'client_key' => $key, 'created' => $now,
      ])->execute();
      $notificationKey = 'client-message:' . $messageId . ($actor['is_staff'] ? ':customer' : ':owner');
      $base = rtrim((string) ($this->config->get('famtastic_pipeline.settings')->get('frontend_base_url') ?: 'https://famtasticdesigns.com'), '/');
      $recipient = $actor['is_staff'] ? $contact['email'] : (string) ($this->config->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'hello@famtasticdesigns.com');
      $destination = $actor['is_staff'] ? $base . '/portal?section=messages&thread=' . $publicId : $base . '/web/admin/famtastic/messages/' . $publicId;
      $emailBody = $actor['is_staff']
        ? "FAMtastic Concierge replied about {$thread['subject']}.\n\n{$body}\n\nOpen your workspace:\n{$destination}\n\nSign in with the email address that received this message to continue the conversation."
        : "{$contact['name']} added a message about {$thread['subject']}.\n\n{$body}\n\nOpen the conversation:\n{$destination}";
      $this->database->insert('famtastic_notification_outbox')->fields([
        'notification_key' => $notificationKey, 'category' => $actor['is_staff'] ? 'transactional' : 'operational',
        'recipient' => $recipient, 'subject' => mb_substr(($actor['is_staff'] ? 'FAMtastic Concierge — ' : 'New customer message — ') . $thread['subject'], 0, 512),
        'body' => $emailBody, 'template_id' => $actor['is_staff'] ? 'customer_message_reply' : 'standard', 'template_version' => 1,
        'status' => 'queued', 'available_at' => $now, 'created' => $now, 'changed' => $now,
      ])->execute();
      // A customer message is visible to staff immediately; its owner alert is
      // separate from customer-facing message delivery status.
      if ($actor['is_staff']) $this->database->update('famtastic_portal_message')->fields(['notification_key' => $notificationKey])->condition('id', $messageId)->execute();
      $this->database->update('famtastic_portal_thread')->fields(['status' => 'open', 'changed' => $now])->condition('id', (int) $thread['id'])->execute();
      $this->database->update('famtastic_support_case')->fields([
        'status' => $actor['is_staff'] ? 'waiting_on_customer' : 'waiting_on_famtastic', 'changed' => $now,
      ])->condition('thread_id', (int) $thread['id'])->execute();
      unset($transaction);
      return ['ok' => TRUE, 'message_id' => $messageId, 'delivery_status' => $actor['is_staff'] ? 'queued' : 'received', 'duplicate' => FALSE];
    }
    catch (\Throwable $error) {
      if (isset($transaction)) $transaction->rollBack();
      throw $error;
    }
    finally {
      $this->lock->release($lockKey);
    }
  }

  private function contact(array $thread): array {
    if (!empty($thread['contact_email'])) return ['name' => (string) $thread['contact_name'], 'email' => (string) $thread['contact_email']];
    $query = $this->database->select('famtastic_membership', 'm');
    $query->join('famtastic_customer', 'c', 'c.id = m.customer_id');
    $query->fields('c', ['display_name', 'email'])->condition('m.organization_id', (int) $thread['organization_id'])
      ->condition('m.status', 'active');
    if (!empty($thread['created_by'])) $query->condition('c.id', (int) $thread['created_by']);
    else $query->condition('m.role', 'owner');
    $customer = $query->orderBy('m.created')->range(0, 1)->execute()->fetchAssoc();
    return ['name' => (string) ($customer['display_name'] ?? ''), 'email' => (string) ($customer['email'] ?? '')];
  }

  private function summary(array $actor, array $thread): array {
    $messages = $this->messages((int) $thread['id'], $actor['is_staff']);
    $last = $messages ? $messages[array_key_last($messages)] : NULL;
    $read = (int) $this->database->select('famtastic_portal_read', 'r')->fields('r', ['last_message_id'])
      ->condition('thread_id', (int) $thread['id'])->condition('uid', $actor['uid'])->execute()->fetchField();
    $opposite = $actor['is_staff'] ? 'customer' : 'staff';
    $unread = count(array_filter($messages, static fn(array $message): bool => $message['id'] > $read && $message['author_type'] === $opposite));
    $contact = $this->contact($thread);
    $case = $this->database->select('famtastic_support_case', 's')->fields('s', ['case_number'])
      ->condition('thread_id', (int) $thread['id'])->execute()->fetchField();
    return [
      'public_id' => (string) $thread['public_id'], 'subject' => (string) $thread['subject'],
      'kind' => (string) $thread['kind'], 'status' => (string) $thread['status'],
      'source' => !empty($thread['source_intake_id']) ? 'contact_form' : 'portal',
      'customer_name' => $contact['name'], 'customer_email' => $contact['email'], 'case_number' => $case ?: NULL,
      'unread_count' => $unread, 'needs_reply' => $thread['status'] !== 'closed' && ($last['author_type'] ?? '') === $opposite,
      'last_message_preview' => mb_substr((string) ($last['body'] ?? ''), 0, 220),
      'last_author_type' => $last['author_type'] ?? NULL, 'last_message_at' => (int) ($last['created'] ?? $thread['changed']),
      'last_message_id' => (int) ($last['id'] ?? 0), 'delivery_status' => $last['delivery_status'] ?? 'portal_only',
      'context' => $this->context($actor, $thread),
    ];
  }

  private function context(array $actor, array $thread): array {
    $query = $this->database->select('famtastic_project_request', 'r')->fields('r', ['id', 'public_id', 'project_name', 'proof_review_status', 'commerce_order_id']);
    $match = $query->orConditionGroup();
    $hasMatch = FALSE;
    if (!empty($thread['source_intake_id'])) { $match->condition('intake_id', (int) $thread['source_intake_id']); $hasMatch = TRUE; }
    if (!empty($thread['prospect_id'])) { $match->condition('prospect_id', (int) $thread['prospect_id']); $hasMatch = TRUE; }
    if (!empty($thread['project_id'])) { $match->condition('project_id', (int) $thread['project_id']); $hasMatch = TRUE; }
    if (!$hasMatch) return [];
    $query->condition($match)->condition('status', 'archived', '<>')->isNull('customer_archived_at');
    if (!$actor['is_staff']) $query->condition('organization_id', (int) $thread['organization_id']);
    $request = $query->orderBy('changed', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    $context = ['intake_id' => $thread['source_intake_id'] ? (int) $thread['source_intake_id'] : NULL];
    if ($actor['is_staff'] && !empty($thread['source_intake_id'])) $context['admin_intake_url'] = '/web/admin/famtastic/intake/' . (int) $thread['source_intake_id'] . '/edit';
    if ($request) {
      $context += ['request_public_id' => $request['public_id'], 'request_id' => (int) $request['id'], 'project_name' => $request['project_name'], 'proof_status' => $request['proof_review_status'], 'order_id' => $request['commerce_order_id'] ? (int) $request['commerce_order_id'] : NULL];
      if ($actor['is_staff']) $context['admin_request_url'] = '/web/admin/famtastic/website-request/' . (int) $request['id'] . '/proof-review';
    }
    return $context;
  }

  private function messages(int $threadId, bool $staff): array {
    $rows = $this->database->select('famtastic_portal_message', 'm')->fields('m')
      ->condition('thread_id', $threadId)->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return array_map(function (array $message) use ($staff): array {
      $delivery = $this->delivery($message);
      if (!$staff) unset($delivery['provider_message_id']);
      return ['id' => (int) $message['id'], 'author_type' => $message['author_type'], 'body' => $message['body'], 'created' => (int) $message['created']] + $delivery;
    }, $rows);
  }

  private function delivery(array $message): array {
    if ($message['author_type'] === 'customer') return ['delivery_status' => 'received'];
    $key = (string) ($message['notification_key'] ?? '');
    $outbox = $key === '' ? FALSE : $this->database->select('famtastic_notification_outbox', 'n')->fields('n', ['status', 'provider_message_id'])
      ->condition('notification_key', $key)->execute()->fetchAssoc();
    // Legacy staff replies already have immutable outbox keys, even though
    // the message table predates the explicit notification reference.
    if (!$outbox) {
      $case = $this->database->select('famtastic_support_case', 's')->fields('s', ['id'])
        ->condition('thread_id', (int) $message['thread_id'])->execute()->fetchField();
      if ($case) $outbox = $this->database->select('famtastic_notification_outbox', 'n')->fields('n', ['status', 'provider_message_id'])
        ->condition('notification_key', 'support:' . $case . ':reply:' . $message['id'])->execute()->fetchAssoc();
    }
    if (!$outbox) return ['delivery_status' => 'portal_only'];
    $status = match ((string) $outbox['status']) {
      'sent' => 'sent', 'dead_letter', 'failed' => 'failed', 'superseded', 'cancelled' => 'portal_only', default => 'queued',
    };
    return ['delivery_status' => $status, 'provider_message_id' => (string) ($outbox['provider_message_id'] ?? '')];
  }

}
