<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../worker-mariadb/process.php';
require __DIR__ . '/scenarios.php';

// Explicit CLI only, inside main's reviewed protected-data/network wrapper.
// No Docker, site bootstrap, third PDO, shell transport or automatic cleanup.
ProofGuard::$deadline = hrtime(TRUE) / 1e9 + 180;
ini_set('memory_limit', '64M'); umask(0077);
$pair = NULL; $results = []; $exit = 2; $runtime = NULL;
try {
  proofNeed(PHP_SAPI === 'cli' && $argc === 3 && in_array($argv[2], ['current', 'baseline'], TRUE), 'usage_review_connection_file_current_or_baseline');
  ProofGuard::tick(); $config = proofConfig($argv[1]); $lineage = reviewLineage(); $mode = $argv[2]; $run = bin2hex(random_bytes(12));
  $cases = [
    'upload-retry' => [fn($p) => reviewAssetRace($p, FALSE), FALSE, 'upload_retry_lock_inversion'],
    'withdraw-retry' => [fn($p) => reviewAssetRace($p, TRUE), FALSE, 'withdraw_retry_lock_inversion'],
    'stale-review-event' => ['reviewStaleEvent', TRUE, 'stale_review_replay_rejected'],
    'stale-proof-job' => ['reviewStaleJob', TRUE, 'stale_job_bypassed'],
  ];
  if ($mode === 'current') $cases = [
    'same-key-creation' => [fn($p) => reviewCreationRace($p, FALSE), FALSE, NULL],
    'different-key-creation' => [fn($p) => reviewCreationRace($p, TRUE), FALSE, NULL],
    'nested-caller' => ['reviewNestedCaller', FALSE, NULL],
  ] + $cases;
  foreach ($cases as $name => [$test, $request, $expected]) {
    ProofGuard::tick(); $started = hrtime(TRUE);
    echo json_encode(['phase' => 'case_started', 'case' => $name, 'mode' => $mode]) . "\n"; flush();
    try {
      $pair = new ReviewPair($argv[1], $mode, $run . '.' . $name, $request);
      $connections = [$pair->a->hello['connection_id'], $pair->b->hello['connection_id']];
      $runtime = ['php' => $pair->a->hello['php'], 'server' => $pair->a->hello['server_version'], 'isolation' => 'REPEATABLE-READ'];
      proofNeed($pair->a->hello['php'] === $pair->b->hello['php'] && $pair->a->hello['server_version'] === $pair->b->hello['server_version']
        && $pair->a->hello['source_mode'] === $mode && $pair->b->hello['source_mode'] === $mode, 'peer_runtime_mismatch');
      $test($pair); proofNeed($mode === 'current', 'negative_control_did_not_fail:' . $name);
      $status = ['status' => 'pass'];
    } catch (ProofAssertion $e) {
      if ($mode !== 'baseline' || $e->getMessage() !== $expected) throw $e;
      $status = ['status' => 'expected_assertion_failure', 'tag' => $expected];
    } finally { if ($pair !== NULL) { $pair->close(); $pair = NULL; } }
    $results[] = ['case' => $name, 'connections' => $connections, 'seconds' => (hrtime(TRUE) - $started) / 1e9] + $status;
  }
  $exit = $mode === 'current' ? 0 : 1;
  echo json_encode(['status' => $mode === 'current' ? 'pass' : 'expected_negative_failures', 'source_commit' => $lineage['source_commit'],
    'baseline_commit' => $lineage['baseline_commit'], 'owner_source_commit' => $config['source_commit'], 'image' => $config['image'],
    'owner_run' => $config['run'], 'fixture_run' => $run, 'runtime' => $runtime, 'cases' => $results, 'checks' => ProofGuard::$checks,
    'limits' => 'Synthetic real MariaDB contention; metadata interfaces doubled. Not installed Drupal storage, provider, creative, activation or delivery evidence.'], JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
  $exit = 2;
  // Even unexpected assertion failures are NOT accepted baseline evidence.
  $safe = ['peer_bootstrap_failed', 'peer_stderr_not_empty', 'peer_exited_without_response', 'peer_response_timeout',
    'unexpected_operation_failure:unrecognized_operation_failure', 'operation_finished_before_lock_checkpoint', 'missing_real_lock_checkpoint'];
  $code = $e instanceof ProofAssertion || in_array($e->getMessage(), $safe, TRUE) ? $e->getMessage() : 'setup_protocol_or_operation_failed';
  echo json_encode(['status' => 'failed_not_negative_proof', 'class' => $e::class, 'code' => $code,
    'source' => basename($e->getFile()) . ':' . $e->getLine(), 'completed_cases' => $results]) . "\n";
} finally { if ($pair !== NULL) $pair->close(); }
exit($exit);
