<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/process.php';
require __DIR__ . '/scenarios.php';

// Run ONLY inside the existing main-reviewed protected-data/network/disk wrapper.
// This process never calls Docker, owns a PDO, boots Drupal, or reads site settings.
ProofGuard::$deadline = hrtime(TRUE) / 1e9 + 240;
ini_set('memory_limit', '64M');
$results = []; $pair = NULL; $exit = 2; $runtime = NULL;
try {
  proofNeed($argc === 3 && in_array($argv[2], ['current', 'baseline'], TRUE), 'usage_run_connection_file_current_or_baseline');
  $config = proofConfig($argv[1]); $mode = $argv[2];
  $lineage = json_decode(file_get_contents(__DIR__ . '/lineage.json'), TRUE, flags: JSON_THROW_ON_ERROR);
  $cases = [
    'root-commit-35s' => [fn($p) => rootOwnershipProof($p, FALSE, $mode === 'baseline'), TRUE, 'ownership_escaped'],
    'stale-active-snapshot' => ['staleActiveProof', TRUE, 'stale_active_admitted'],
    'stale-budget-snapshot' => ['staleBudgetProof', TRUE, 'budget_over_stop'],
    'stale-recovery-snapshot' => ['renewalRecoveryProof', TRUE, 'renewal_overwritten'],
  ];
  if ($mode === 'current') $cases += [
    'root-rollback-35s' => [fn($p) => rootOwnershipProof($p, TRUE), TRUE, NULL],
    'duplicate-enqueue-commit' => [fn($p) => duplicateEnqueueProof($p, FALSE), FALSE, NULL],
    'duplicate-enqueue-rollback' => [fn($p) => duplicateEnqueueProof($p, TRUE), FALSE, NULL],
    'recovery-before-renew' => ['recoveryThenRenewProof', TRUE, NULL],
    'cas-claim-helper' => [fn($p) => casProof($p, 'famtastic_worker_claim'), TRUE, NULL],
    'cas-job-helper' => [fn($p) => casProof($p, 'famtastic_job'), TRUE, NULL],
    'static-profile-bounds' => [fn($p) => boundsProof($p, 'static'), TRUE, NULL],
    'proof-profile-bounds' => [fn($p) => boundsProof($p, 'proof'), TRUE, NULL],
    'prior-month-unknown-hold' => ['priorMonthProof', TRUE, NULL],
    'legacy-writer-exclusion' => ['legacyProof', TRUE, NULL],
    'real-semaphore-root-35s' => [fn($p) => rootOwnershipProof($p, FALSE), TRUE, NULL],
    'real-semaphore-renew' => [fn($p) => boundsProof($p, 'static'), TRUE, NULL],
  ];
  foreach ($cases as $name => [$test, $seed, $expected]) {
    ProofGuard::tick(); $start = hrtime(TRUE); $hint = str_starts_with($name, 'real-semaphore-') ? 'database' : 'ineffective';
    echo json_encode(['phase' => 'case_started', 'case' => $name, 'mode' => $mode, 'hint' => $hint]) . "\n"; flush();
    try {
      $pair = new ProofPair($argv[1], $mode, $hint, $seed);
      $runtime = ['php' => $pair->a->hello['php'], 'server' => $pair->a->hello['server_version'], 'isolation' => 'REPEATABLE-READ'];
      $test($pair);
      proofNeed($mode !== 'baseline', 'negative_control_did_not_fail:' . $name);
      $results[] = ['case' => $name, 'status' => 'pass', 'hint' => $hint, 'connections' => [$pair->a->hello['connection_id'], $pair->b->hello['connection_id']], 'seconds' => (hrtime(TRUE) - $start) / 1e9];
    } catch (ProofAssertion $e) {
      if ($mode !== 'baseline' || $e->getMessage() !== $expected) throw $e;
      $results[] = ['case' => $name, 'status' => 'expected_assertion_failure', 'tag' => $expected, 'hint' => $hint, 'seconds' => (hrtime(TRUE) - $start) / 1e9];
    } finally {
      if ($pair !== NULL) { $pair->close(); $pair = NULL; }
    }
  }
  $exit = $mode === 'current' ? 0 : 1; // Negative controls retain a real red receipt.
  echo json_encode(['status' => $mode === 'current' ? 'pass' : 'expected_negative_failures', 'source_commit' => $lineage['source_commit'],
    'baseline_commit' => $lineage['baseline_commit'], 'image' => $lineage['image'], 'run' => $config['run'], 'cases' => $results,
    'checks' => ProofGuard::$checks, 'runtime' => $runtime, 'limits' => 'Real MariaDB fixtures only; not installed Drupal, provider, cloud, importer or delivery proof.'], JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $e) {
  $exit = $e instanceof ProofAssertion ? 1 : 2;
  echo json_encode(['status' => 'failed_not_negative_proof', 'error_class' => $e::class, 'code' => $e->getMessage(), 'completed_cases' => $results]) . "\n";
} finally { if ($pair !== NULL) $pair->close(); }
exit($exit);
