<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileSystemInterface;

/**
 * Runs notifications, mailbox ingestion, support SLAs, and worker protection.
 */
final class LifecycleOperationsService {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly OutreachMailer $mailer,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  public function dispatchNotifications(int $limit = 25): array {
    $now = $this->time->getRequestTime();
    $rows = $this->database->select('famtastic_notification_outbox', 'n')->fields('n')
      ->condition('status', ['queued', 'retry'], 'IN')->condition('available_at', $now, '<=')
      ->orderBy('created')->range(0, max(1, min(100, $limit)))->execute()->fetchAll(\PDO::FETCH_ASSOC);
    $result = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'retried' => 0];
    foreach ($rows as $row) {
      $result['processed']++;
      try {
        $messageId = $this->mailer->send($row['recipient'], $row['subject'], $row['body']);
        $this->database->update('famtastic_notification_outbox')->fields([
          'status' => 'sent', 'attempts' => (int) $row['attempts'] + 1, 'sent_at' => $now,
          'provider_message_id' => $messageId, 'last_error' => NULL, 'changed' => $now,
        ])->condition('id', $row['id'])->execute();
        $result['sent']++;
      }
      catch (\Throwable $error) {
        $attempts = (int) $row['attempts'] + 1;
        $retry = $attempts < (int) $row['max_attempts'];
        $this->database->update('famtastic_notification_outbox')->fields([
          'status' => $retry ? 'retry' : 'dead_letter', 'attempts' => $attempts,
          'available_at' => $now + min(86400, 300 * (2 ** min(8, $attempts))),
          'last_error' => mb_substr($error->getMessage(), 0, 2000), 'changed' => $now,
        ])->condition('id', $row['id'])->execute();
        $result[$retry ? 'retried' : 'failed']++;
      }
    }
    $this->heartbeat('notification_dispatch', $result, $now + 300);
    return $result;
  }

  /**
   * Imports one validated mailbox envelope into the correct portal thread.
   */
  public function ingestInbound(array $message): array {
    $messageId = trim((string) ($message['message_id'] ?? ''));
    $sender = mb_strtolower(trim((string) ($message['from'] ?? '')));
    $recipient = mb_strtolower(trim((string) ($message['to'] ?? '')));
    $subject = mb_substr(trim(strip_tags((string) ($message['subject'] ?? ''))), 0, 512);
    $body = trim(strip_tags((string) ($message['body'] ?? '')));
    if ($messageId === '' || !filter_var($sender, FILTER_VALIDATE_EMAIL) || strlen($body) < 1 || strlen($body) > 262144) {
      throw new \InvalidArgumentException('inbound_message_invalid');
    }
    $hash = hash('sha256', $messageId);
    $existing = $this->database->select('famtastic_inbound_message', 'i')->fields('i')->condition('message_id_hash', $hash)->execute()->fetchAssoc();
    if ($existing) return ['accepted' => $existing['status'] === 'matched', 'duplicate' => TRUE, 'status' => $existing['status']];
    preg_match('/support\+([0-9a-f-]{36})@/i', $recipient, $match);
    $publicId = strtolower((string) ($message['thread_public_id'] ?? ($match[1] ?? '')));
    $attachments = $this->validateAttachments((array) ($message['attachments'] ?? []), $publicId ?: 'unmatched');
    $thread = $publicId === '' ? FALSE : $this->database->select('famtastic_portal_thread', 't')->fields('t')->condition('public_id', $publicId)->execute()->fetchAssoc();
    $authorized = FALSE;
    if ($thread) {
      $query = $this->database->select('famtastic_membership', 'm');
      $query->join('famtastic_customer', 'c', 'c.id = m.customer_id');
      $authorized = (bool) $query->condition('m.organization_id', $thread['organization_id'])->condition('m.status', 'active')
        ->condition('c.email', $sender)->countQuery()->execute()->fetchField();
    }
    $status = $thread && $authorized ? 'matched' : 'unmatched';
    $reason = !$thread ? 'thread_not_found' : ($authorized ? '' : 'sender_not_authorized');
    $now = $this->time->getRequestTime();
    $this->database->insert('famtastic_inbound_message')->fields([
      'message_id_hash' => $hash, 'thread_public_id' => $publicId, 'sender_hash' => hash('sha256', $sender),
      'subject' => $subject, 'body' => $body, 'attachment_manifest' => json_encode($attachments, JSON_THROW_ON_ERROR),
      'status' => $status, 'rejection_reason' => $reason, 'received_at' => (int) ($message['received_at'] ?? $now), 'created' => $now,
    ])->execute();
    if ($status === 'matched') {
      $this->database->insert('famtastic_portal_message')->fields([
        'thread_id' => $thread['id'], 'author_type' => 'customer', 'body' => $body, 'created' => $now,
      ])->execute();
      $this->database->update('famtastic_portal_thread')->fields(['status' => 'open', 'changed' => $now])->condition('id', $thread['id'])->execute();
      $this->database->update('famtastic_support_case')->fields(['status' => 'waiting_on_famtastic', 'changed' => $now])
        ->condition('thread_id', $thread['id'])->execute();
    }
    else {
      $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fritz.medine@gmail.com');
      $this->queue('inbound:' . $hash . ':unmatched', $admin, 'Unmatched customer email requires review', "Subject: {$subject}\nReason: {$reason}\nMessage-ID hash: {$hash}");
    }
    return ['accepted' => $status === 'matched', 'duplicate' => FALSE, 'status' => $status, 'reason' => $reason];
  }

  public function staffReply(string $caseNumber, string $body, int $uid = 1): array {
    $case = $this->database->select('famtastic_support_case', 's')->fields('s')->condition('case_number', $caseNumber)->execute()->fetchAssoc();
    if (!$case) throw new \RuntimeException('support_case_not_found');
    $thread = $this->database->select('famtastic_portal_thread', 't')->fields('t')->condition('id', $case['thread_id'])->execute()->fetchAssoc();
    $customerQuery = $this->database->select('famtastic_membership', 'm');
    $customerQuery->join('famtastic_customer', 'c', 'c.id = m.customer_id');
    $customer = $customerQuery->fields('c')->condition('m.organization_id', $thread['organization_id'])->condition('m.role', 'owner')->execute()->fetchAssoc();
    $clean = trim(strip_tags($body));
    if ($clean === '') throw new \InvalidArgumentException('support_reply_required');
    $now = $this->time->getRequestTime();
    $messageId = (int) $this->database->insert('famtastic_portal_message')->fields([
      'thread_id' => $thread['id'], 'author_uid' => $uid, 'author_type' => 'staff', 'body' => $clean, 'created' => $now,
    ])->execute();
    $this->database->update('famtastic_support_case')->fields(['status' => 'waiting_on_customer', 'responded_at' => $now, 'changed' => $now])
      ->condition('id', $case['id'])->execute();
    $this->queue("support:{$case['id']}:reply:{$messageId}", $customer['email'], "Reply to support case {$caseNumber}", $clean . "\n\nReply to support+{$thread['public_id']}@famtasticdesigns.com");
    return ['case_number' => $caseNumber, 'status' => 'waiting_on_customer', 'message_id' => $messageId];
  }

  public function runProtection(): array {
    $now = $this->time->getRequestTime();
    $admin = (string) ($this->configFactory->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fritz.medine@gmail.com');
    $overdue = $this->database->select('famtastic_support_case', 's')->fields('s')
      ->condition('status', ['new', 'assigned', 'waiting_on_famtastic'], 'IN')->condition('response_due', $now, '<=')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($overdue as $case) {
      $this->queue("support:{$case['id']}:overdue:" . gmdate('Ymd', $now), $admin,
        "Overdue support case {$case['case_number']}", "Priority: {$case['priority']}\nStatus: {$case['status']}\nResponse deadline has passed.");
    }
    $processed = count($overdue);
    $followups = $this->database->select('famtastic_prospect', 'p')->fields('p', ['id', 'business_name', 'status', 'next_followup_due'])
      ->condition('status', ['contacted', 'qualified', 'proposal', 'nurture'], 'IN')->condition('next_followup_due', 0, '>')->condition('next_followup_due', $now, '<=')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($followups as $lead) {
      $this->queue("lead:{$lead['id']}:followup:" . gmdate('Ymd', $now), $admin, "Lead follow-up due — {$lead['business_name']}", "Stage: {$lead['status']}\nFollow-up deadline has passed.\nOpen: https://famtasticdesigns.com/web/admin/famtastic/prospect/{$lead['id']}/edit");
    }
    $processed += count($followups);

    $projects = $this->database->select('famtastic_project', 'p')->fields('p', ['id', 'label', 'delivery_status', 'changed'])
      ->condition('delivery_status', ['draft', 'request_generated', 'submitted', 'proof_delivered', 'revision'], 'IN')
      ->condition('changed', $now - 604800, '<=')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($projects as $project) {
      $this->queue("project:{$project['id']}:stale:" . gmdate('YW', $now), $admin, "Project next step overdue — {$project['label']}", "Status: {$project['delivery_status']}\nNo update has been recorded for at least seven days.");
    }
    $processed += count($projects);

    $renewalQuery = $this->database->select('famtastic_entitlement', 'e');
    $renewalQuery->join('famtastic_membership', 'm', 'm.organization_id = e.organization_id AND m.role = :role', [':role' => 'owner']);
    $renewalQuery->join('famtastic_customer', 'c', 'c.id = m.customer_id');
    $renewalQuery->fields('e', ['id', 'entitlement_type', 'renews_at', 'amount_minor', 'billing_interval'])->fields('c', ['email'])
      ->condition('e.status', 'active')->condition('e.renews_at', $now, '>')->condition('e.renews_at', $now + 2592000, '<=');
    $renewals = $renewalQuery->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($renewals as $renewal) {
      $this->queue("entitlement:{$renewal['id']}:renewal-30d", $renewal['email'], 'Upcoming FAMtastic service renewal',
        'Service: ' . $renewal['entitlement_type'] . "\nRenewal date: " . gmdate('Y-m-d', (int) $renewal['renews_at']) . "\nManage or cancel from your customer portal before renewal.");
    }
    $processed += count($renewals);

    $lateWorkers = $this->database->select('famtastic_worker_heartbeat', 'w')->fields('w')->condition('next_due', 0, '>')->condition('next_due', $now, '<')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($lateWorkers as $worker) {
      $this->queue("worker:{$worker['worker_key']}:late:" . gmdate('YmdH', $now), $admin, "Automation worker late — {$worker['worker_key']}", "Last finished: " . gmdate(DATE_ATOM, (int) $worker['last_finished']) . "\nExpected by: " . gmdate(DATE_ATOM, (int) $worker['next_due']));
    }
    $processed += count($lateWorkers);
    $deadLetters = (int) $this->database->select('famtastic_notification_outbox', 'n')->condition('status', 'dead_letter')->countQuery()->execute()->fetchField();
    if ($deadLetters > 0) $this->queue('exceptions:daily:' . gmdate('Ymd', $now), $admin, 'Daily FAMtastic delivery exception summary', "Notifications requiring manual review: {$deadLetters}\nOpen operations: https://famtasticdesigns.com/web/admin/famtastic");

    $result = ['processed' => $processed, 'failed' => 0, 'retried' => 0];
    $this->heartbeat('lifecycle_protection', $result, $now + 900);
    return $result;
  }

  private function validateAttachments(array $attachments, string $threadPublicId): array {
    $allowed = ['image/png', 'image/jpeg', 'image/webp', 'application/pdf', 'text/plain'];
    $safe = []; $total = 0;
    foreach ($attachments as $attachment) {
      $size = (int) ($attachment['size'] ?? 0); $mime = strtolower((string) ($attachment['mime'] ?? ''));
      if ($size < 0 || $size > 10485760 || !in_array($mime, $allowed, TRUE)) throw new \InvalidArgumentException('inbound_attachment_rejected');
      $total += $size;
      if ($total > 15728640) throw new \InvalidArgumentException('inbound_attachments_too_large');
      $name = mb_substr(basename((string) ($attachment['name'] ?? 'attachment')), 0, 255);
      $record = ['name' => $name, 'mime' => $mime, 'size' => $size, 'sha256' => (string) ($attachment['sha256'] ?? '')];
      if (isset($attachment['content_base64'])) {
        $bytes = base64_decode((string) $attachment['content_base64'], TRUE);
        if ($bytes === FALSE || strlen($bytes) !== $size || !hash_equals(hash('sha256', $bytes), $record['sha256'])) throw new \InvalidArgumentException('inbound_attachment_integrity_failed');
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($detected, $allowed, TRUE) || ($mime !== $detected && !($mime === 'image/jpeg' && $detected === 'image/pjpeg'))) throw new \InvalidArgumentException('inbound_attachment_mime_mismatch');
        $directory = 'private://famtastic-support/' . preg_replace('/[^0-9a-f-]/', '', $threadPublicId);
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $path = $directory . '/' . bin2hex(random_bytes(12)) . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        $saved = $this->fileSystem->saveData($bytes, $path, FileSystemInterface::EXISTS_ERROR);
        if ($saved === FALSE) throw new \RuntimeException('inbound_attachment_save_failed');
        $record['private_path'] = $saved;
      }
      $safe[] = $record;
    }
    return $safe;
  }

  private function queue(string $key, string $recipient, string $subject, string $body): void {
    $now = $this->time->getRequestTime();
    $this->database->merge('famtastic_notification_outbox')->key('notification_key', $key)->insertFields([
      'notification_key' => $key, 'category' => 'operational', 'recipient' => mb_strtolower($recipient), 'subject' => $subject, 'body' => $body,
      'status' => 'queued', 'attempts' => 0, 'max_attempts' => 5, 'available_at' => $now, 'created' => $now, 'changed' => $now,
    ])->execute();
  }

  private function heartbeat(string $key, array $result, int $nextDue): void {
    $now = $this->time->getRequestTime();
    $this->database->merge('famtastic_worker_heartbeat')->key('worker_key', $key)->fields([
      'worker_key' => $key, 'status' => ($result['failed'] ?? 0) > 0 ? 'degraded' : 'healthy', 'last_started' => $now,
      'last_finished' => $now, 'next_due' => $nextDue, 'processed' => (int) ($result['processed'] ?? 0),
      'failed' => (int) ($result['failed'] ?? 0), 'retried' => (int) ($result['retried'] ?? 0), 'changed' => $now,
    ])->execute();
  }
}
