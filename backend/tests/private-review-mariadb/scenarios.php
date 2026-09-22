<?php
declare(strict_types=1);

final class ReviewPair {
  public RemotePeer $a;
  public RemotePeer $b;
  public ReviewFiles $files;
  public function __construct(string $config, string $mode, string $hint, bool $request = FALSE) {
    $this->files = new ReviewFiles($config, $hint, TRUE);
    $this->a = new RemotePeer($config, $mode, $hint, 'a', 'private-review');
    try {
      $this->b = new RemotePeer($config, $mode, $hint, 'b', 'private-review');
      ProofGuard::check($this->a->hello['connection_id'] !== $this->b->hello['connection_id'], 'review_connections_not_independent');
      $this->a->call('seed', ['request' => $request]);
    } catch (Throwable $e) { $this->close(); throw $e; }
  }
  public function arm(string $role, string $table, bool $pause): void {
    $other = $role === 'a' ? 'b' : 'a';
    $this->$role->call('arm', ['table' => $table, 'pause' => $pause, 'peer' => $this->$other->hello['connection_id']]);
  }
  public function waitFor(string $mark, array $busy, ?string $inversion = NULL): void {
    $deadline = hrtime(TRUE) / 1e9 + 8;
    while (!$this->files->has($mark)) {
      ProofGuard::tick();
      if ($inversion !== NULL) ProofGuard::check(!$this->files->has('b.member'), $inversion);
      foreach ($busy as $role) {
        $reply = $this->$role->poll();
        if ($reply !== NULL) {
          // Closed peer errors only, never SQL, environment, credentials or values.
          echo json_encode(['phase' => 'early_peer_result', 'role' => $role, 'ok' => $reply['ok'], 'error' => $reply['error'] ?? NULL]) . "\n";
          proofNeed(FALSE, 'operation_finished_before_lock_checkpoint');
        }
      }
      proofNeed(hrtime(TRUE) / 1e9 < $deadline, 'missing_real_lock_checkpoint'); usleep(20000);
    }
    if ($inversion !== NULL) ProofGuard::check(!$this->files->has('b.member'), $inversion);
    ProofGuard::check(TRUE, 'real_lock_checkpoint_observed');
  }
  public function close(): void { if (isset($this->a)) $this->a->stop(); if (isset($this->b)) $this->b->stop(); }
  public function __destruct() { $this->close(); }
}
function reviewRefused(array $reply, string $exact): bool {
  return $reply['ok'] === FALSE && ($reply['error']['class'] ?? '') === RuntimeException::class && ($reply['error']['message'] ?? '') === $exact;
}
function reviewQuiet(array $stats, int $jobs = 0): void {
  ProofGuard::check(!$stats['root_open'] && $stats['counts']['famtastic_job'] === $jobs && $stats['counts']['famtastic_notification_outbox'] === 0, 'unexpected_transaction_job_or_notification');
  foreach ($stats['requests'] as $row) ProofGuard::check($row['customer_id'] === 91 && $row['organization_id'] === 92 && $row['status'] === 'draft'
    && $row['proof_review_status'] === 'not_started' && $row['no_delivery'], 'request_authority_changed');
}
function reviewOneAttachment(ReviewPair $p, string $publicId): void {
  $stats = $p->a->call('stats'); reviewQuiet($stats);
  ProofGuard::check($stats['counts']['famtastic_project_request'] === 1 && $stats['counts']['famtastic_event'] === 2
    && $stats['events']['draft'] === 1 && $stats['events']['review'] === 1 && $stats['counts']['famtastic_portal_activity'] === 1
    && $stats['requests'][0]['customer_supplied'] === FALSE, 'duplicate_or_missing_draft_attachment');
  $read = $p->b->call('read', ['public_id' => $publicId]); $file = $p->files->manifest()['files'][0];
  ProofGuard::check($read['sha256'] === $file['sha256'] && $read['bytes'] === $file['bytes'] && $read['manifest_sha256'] === $stats['requests'][0]['digest'], 'private_copy_not_readable_or_exact');
}
function reviewCreationRace(ReviewPair $p, bool $different): void {
  $p->arm('a', 'member', TRUE); $p->a->send('create');
  $p->waitFor('a.member', ['a']);
  $p->b->send('create', ['key' => $different ? 'other-key' : 'same-key']);
  $p->waitFor('a.wait', ['a', 'b']); // Actual B waits on A's membership lock.
  $p->files->mark('a.release');
  $first = RemotePeer::value($p->a->receive()); $second = $p->b->receive();
  ProofGuard::check($first['newly_created'] && $first['newly_attached'] && !$first['root_open'], 'first_creator_not_committed');
  if ($different) {
    proofNeed(reviewRefused($second, 'A matching request exists; attach using its verified exact public UUID.'), 'different_key_not_exactly_rejected');
  } else {
    $again = RemotePeer::value($second);
    ProofGuard::check(!$again['newly_created'] && !$again['newly_attached'] && !$again['root_open']
      && $again['request_id'] === $first['request_id'] && $again['request_public_id'] === $first['request_public_id'], 'same_key_did_not_reuse');
  }
  reviewOneAttachment($p, $first['request_public_id']);
}
function reviewAssetRace(ReviewPair $p, bool $withdraw): void {
  $created = $p->a->call('create'); $id = $created['request_public_id'];
  if ($withdraw) {
    ProofGuard::check($p->a->call('upload', ['public_id' => $id])['status'] === 201, 'withdraw_setup_upload_failed');
    $asset = $p->a->call('stats')['assets'][0]['public_id'];
  }
  $p->arm('a', 'request', TRUE); $p->arm('b', 'member', FALSE);
  $p->a->send($withdraw ? 'withdraw' : 'upload', ['public_id' => $id, 'asset_id' => $asset ?? '']);
  $p->waitFor('a.request', ['a']);
  $p->b->send('create');
  // Frozen 7227 acquires membership while the actual asset method owns request.
  // Capture the inversion BEFORE causing a deadlock; SQL errors do not count.
  $p->waitFor('a.wait', ['a', 'b'], $withdraw ? 'withdraw_retry_lock_inversion' : 'upload_retry_lock_inversion');
  $p->files->mark('a.release');
  $assetResult = RemotePeer::value($p->a->receive()); $retry = RemotePeer::value($p->b->receive());
  ProofGuard::check(!$assetResult['root_open'] && !$retry['root_open'] && !$retry['newly_created'] && !$retry['newly_attached'], 'asset_retry_scope_failed');
  if (!$withdraw) ProofGuard::check($assetResult['status'] === 201, 'first_asset_not_inserted');
  $stats = $p->a->call('stats'); reviewQuiet($stats);
  ProofGuard::check(count($stats['assets']) === 1 && $stats['assets'][0]['status'] === ($withdraw ? 'withdrawn' : 'active')
    && $stats['events']['draft'] === 1 && $stats['events']['review'] === 1 && $stats['events']['withdraw'] === (int) $withdraw
    && $stats['counts']['famtastic_portal_activity'] === 1
    && $stats['counts']['fixture_file_metadata'] === 1 && $stats['counts']['fixture_file_usage'] === 1, 'asset_metadata_or_rights_mismatch');
  if ($withdraw) {
    $again = $p->b->call('upload', ['public_id' => $id]);
    ProofGuard::check($again['status'] === 409 && !$again['root_open'] && $p->a->call('stats')['assets'][0]['status'] === 'withdrawn', 'withdrawn_bytes_reactivated');
  }
}
function reviewStaleEvent(ReviewPair $p): void {
  $p->a->call('attach'); $p->b->call('begin'); $before = $p->b->call('snapshot');
  ProofGuard::check($before['events']['review'] === 1 && $before['counts']['famtastic_event'] === 1
    && $before['counts']['famtastic_portal_activity'] === 1, 'stale_event_snapshot_not_established');
  $next = $p->a->call('attach', ['version' => 2]);
  ProofGuard::check($next['newly_attached'] && !$next['root_open'], 'second_review_not_committed');
  ProofGuard::check($p->b->call('snapshot')['events']['review'] === 1, 'event_snapshot_not_repeatable_read');
  $reply = $p->b->raw('attach', ['version' => 2]);
  // Inspect B's own writes before rollback can conceal a spurious replay event
  // or activity. Its established RR snapshot still contains only version 1.
  $inside = $p->b->call('stats');
  ProofGuard::check($inside['root_open'] && $inside['events'] === $before['events']
    && $inside['counts']['famtastic_event'] === $before['counts']['famtastic_event']
    && $inside['counts']['famtastic_portal_activity'] === $before['counts']['famtastic_portal_activity'], 'stale_replay_wrote_before_rollback');
  $p->b->call('rollback'); $stats = $p->a->call('stats'); reviewQuiet($stats);
  ProofGuard::check($stats['events']['review'] === 2 && $stats['requests'][0]['review_id'] === 'synthetic-version-2'
    && $stats['requests'][0]['digest'] === $next['manifest_sha256'], 'committed_review_was_changed');
  if ($reply['ok'] === FALSE) {
    proofNeed(reviewRefused($reply, 'Review changed concurrently; retry the exact manifest.'), 'unexpected_stale_event_error');
    throw new ProofAssertion('stale_review_replay_rejected');
  }
  $replay = RemotePeer::value($reply);
  ProofGuard::check(!$replay['newly_attached'] && $replay['root_open'] && $replay['manifest_sha256'] === $next['manifest_sha256'], 'stale_event_replay_not_current');
}
function reviewStaleJob(ReviewPair $p): void {
  $p->b->call('begin'); ProofGuard::check($p->b->call('snapshot')['counts']['famtastic_job'] === 0, 'job_snapshot_not_empty');
  $p->a->call('job');
  ProofGuard::check($p->b->call('snapshot')['counts']['famtastic_job'] === 0 && $p->a->call('stats')['counts']['famtastic_job'] === 1, 'job_snapshot_not_repeatable_read');
  $reply = $p->b->raw('attach');
  // A successful old attach is visible to its own outer transaction; rollback
  // afterwards rather than committing the deliberately invalid combination.
  $inside = $p->b->call('stats'); $p->b->call('rollback');
  $stats = $p->a->call('stats'); reviewQuiet($stats, 1);
  ProofGuard::check($stats['events']['review'] === 0 && $stats['counts']['famtastic_portal_activity'] === 0, 'failed_job_guard_leaked_authority');
  if ($reply['ok'] === TRUE) {
    ProofGuard::check($reply['value']['newly_attached'] && $inside['events']['review'] === 1 && $inside['root_open'], 'unexpected_job_guard_success_shape');
    throw new ProofAssertion('stale_job_bypassed');
  }
  proofNeed(reviewRefused($reply, 'Full-site review cannot replace an existing proof routine.'), 'unexpected_job_guard_error');
}
function reviewNestedCaller(ReviewPair $p): void {
  $created = $p->a->call('create');
  $p->b->call('begin'); $p->b->call('sentinel');
  $retry = $p->b->call('create');
  ProofGuard::check($retry['root_open'] && !$retry['newly_created'] && !$retry['newly_attached'], 'nested_retry_committed_caller');
  $inside = $p->b->call('stats');
  ProofGuard::check($inside['root_open'] && $inside['organization_name'] === 'Uncommitted caller sentinel', 'nested_retry_lost_caller_write');
  ProofGuard::check($p->a->call('stats')['organization_name'] === 'Original synthetic organization', 'caller_write_became_visible');
  $p->b->call('rollback');
  reviewOneAttachment($p, $created['request_public_id']);
  $p->b->call('begin'); $p->b->call('sentinel');
  $refusal = $p->b->raw('create', ['key' => 'nested-new']);
  proofNeed(reviewRefused($refusal, 'New staff drafts require a fresh root transaction.'), 'new_nested_creation_not_refused');
  $inside = $p->b->call('stats');
  ProofGuard::check($inside['root_open'] && $inside['organization_name'] === 'Uncommitted caller sentinel', 'nested_refusal_lost_caller_write');
  ProofGuard::check($p->a->call('stats')['organization_name'] === 'Original synthetic organization', 'nested_refusal_changed_caller_scope');
  $p->b->call('rollback'); reviewOneAttachment($p, $created['request_public_id']);
}
