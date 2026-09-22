<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../worker-mariadb/process.php';

function reviewEmit(array $data): void { echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n"; flush(); }
function reviewError(Throwable $e): array {
  // Only these exact domain refusals are observable; no SQL, DSN or arbitrary messages.
  $allowed = [
    'A matching request exists; attach using its verified exact public UUID.',
    'Full-site review cannot replace an existing proof routine.',
    'Review changed concurrently; retry the exact manifest.',
    'New staff drafts require a fresh root transaction.',
  ];
  return ['class' => $e::class, 'message' => $e::class === RuntimeException::class && in_array($e->getMessage(), $allowed, TRUE) ? $e->getMessage() : 'unrecognized_operation_failure'];
}
final class ReviewPeer {
  private ?Drupal\Core\Database\Transaction $root = NULL;
  private ReviewServices $services;
  public const REQUEST = '11111111-2222-4333-8444-555555555555';
  public function __construct(private ReviewConnection $db, private ReviewFiles $files, private array $config, private string $role) {
    proofNeed(in_array($role, ['a', 'b'], TRUE), 'invalid_review_role');
    $this->services = new ReviewServices($db, $files);
  }
  public function rollback(): void {
    $this->db->afterRead = NULL;
    if ($this->root !== NULL) { $this->root->rollBack(); $this->root = NULL; }
    proofNeed(!$this->db->inTransaction(), 'remaining_review_transaction');
  }
  private function arm(array $r): void {
    $table = match ($r['table']) { 'request' => 'famtastic_project_request', 'member' => 'famtastic_membership', default => throw new RuntimeException('invalid_barrier_table') };
    proofNeed(is_bool($r['pause']) && is_int($r['peer']) && $r['peer'] > 0, 'invalid_barrier');
    $this->db->afterRead = function (string $read, bool $locked) use ($table, $r): void {
      if ($read !== $table || !$locked) return;
      $this->db->afterRead = NULL;
      proofNeed($this->db->inTransaction(), 'locking_read_without_transaction');
      $this->files->mark($this->role . '.' . $r['table']);
      if (!$r['pause']) return;
      $deadline = hrtime(TRUE) / 1e9 + 12;
      while (!$this->files->has($this->role . '.release')) {
        ProofGuard::tick(); proofNeed(hrtime(TRUE) / 1e9 < $deadline, 'barrier_timeout');
        // Same holder PDO observes the other actual InnoDB transaction waiting.
        $waiting = $this->db->query('SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w
          JOIN information_schema.INNODB_TRX b ON b.trx_id = w.blocking_trx_id
          JOIN information_schema.INNODB_TRX r ON r.trx_id = w.requesting_trx_id
          JOIN information_schema.INNODB_LOCKS l ON l.lock_id = w.requested_lock_id
          WHERE b.trx_mysql_thread_id = CONNECTION_ID() AND r.trx_mysql_thread_id = :peer AND l.lock_table = :table',
          [':peer' => $r['peer'], ':table' => '`' . $this->config['database'] . '`.`pr_' . $table . '`'])->fetchField();
        if ($waiting) $this->files->mark($this->role . '.wait');
        usleep(20000);
      }
    };
  }
  private function seed(bool $request): void {
    proofNeed($this->role === 'a' && !$this->db->inTransaction(), 'fixture_setup_only');
    reviewSchema($this->db, $this->config);
    foreach (array_keys(reviewTables()) as $table) if ($table !== 'fixture_owner') $this->db->delete($table)->execute();
    $this->db->insert('famtastic_customer')->fields(['id' => 91, 'uid' => 191, 'public_id' => 'synthetic-customer', 'email' => 'fixture@example.invalid', 'display_name' => 'Synthetic', 'created' => 1, 'changed' => 1])->execute();
    $this->db->insert('famtastic_organization')->fields(['id' => 92, 'public_id' => 'synthetic-organization', 'name' => 'Original synthetic organization', 'status' => 'active', 'created' => 1, 'changed' => 1])->execute();
    $this->db->insert('famtastic_membership')->fields(['organization_id' => 92, 'customer_id' => 91, 'role' => 'owner', 'status' => 'active', 'created' => 1])->execute();
    $this->db->insert('famtastic_build_run')->fields(['build_key' => 'build-dna:synthetic-private-review', 'status' => 'completed', 'source_sha' => str_repeat('a', 40), 'artifact_checksum' => str_repeat('b', 64), 'created' => 1, 'changed' => 1])->execute();
    if ($request) $this->db->insert('famtastic_project_request')->fields(['id' => 93, 'public_id' => self::REQUEST, 'customer_id' => 91, 'organization_id' => 92,
      'project_name' => 'Synthetic existing request', 'status' => 'draft', 'proof_review_status' => 'not_started', 'intake_data' => '{}', 'created' => 1, 'changed' => 1])->execute();
  }
  private function stats(): array {
    $out = ['root_open' => $this->db->inTransaction()];
    foreach (array_keys(reviewTables()) as $table) $out['counts'][$table] = (int) $this->db->select($table, 't')->countQuery()->execute()->fetchField();
    foreach (['draft' => 'website_request.staff_full_site_review_draft', 'review' => 'website_request.full_site_review_attached', 'withdraw' => 'website_request.reference_withdrawn'] as $key => $type) {
      $out['events'][$key] = (int) $this->db->select('famtastic_event', 'e')->condition('event_type', $type)->countQuery()->execute()->fetchField();
    }
    $out['assets'] = $this->db->select('famtastic_request_asset', 'a')->fields('a', ['public_id', 'status', 'sha256', 'file_id'])->execute()->fetchAll(PDO::FETCH_ASSOC);
    $out['organization_name'] = $this->db->select('famtastic_organization', 'o')->fields('o', ['name'])->condition('id', 92)->execute()->fetchField();
    $out['requests'] = [];
    foreach ($this->db->select('famtastic_project_request', 'r')->fields('r')->execute()->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $intake = json_decode($row['intake_data'], TRUE, flags: JSON_THROW_ON_ERROR);
      $review = $intake['staff_assisted_brief']['full_site_review'] ?? [];
      $out['requests'][] = ['id' => (int) $row['id'], 'public_id' => $row['public_id'], 'status' => $row['status'], 'proof_review_status' => $row['proof_review_status'],
        'customer_id' => (int) $row['customer_id'], 'organization_id' => (int) $row['organization_id'], 'customer_supplied' => $intake['staff_assisted_brief']['customer_supplied'] ?? NULL,
        'review_id' => $review['manifest']['review_id'] ?? NULL, 'digest' => $review['manifest_sha256'] ?? NULL,
        'no_delivery' => empty($row['prospect_id']) && empty($row['proof_campaign_id']) && empty($row['project_id']) && empty($row['commerce_order_id']) && empty($row['submitted_at']) && empty($row['selected_proof_direction'])];
    }
    return $out;
  }
  public function command(array $r): mixed {
    switch ($r['op']) {
      case 'seed': $this->seed($r['request'] === TRUE); return TRUE;
      case 'arm': $this->arm($r); return TRUE;
      case 'stats': case 'snapshot': return $this->stats();
      case 'begin':
        proofNeed($this->root === NULL && !$this->db->inTransaction(), 'root_already_open');
        $this->root = $this->db->startTransaction(); return TRUE;
      case 'rollback': $this->rollback(); return TRUE;
      case 'sentinel':
        proofNeed($this->root !== NULL, 'sentinel_needs_outer_transaction');
        $this->db->update('famtastic_organization')->fields(['name' => 'Uncommitted caller sentinel'])->condition('id', 92)->execute(); return TRUE;
      case 'create':
        proofNeed(in_array($r['key'] ?? 'same-key', ['same-key', 'other-key', 'nested-new'], TRUE), 'unexpected_request_key');
        $result = $this->services->reviews->createAndAttach(91, 92, 'Synthetic staff project', $r['key'] ?? 'same-key', $this->files->path('source'), $this->files->manifest(), 'fixture:staff', 'Synthetic harness only');
        return ['root_open' => $this->db->inTransaction()] + array_intersect_key($result, array_flip(['newly_created', 'newly_attached', 'request_id', 'request_public_id', 'manifest_sha256']));
      case 'attach':
        $result = $this->services->reviews->attach(self::REQUEST, 91, 92, $this->files->path('source'), $this->files->manifest($r['version'] ?? 1), 'fixture:staff', 'Synthetic harness only');
        return ['root_open' => $this->db->inTransaction()] + array_intersect_key($result, array_flip(['newly_attached', 'manifest_sha256']));
      case 'job':
        proofNeed($this->role === 'a' && !$this->db->inTransaction(), 'job_fixture_setup_only');
        return $this->services->ledger->enqueue('website_proof.generate.v1:request:93:brief:' . str_repeat('c', 64), 'proof.generate', ['synthetic_never_execute' => TRUE]);
      case 'read':
        $read = $this->services->reviews->read(91, $r['public_id'], 'index.html');
        return ['sha256' => hash('sha256', $read['bytes']), 'bytes' => strlen($read['bytes']), 'manifest_sha256' => $read['manifest_sha256']];
      case 'upload':
        $request = Symfony\Component\HttpFoundation\Request::create('https://example.invalid/', 'POST', ['ownership_confirmed' => '1'], [],
          ['asset' => new Symfony\Component\HttpFoundation\File\UploadedFile($this->files->path('upload.png'), 'reference.png', 'image/png', UPLOAD_ERR_OK, TRUE)]);
        $response = $this->services->controller->uploadAsset($request, $r['public_id']);
        $body = json_decode($response->getContent(), TRUE, flags: JSON_THROW_ON_ERROR);
        return ['status' => $response->getStatusCode(), 'duplicate' => $body['duplicate'] ?? NULL, 'root_open' => $this->db->inTransaction()];
      case 'withdraw':
        $this->services->portal->withdrawWebsiteRequestAsset(91, $r['public_id'], $r['asset_id']); return ['root_open' => $this->db->inTransaction()];
      default: throw new RuntimeException('unknown_review_operation');
    }
  }
}

$peer = NULL; $exit = 0;
ProofGuard::$deadline = hrtime(TRUE) / 1e9 + 180;
try {
  proofNeed(PHP_SAPI === 'cli' && $argc === 5, 'review_peer_arguments');
  ProofGuard::tick(); umask(0077);
  $config = proofConfig($argv[1]); reviewLoad($argv[2]); require __DIR__ . '/fixtures.php';
  $files = new ReviewFiles($argv[1], $argv[3]);
  // Validate identity with the unchanged bootstrap, then wrap that SAME PDO.
  $validated = proofConnection($config); $options = $validated->getConnectionOptions(); $options['prefix'] = 'pr_';
  $db = new ReviewConnection($validated->getClientConnection(), $options); unset($validated);
  $db->query('SET SESSION innodb_lock_wait_timeout = 15');
  $db->query('SET SESSION max_statement_time = 18');
  $peer = new ReviewPeer($db, $files, $config, $argv[4]);
  reviewEmit(['phase' => 'hello', 'connection_id' => (int) $db->query('SELECT CONNECTION_ID()')->fetchField(), 'php' => PHP_VERSION,
    'server_version' => $db->query('SELECT VERSION()')->fetchField(), 'source_mode' => $argv[2]]);
  while (($line = fgets(STDIN, 16385)) !== FALSE) {
    ProofGuard::tick(); proofNeed(str_ends_with($line, "\n") && strlen($line) < 16384, 'oversized_review_command');
    $r = json_decode($line, TRUE, flags: JSON_THROW_ON_ERROR);
    proofNeed(is_int($r['id']) && is_string($r['op']), 'invalid_review_command');
    reviewEmit(['phase' => 'started', 'id' => $r['id']]);
    try { reviewEmit(['phase' => 'done', 'id' => $r['id'], 'ok' => TRUE, 'value' => $peer->command($r)]); }
    catch (Throwable $e) { reviewEmit(['phase' => 'done', 'id' => $r['id'], 'ok' => FALSE, 'error' => reviewError($e)]); }
  }
} catch (Throwable $e) {
  reviewEmit(['phase' => 'bootstrap_or_protocol_failure', 'error' => reviewError($e)]); $exit = 2;
} finally { if ($peer !== NULL) $peer->rollback(); }
exit($exit);
