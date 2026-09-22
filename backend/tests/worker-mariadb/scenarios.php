<?php
declare(strict_types=1);

function checkProof(bool $ok, string $tag): void { ProofGuard::check($ok, $tag); }
function rejectedProof(array $reply, string $message): void {
  proofNeed($reply['ok'] === FALSE && str_contains($reply['error']['message'] ?? '', $message), 'wrong_rejection_or_unexpected_success:' . $message);
}
function claimRowProof(array $state, int $id): array {
  foreach ($state['famtastic_worker_claim'] as $row) if ((int) $row['job_id'] === $id) return $row;
  throw new RuntimeException('claim_fixture_missing');
}
function waitMutexProof(ProofPair $p, float $seconds, bool $negative = FALSE): void {
  $start = hrtime(TRUE) / 1e9; $observed = FALSE;
  do {
    if (($early = $p->b->poll()) !== NULL) {
      $value = RemotePeer::value($early);
      if ($negative && is_array($value) && ($value['job_id'] ?? NULL) === 2) {
        $p->b->call('commit'); $p->a->call('commit');
        $state = $p->a->call('stats');
        proofNeed(count(array_filter($state['famtastic_worker_claim'], fn($r) => $r['state'] === 'leased')) === 2, 'negative_ownership_not_reproduced');
        throw new ProofAssertion('ownership_escaped');
      }
      throw new ProofAssertion('contender_completed_before_root_release');
    }
    if ($p->a->call('waits', ['peer' => $p->b->hello['connection_id']])) $observed = TRUE;
    proofNeed($observed || hrtime(TRUE) / 1e9 - $start < 5, 'no_actual_mutex_wait_observed');
    usleep(100000); ProofGuard::tick();
  } while (!$observed || hrtime(TRUE) / 1e9 - $start < $seconds);
  checkProof($observed, 'mutex_wait_not_proven');
}
function rootOwnershipProof(ProofPair $p, bool $rollback, bool $negative = FALSE): void {
  $p->clock(ProofPair::NOW, TRUE);
  $p->b->call('begin'); $p->b->call('snapshot'); // Snapshot predates A's lease.
  $p->a->call('begin'); $claim = $p->a->call('claim');
  checkProof($claim['root_still_open'], 'inner_committed_outer');
  $p->b->send('claim', ['cap' => 'proof']);
  waitMutexProof($p, 35, $negative);
  $p->a->call($rollback ? 'rollback' : 'commit');
  $next = RemotePeer::value($p->b->receive());
  checkProof($rollback ? ($next['job_id'] ?? NULL) === 2 && $next['attempt'] === 1 : $next === NULL, 'wrong_post_root_claim');
  $p->b->call('commit'); $state = $p->a->call('stats');
  checkProof($state['mutex_rows'] === 1 && (int) $state['total_holds'] === 100 && count($state['famtastic_worker_budget']) === 1, 'root_hold_or_singleton_mismatch');
  if ($rollback) checkProof($next['lease_until'] >= ProofPair::NOW + 35 + 180, 'lease_aged_while_waiting');
}
function staleActiveProof(ProofPair $p): void {
  $p->b->call('begin'); $p->b->call('snapshot');
  $p->a->call('claim', ['cap' => 'proof']);
  $claim = $p->b->call('claim'); $p->b->call('commit');
  checkProof($claim === NULL, 'stale_active_admitted');
  checkProof((int) $p->a->call('stats')['total_holds'] === 100, 'extra_active_hold');
}
function staleBudgetProof(ProofPair $p): void {
  $p->a->call('budget', ['key' => 'prior-unknown', 'cents' => 1900]);
  $p->b->call('begin'); $p->b->call('snapshot');
  $p->a->call('claim'); $p->a->call('finish', ['job' => 1]);
  $reply = $p->b->raw('claim', ['cap' => 'proof']);
  $p->b->call('commit'); $state = $p->a->call('stats');
  if ($reply['ok']) {
    proofNeed(($reply['value']['job_id'] ?? NULL) === 2 && (int) $state['total_holds'] === 2100, 'unexpected_budget_control_result');
    throw new ProofAssertion('budget_over_stop');
  }
  rejectedProof($reply, 'worker_budget_exhausted');
  checkProof((int) $state['total_holds'] === 2000 && count($state['famtastic_worker_budget']) === 2, 'hold_lost_or_budget_exceeded');
  checkProof($state['health']['authorized_monthly_cents'] === 2500 && $state['health']['stop_cents'] === 2000, 'budget_policy_changed');
}
function renewalRecoveryProof(ProofPair $p): void {
  $p->a->call('claim');
  $p->b->call('begin'); $p->b->call('snapshot');
  $p->a->call('clock', ['now' => ProofPair::NOW + 80]);
  $p->a->call('renew', ['job' => 1]);
  $p->b->call('clock', ['now' => ProofPair::NOW + 100]);
  checkProof($p->b->call('claim', ['cap' => 'proof']) === NULL, 'recovery_replaced_live_attempt');
  $p->b->call('commit'); $row = claimRowProof($p->a->call('stats'), 1);
  checkProof($row['state'] === 'leased' && (int) $row['lease_until'] === ProofPair::NOW + 170 && $row['token_hash'] !== '', 'renewal_overwritten');
}
function recoveryThenRenewProof(ProofPair $p): void {
  $p->a->call('claim'); $p->clock(ProofPair::NOW + 91);
  checkProof($p->b->call('claim', ['cap' => 'proof']) === NULL, 'replacement_fence_lost');
  $before = $p->b->call('stats'); rejectedProof($p->a->raw('renew', ['job' => 1]), 'Lease lost');
  checkProof($before === $p->b->call('stats'), 'stale_renew_mutated_rows');
}
function duplicateEnqueueProof(ProofPair $p, bool $rollback): void {
  $p->b->call('begin'); $p->b->call('snapshot'); $p->a->call('begin');
  $first = $p->a->call('enqueue'); $p->b->send('enqueue');
  waitMutexProof($p, 0.25); $p->a->call($rollback ? 'rollback' : 'commit');
  $second = RemotePeer::value($p->b->receive()); $p->b->call('commit');
  $state = $p->a->call('stats');
  checkProof($rollback || $first === $second, 'duplicate_allocated_another_job');
  checkProof(count($state['jobs']) === 1 && count($state['famtastic_worker_claim']) === 1 && !$state['famtastic_worker_budget'], 'duplicate_enrollment_or_early_hold');
}
function casProof(ProofPair $p, string $table): void {
  $p->a->call('claim'); $p->a->call('capture', ['table' => $table]); $p->b->call('tamper', ['table' => $table]);
  $before = $p->b->call('stats'); rejectedProof($p->a->raw('cas', ['table' => $table]), 'changed; transaction rolled back');
  checkProof($before === $p->b->call('stats'), 'cas_partial_write_persisted');
}
function boundsProof(ProofPair $p, string $cap): void {
  $id = $cap === 'proof' ? 2 : 1; $fence = $cap === 'proof' ? 1830 : 330; $lease = $cap === 'proof' ? 180 : 90;
  $now = ProofPair::NOW;
  for ($attempt = 1; $attempt <= 3; $attempt++) {
    $p->clock($now); $claim = $p->a->call('claim', ['cap' => $cap]);
    checkProof($claim['attempt'] === $attempt && $claim['lease_until'] === $now + $lease
      && $claim['execution_deadline'] === $now + $fence - 30 && $claim['heartbeat_seconds'] === 30, 'profile_or_generation_wrong');
    // Matched-row, same-second renew must succeed even if no value changes.
    $renew = $p->a->call('renew', ['job' => $id]); checkProof($renew['lease_until'] === $claim['lease_until'], 'same_second_renew_failed');
    if ($cap === 'proof') rejectedProof($p->a->raw('finish', ['job' => $id]), 'authoritative importer');
    if ($attempt === 1) {
      // Advance only the injected clock; each renewal is before its live lease.
      $end = $claim['execution_deadline'];
      for ($at = $now + $lease - 1; $at < $end; $at += $lease - 1) {
        $p->a->call('clock', ['now' => $at]);
        $renew = $p->a->call('renew', ['job' => $id]);
        checkProof($renew['lease_until'] === min($at + $lease, $end), 'renew_exceeded_execution_bound');
      }
      $p->a->call('clock', ['now' => $end - 1]);
      checkProof($p->a->call('renew', ['job' => $id])['lease_until'] === $end, 'final_renew_not_clamped');
    }
    $p->a->call('fail', ['job' => $id]);
    $p->clock($now + $fence - 1); checkProof($p->b->call('claim', ['cap' => $cap]) === NULL, 'replacement_before_deadline');
    $now += $fence + 1;
  }
  $p->clock($now); checkProof($p->b->call('claim', ['cap' => $cap]) === NULL, 'fourth_attempt_allowed');
  $state = $p->a->call('stats'); checkProof(count($state['famtastic_worker_budget']) === 3 && (int) $state['total_holds'] === 300, 'unknown_holds_refunded');
  checkProof(claimRowProof($state, $id)['state'] === 'exception', 'exhaustion_not_preserved');
  rejectedProof($p->a->raw('renew', ['job' => $id, 'index' => 0]), $cap === 'proof' ? 'generation mismatch' : 'Lease lost');
}
function priorMonthProof(ProofPair $p): void {
  $p->a->call('budget', ['key' => 'older-unknown', 'month' => '2026-08', 'cents' => 2500]);
  $p->a->call('claim'); $state = $p->a->call('stats');
  checkProof((int) $state['total_holds'] === 2600 && $state['health']['reserved_cents'] === 100, 'prior_month_hold_rewritten');
}
function legacyProof(ProofPair $p): void {
  $p->a->call('claim'); $before = $p->a->call('stats');
  foreach (['complete', 'fail', 'requeue'] as $action) rejectedProof($p->b->raw('legacy', ['action' => $action]), 'Coordinator-owned');
  checkProof($p->b->call('legacy', ['action' => 'claim']) === NULL, 'legacy_claim_adopted_managed');
  checkProof($before === $p->a->call('stats'), 'legacy_mutated_managed_state');
}
