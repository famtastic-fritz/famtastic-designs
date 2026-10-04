<?php

declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Lock\LockBackendInterface;

/** Drupal owns discovery cursor, retry state and health; cron is only a clock. */
final class InboundMailboxService {
  private const KEY = 'famtastic.inbound_mail';
  public function __construct(
    private readonly StateInterface $state,
    private readonly LockBackendInterface $lock,
    private readonly Connection $database,
    private readonly LifecycleOperationsService $operations,
    private readonly string $root,
  ) {}

  /** Explicit first activation snapshots existing files; never imports them. */
  public function activate(): array {
    if (!$this->lock->acquire(self::KEY, 180)) throw new \RuntimeException('mail_scan_locked');
    try {
      if ($this->state->get(self::KEY . '.activation')) return $this->health();
      $baseline = [];
      foreach ($this->files() as [$mailbox, $path]) $baseline[$this->identity($mailbox, $path)] = TRUE;
      $this->state->set(self::KEY . '.baseline', $baseline);
      $this->state->set(self::KEY . '.activation', ['at' => time(), 'excluded_files' => count($baseline)]);
      return $this->health();
    } finally { $this->lock->release(self::KEY); }
  }

  public function tick(int $limit = 25): array {
    if (!$this->state->get(self::KEY . '.activation')) throw new \RuntimeException('mail_activation_required');
    if (!$this->lock->acquire(self::KEY, 180)) throw new \RuntimeException('mail_scan_locked');
    $now = time();
    $report = ['started' => $now, 'finished' => NULL, 'processed' => 0, 'matched' => 0, 'unmatched' => 0, 'duplicates' => 0, 'failed' => 0, 'deferred' => 0, 'excluded' => 0];
    $this->state->set(self::KEY . '.heartbeat', $report);
    try {
      $baseline = $this->state->get(self::KEY . '.baseline', []);
      foreach ($this->files() as [$mailbox, $path]) {
        $id = $this->identity($mailbox, $path);
        if (isset($baseline[$id])) { $report['excluded']++; continue; }
        $receipt = $this->state->get(self::KEY . '.file.' . $id, []);
        if (($receipt['status'] ?? '') === 'captured') continue;
        if (($receipt['retry_at'] ?? 0) > $now) { $report['deferred']++; continue; }
        if ($report['processed'] >= max(1, min(50, $limit)) || time() - $now > 45) { $report['deferred']++; continue; }
        $report['processed']++;
        try {
          $size = filesize($path);
          if ($size === FALSE || $size > 16777216 || $size < 1) throw new \RuntimeException('mail_size_invalid');
          $raw = file_get_contents($path);
          if ($raw === FALSE) throw new \RuntimeException('mail_read_failed');
          $message = InboundEnvelope::parse($raw, (int) filemtime($path));
          // Trusted mailbox location is a recipient fact, unlike arbitrary
          // body content. The signed pipe still uses delivery/address headers.
          $message['recipients'][] = $mailbox . '@famtasticdesigns.com';
          $result = $this->operations->ingestInbound($message);
          $report[$result['status']]++;
          if ($result['duplicate']) $report['duplicates']++;
          $this->state->set(self::KEY . '.file.' . $id, ['status' => 'captured', 'at' => time(), 'message_hash' => hash('sha256', $message['message_id']), 'match' => $result['status']]);
          // Retain original bytes and paths in both Maildirs. Drupal receipts
          // prevent rescans; unmatched/invalid originals remain available.
        } catch (\Throwable) {
          $attempts = (int) ($receipt['attempts'] ?? 0) + 1;
          $this->state->set(self::KEY . '.file.' . $id, ['status' => 'retry', 'attempts' => $attempts,
            'retry_at' => time() + min(86400, 300 * (2 ** min(8, $attempts - 1)))]);
          $report['failed']++;
        }
      }
    } catch (\Throwable $error) {
      $report['failed']++;
      throw $error;
    } finally {
      $report['finished'] = time();
      $this->state->set(self::KEY . '.heartbeat', $report);
      $this->database->merge('famtastic_worker_heartbeat')->key('worker_key', 'inbound_mail')->fields([
        'worker_key' => 'inbound_mail', 'status' => $report['failed'] ? 'degraded' : 'healthy',
        'last_started' => $now, 'last_finished' => $report['finished'], 'next_due' => $now + 300,
        'processed' => $report['processed'], 'failed' => $report['failed'], 'retried' => 0, 'changed' => time(),
      ])->execute();
      $this->lock->release(self::KEY);
    }
    return $report;
  }

  public function health(): array {
    $activation = $this->state->get(self::KEY . '.activation');
    $heartbeat = $this->state->get(self::KEY . '.heartbeat', []);
    $counts = ['hello' => 0, 'support' => 0]; $pending = 0; $retry = 0; $captured = 0; $excluded = 0;
    $baseline = $this->state->get(self::KEY . '.baseline', []);
    foreach ($this->files() as [$mailbox, $path]) {
      $counts[$mailbox]++;
      $id = $this->identity($mailbox, $path);
      if (isset($baseline[$id])) { $excluded++; continue; }
      $status = $this->state->get(self::KEY . '.file.' . $id, [])['status'] ?? '';
      if ($status === 'captured') $captured++;
      elseif ($status === 'retry') $retry++;
      else $pending++;
    }
    $matched = (int) $this->database->select('famtastic_inbound_message', 'i')->condition('status', 'matched')->countQuery()->execute()->fetchField();
    $unmatched = (int) $this->database->select('famtastic_inbound_message', 'i')->condition('status', 'unmatched')->countQuery()->execute()->fetchField();
    $drafts = [];
    $query = $this->database->select('famtastic_support_draft', 'd')->fields('d', ['status']);
    $query->addExpression('COUNT(*)', 'count');
    foreach ($query->groupBy('status')->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) $drafts[$row['status']] = (int) $row['count'];
    $query = $this->database->select('famtastic_inbound_message', 'i');
    $query->leftJoin('famtastic_support_draft', 'd', 'd.message_id = i.id');
    $missingDrafts = (int) $query->isNull('d.id')->countQuery()->execute()->fetchField();
    return ['enabled' => (bool) $activation, 'activation' => $activation, 'clock_status' => !$heartbeat ? 'never_run' : (time() - ($heartbeat['finished'] ?: $heartbeat['started']) > 900 ? 'stale' : 'recent'),
      'mailbox_new_files' => $counts, 'excluded_baseline' => $excluded, 'pending_files' => $pending, 'retry_files' => $retry,
      'captured_files' => $captured, 'heartbeat' => $heartbeat, 'ingestion_totals' => ['matched' => $matched, 'unmatched' => $unmatched],
      'draft_totals_by_status' => $drafts, 'messages_missing_draft' => $missingDrafts,
      'approval' => 'Human decision required; this command never approves, dispatches or sends.'];
  }

  private function files(): array {
    $files = [];
    foreach (['support', 'hello'] as $mailbox) {
      $folder = $this->root . '/' . $mailbox;
      if (!is_dir($folder) || is_link($folder)) throw new \RuntimeException('mailbox_unavailable');
      $directories = new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS);
      $filter = new \RecursiveCallbackFilterIterator($directories, static fn(\SplFileInfo $file): bool =>
        !$file->isLink() && (!$file->isDir() || !in_array($file->getFilename(), ['cur', 'tmp'], TRUE)));
      $iterator = new \RecursiveIteratorIterator($filter);
      foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink() || basename(dirname($file->getPathname())) !== 'new') continue;
        $files[] = [$mailbox, $file->getPathname()];
        if (count($files) > 10000) throw new \RuntimeException('mailbox_discovery_limit');
      }
    }
    usort($files, static fn(array $a, array $b): int => strcmp($a[1], $b[1]));
    return $files;
  }
  private function identity(string $mailbox, string $path): string {
    // Path identity prevents old files being reclassified by date/header edits.
    return hash('sha256', $mailbox . ':' . substr($path, strlen($this->root)));
  }
}
