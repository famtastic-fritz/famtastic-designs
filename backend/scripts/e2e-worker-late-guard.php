<?php

declare(strict_types=1);

/**
 * Regression harness for the worker-late guard (CEO-FULL-REVIEW-2026-08-24
 * gap #4): "late" must mean no sign of life within WORKER_LATE_GRACE_SECONDS,
 * not merely an expired next_due, which raced against sibling 5-minute cron
 * lines and produced 237 false-positive alerts.
 *
 * Run through scripts/e2e-worker-late-guard.sh (memory transport, evidence
 * JSON asserted by the wrapper). Leaves no residue: synthetic worker rows and
 * outbox keys are removed at the end of every run.
 */

$runId = (string) (getenv('FAMTASTIC_SYNTHETIC_RUN_ID') ?: time());
$now = \Drupal::time()->getRequestTime();
$db = \Drupal::database();
$operations = \Drupal::service('famtastic_pipeline.lifecycle_operations');

// Deterministic start: clear any prior synthetic state.
$db->delete('famtastic_worker_heartbeat')
  ->condition('worker_key', ['synthetic_stale_worker', 'synthetic_running_worker'], 'IN')->execute();
$db->delete('famtastic_notification_outbox')
  ->condition('notification_key', 'worker:synthetic_%', 'LIKE')->execute();

// Case A — genuinely dead: expected long ago, no completion for 2h.
seed_worker_row($db, $now, 'synthetic_stale_worker', $now - 7200, $now - 3600);
// Case B — the old false positive: next_due just passed while the worker is
// mid-run on the shared cadence (finished 2 minutes ago).
seed_worker_row($db, $now, 'synthetic_running_worker', $now - 120, $now - 60);

$operations->runProtection();

$outboxStatus = static function (string $key) use ($db): ?string {
  $status = $db->select('famtastic_notification_outbox', 'n')->fields('n', ['status'])
    ->condition('notification_key', $key)->execute()->fetchField();
  return $status === FALSE ? NULL : (string) $status;
};

$staleKey = 'worker:synthetic_stale_worker:late:' . gmdate('YmdH', $now);
$runningKey = 'worker:synthetic_running_worker:late:' . gmdate('YmdH', $now);
$staleQueued = $outboxStatus($staleKey);
$runningAbsent = $outboxStatus($runningKey) === NULL;

// Idempotency: a second protection sweep must not multiply alerts.
$operations->runProtection();
$stillSingle = (int) $db->select('famtastic_notification_outbox', 'n')
  ->condition('notification_key', 'worker:synthetic_stale_worker:late:%', 'LIKE')
  ->countQuery()->execute()->fetchField() === 1;

// Residue cleanup before assertions so failures never leave fixtures behind.
$db->delete('famtastic_worker_heartbeat')
  ->condition('worker_key', ['synthetic_stale_worker', 'synthetic_running_worker'], 'IN')->execute();
$db->delete('famtastic_notification_outbox')
  ->condition('notification_key', 'worker:synthetic_%', 'LIKE')->execute();

$checks = [
  'stale_worker_alert_queued' => $staleQueued === 'queued',
  'midrun_worker_not_alerted' => $runningAbsent,
  'second_sweep_idempotent' => $stillSingle,
];
if (in_array(FALSE, $checks, TRUE)) {
  throw new RuntimeException('Worker-late guard failed: ' . json_encode([
    'stale_status' => $staleQueued,
    'running_alert_present' => !$runningAbsent,
    'single_after_two_sweeps' => $stillSingle,
  ]));
}

$evidenceDir = (string) getenv('FAMTASTIC_LIFECYCLE_EVIDENCE_DIR');
if ($evidenceDir === '' || (!is_dir($evidenceDir) && !mkdir($evidenceDir, 0770, TRUE))) {
  throw new RuntimeException('Evidence directory unavailable');
}
$evidence = [
  'schema' => 'famtastic.worker-late-guard.v1', 'status' => 'passed', 'run_id' => $runId,
  'checks' => $checks,
  'grace_seconds' => 1800,
  'cases' => [
    'stale' => ['last_finished_offset_s' => -7200, 'next_due_offset_s' => -3600, 'alert_key' => $staleKey],
    'midrun' => ['last_finished_offset_s' => -120, 'next_due_offset_s' => -60, 'alert_key' => NULL],
  ],
  'generated_at' => gmdate(DATE_ATOM),
];
file_put_contents($evidenceDir . '/evidence.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "PASS: stale worker alerted once; mid-run worker on shared cadence not flagged; second sweep idempotent.\n";
echo 'Evidence: ' . $evidenceDir . "/evidence.json\n";

function seed_worker_row(Drupal\Core\Database\Connection $db, int $now, string $key, int $lastFinished, int $nextDue): void {
  $db->insert('famtastic_worker_heartbeat')->fields([
    'worker_key' => $key, 'status' => 'healthy',
    'last_started' => $lastFinished, 'last_finished' => $lastFinished,
    'next_due' => $nextDue, 'processed' => 0, 'failed' => 0, 'retried' => 0,
    'changed' => $now,
  ])->execute();
}
