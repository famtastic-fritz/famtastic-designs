<?php
/** Synthetic dependency-free proof of production serializer and receipt boundary. */
declare(strict_types=1);
namespace Drupal\Component\Datetime { interface TimeInterface { public function getRequestTime(); } }
namespace Drupal\Core\Config { interface ConfigFactoryInterface { public function get($name); } }
namespace Drupal\Core\Entity { interface EntityTypeManagerInterface { public function getStorage($name); } }
namespace Drupal\Core\Database {
  class Connection {
    public array $row = []; public array $notifications = [];
    public function select($table, $alias) { return new Query($this, $table); }
    public function update($table) { return new Query($this, $table, 'update'); }
    public function merge($table) { return new Query($this, $table, 'merge'); }
    public function startTransaction() { return new class { public function rollBack() {} }; }
  }
  class Query {
    private array $data = []; private array $conditions = []; private string $key = '';
    public function __construct(private Connection $db, private string $table, private string $mode = 'select') {}
    public function fields(...$args) { if ($this->mode === 'update') $this->data = $args[0]; return $this; }
    public function condition($key, $value, $operator = '=') { $this->conditions[$key] = $value; return $this; }
    public function range(...$args) { return $this; }
    public function forUpdate() { return $this; }
    public function isNull($key) { $this->conditions[$key] = NULL; return $this; }
    public function key($key, $value) { $this->key = $value; return $this; }
    public function insertFields($data) { $this->data = $data; return $this; }
    public function execute() {
      if ($this->mode === 'merge') { $this->db->notifications[$this->key] = $this->data; return 1; }
      if ($this->mode === 'update') {
        if ($this->table === 'famtastic_notification_outbox') { foreach ($this->db->notifications as &$notification) $notification = array_replace($notification, $this->data); return 1; }
        foreach ($this->conditions as $key => $value) if (($this->db->row[$key] ?? NULL) != $value) return 0;
        $this->db->row = array_replace($this->db->row, $this->data); return 1;
      }
      return $this;
    }
    public function fetchAssoc() { return $this->db->row; }
    public function fetchField() { return 'synthetic@example.test'; }
  }
}
namespace Drupal\famtastic_pipeline\Entity {
  class Project {
    public function __construct(public array $packet) {}
    public array $studio = [];
    public function get($key) { return (object) ['value' => json_encode(array_replace($this->studio, ['site_studio_build_packet' => $this->packet]))]; }
    public function set($key, $value) { if ($key === 'studio_json') { $this->studio = json_decode($value, TRUE); $this->packet = $this->studio['site_studio_build_packet']; } return $this; }
    public function save() { return 1; }
    public function id() { return 902; }
  }
}
namespace Drupal\famtastic_pipeline\Service {
  class OperationalLedger {
    public array $events = [];
    public function recordEvent($key, $type, $payload, ...$args) { if (isset($this->events[$key])) return FALSE; $this->events[$key] = [$type, $payload]; return TRUE; }
  }
}
namespace {
  if (!function_exists('mb_strtolower')) { function mb_strtolower($s) { return strtolower($s); } }
  $serviceRoot = dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src/Service/';
  foreach (['SelectedStagingContinuation', 'SiteStudioBuildPacketService', 'StagingReceiptService'] as $class) require $serviceRoot . $class . '.php';
  use Drupal\famtastic_pipeline\Service\SelectedStagingContinuation as Producer;
  use Drupal\famtastic_pipeline\Service\StagingReceiptService as Receipts;
  if (($argv[1] ?? '') === '--manifest-digest') {
    echo \Drupal\famtastic_pipeline\Service\SiteStudioBuildPacketService::artifactManifestDigest(json_decode(stream_get_contents(STDIN), TRUE, 512, JSON_THROW_ON_ERROR));
    exit;
  }
  $html = '<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Synthetic selected site</title></head><body><main><h1>Synthetic selected site</h1><p>Complete static scope.</p></main></body></html>';
  $artifacts = [['role' => 'selected_preview', 'path' => 'proofs/selected/index.html', 'sha256' => hash('sha256', $html), 'bytes' => strlen($html)]];
  $row = ['id' => 901, 'public_id' => 'synthetic-request', 'project_id' => 902, 'customer_id' => 903, 'proof_campaign_id' => 904, 'proof_review_status' => 'selected', 'commerce_order_id' => NULL, 'staging_status' => 'queued', 'staging_review_status' => 'not_started', 'staging_receipt_hash' => '', 'staging_receipt_json' => NULL];
  $evidence = [
    'operation' => 'package_existing', 'initiating_system' => 'designs', 'correlation_id' => 'synthetic-correlation', 'requested_next_action' => 'protected_review',
    'spec' => ['capability_class' => 'static', 'site_needs' => ['pages' => ['home']]],
    'required_pages' => ['index.html'],
    'files' => [['path' => 'index.html', 'source_path' => $artifacts[0]['path'], 'url' => 'https://artifacts.example.test/selected/index.html', 'rights' => ['status' => 'approved', 'evidence_ref' => 'synthetic-original-source']]],
    'source' => ['campaign_id' => 'synthetic-campaign'],
    'selection' => ['direction_name' => 'Synthetic direction', 'proof_version' => '1', 'approval_id' => 'synthetic-selection'],
    'brand' => ['design_contract' => ['schema_version' => 1, 'tokens' => ['bg' => '#fff', 'fg' => '#111', 'accent' => '#070', 'muted' => '#555'], 'typography' => ['headings' => 'Arial', 'body' => 'Arial'], 'component_recipe' => ['proof-shell'], 'layout' => ['max_width' => '72rem', 'gutter' => '1rem', 'grid' => '1-col'], 'responsive' => ['mobile' => 'stack', 'tablet' => 'stack', 'desktop' => 'stack'], 'asset_policy' => ['preserve' => TRUE, 'rights_safe_only' => TRUE], 'evolution' => ['preserve_tokens' => TRUE, 'preserve_typography' => TRUE, 'additions_must_use_recipe' => TRUE, 'parity_required' => TRUE]]],
    'research_packet_ref' => ['packet_id' => 'synthetic-research', 'brief_hash' => str_repeat('a', 64), 'source_adapter' => 'famtastic_designs'],
    'hosting_target' => ['staging_url' => 'https://synthetic.famtasticinc.com/', 'target_path' => '/home/nineoo/public_html/synthetic', 'remote_subdirectory' => 'synthetic'],
  ];
  $packet = Producer::createPacket($row, '902', 'a', $artifacts, $artifacts[0]['path'], 'https://artifacts.example.test/selected/index.html', json_encode(['selected_build_continuation' => $evidence]), ['display_name' => 'Synthetic customer', 'email' => 'synthetic@example.test'], 905, 1, '2026-09-17T00:00:00+00:00');
  if (($argv[1] ?? '') === '--packet') { echo json_encode(['packet' => $packet], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); exit; }
  if (($argv[1] ?? '') === '--html') { echo $html; exit; }
  $count = 0;
  function check($condition, $message) { global $count; if (!$condition) throw new \RuntimeException($message); $count++; }
  function rejects($fn, $message) { try { $fn(); } catch (\InvalidArgumentException $e) { check(TRUE, $message); return; } throw new \RuntimeException('Expected rejection: ' . $message); }
  $receipt = [
    'schema' => 'famtastic.site-studio.staging-receipt.v1', 'status' => 'deployed', 'event_id' => 'synthetic-event',
    'packet_id' => $packet['packet_id'], 'idempotency_key' => $packet['idempotency_key'], 'request_id' => $packet['request_id'], 'project_id' => '902', 'website_request_id' => 901, 'customer_id' => '903', 'selection_revision' => 1,
    'selected_direction_id' => 'direction-a', 'selected_artifact_sha256' => $artifacts[0]['sha256'], 'artifact_manifest_sha256' => $packet['artifact_manifest_sha256'],
    'artifact_sha256' => str_repeat('b', 64), 'completed_at' => '2026-09-17T00:01:00Z', 'repository' => ['branch' => 'synthetic', 'mode' => 'local_only'], 'qa' => [['name' => 'scope', 'status' => 'passed']],
  ] + $evidence['hosting_target'];
  if (($argv[1] ?? '') === '--validate-source-receipt') {
    $input = json_decode(stream_get_contents(STDIN), TRUE, 512, JSON_THROW_ON_ERROR);
    $packet = $input['packet']; $receipt = $input['receipt'];
    $row['public_id'] = $packet['request_id'];
  }
  elseif (($argv[1] ?? '') === '--validate-receipt') {
    $receipt = json_decode(stream_get_contents(STDIN), TRUE, 512, JSON_THROW_ON_ERROR);
  }
  $db = new \Drupal\Core\Database\Connection(); $db->row = $row;
  $project = new \Drupal\famtastic_pipeline\Entity\Project($packet);
  $entities = new class($project) implements \Drupal\Core\Entity\EntityTypeManagerInterface {
    public function __construct(private object $project) {}
    public function getStorage($name) { return new class($this->project) { public function __construct(private object $project) {} public function load($id) { return $this->project; } }; }
  };
  $clock = new class implements \Drupal\Component\Datetime\TimeInterface { public function getRequestTime() { return 1789600000; } };
  $config = new class implements \Drupal\Core\Config\ConfigFactoryInterface { public function get($name) { return new class { public function get($key) { return 'https://agency.example.test'; } }; } };
  $ledger = new \Drupal\famtastic_pipeline\Service\OperationalLedger();
  $service = new Receipts($db, $entities, $ledger, $clock, $config);
  $result = $service->accept($receipt);
  if (in_array(($argv[1] ?? ''), ['--validate-receipt', '--validate-source-receipt'], TRUE)) { echo json_encode(['accepted' => TRUE, 'notifications' => count($db->notifications), 'checkout' => Receipts::checkoutGateSatisfied($db->row)]); exit; }
  check($result['newly_processed'] && count($db->notifications) === 1, 'success queues one existing outbox record');
  check(!Receipts::checkoutGateSatisfied($db->row), 'ready is not accepted');
  check(!$service->accept($receipt)['newly_processed'] && count($db->notifications) === 1, 'duplicate callback is idempotent');
  foreach (['customer_id' => '999', 'website_request_id' => 999, 'project_id' => '999', 'selection_revision' => 2, 'staging_url' => 'https://wrong.famtasticinc.com/', 'target_path' => '/wrong', 'selected_artifact_sha256' => str_repeat('c', 64)] as $key => $value) {
    rejects(fn() => $service->accept(array_replace($receipt, [$key => $value])), 'wrong ' . $key);
  }
  $changed = $receipt; $changed['artifact_sha256'] = str_repeat('d', 64);
  rejects(fn() => $service->accept($changed), 'different same-revision receipt');
  $failedQa = $receipt; $failedQa['qa'][0]['status'] = 'failed';
  rejects(fn() => $service->accept($failedQa), 'failed QA');
  $next = $packet; $next['continuation']['selection_revision'] = 2; $next['packet_id'] .= ':next'; $next['idempotency_key'] = $next['packet_id'];
  Producer::assertSuccessor($packet, $next); check(TRUE, 'same owner next revision');
  $jump = $next; $jump['continuation']['selection_revision'] = 3;
  rejects(fn() => Producer::assertSuccessor($packet, $jump), 'unrecorded revision gap refused');
  Producer::assertSuccessor($packet, $jump, 3); check(TRUE, 'recorded blocked intent revisions may precede next executable packet');
  rejects(fn() => Producer::assertSuccessor($packet, $packet), 'changed payload same revision');
  $other = $next; $other['continuation']['customer']['id'] = '999'; rejects(fn() => Producer::assertSuccessor($packet, $other), 'cross-tenant successor');
  $db->row['staging_review_status'] = 'accepted';
  $db->row['staging_reviewed_at'] = 1789600000;
  $registry = new \Drupal\famtastic_pipeline\Service\SiteStudioBuildPacketService($db, $entities, $ledger, $clock, $config);
  check($registry->registerPacket($next)['newly_registered'], 'actual registry accepts next revision');
  check($db->row['staging_review_status'] === 'not_started' && $db->row['staging_receipt_hash'] === '' && !Receipts::checkoutGateSatisfied($db->row), 'actual registry invalidates acceptance');
  check(count($project->studio['site_studio_build_packet_history']) === 1 && count($project->studio['site_studio_staging_history']) === 1, 'actual registry preserves prior packet receipt acceptance');
  check(!$registry->registerPacket($next)['newly_registered'], 'actual registry deduplicates exact packet');
  $registry->assertActiveSelectedPacket($next); check(TRUE, 'active registry dispatch allowed');
  rejects(fn() => $registry->assertActiveSelectedPacket($packet), 'superseded queued dispatch rejected');
  $changedPacket = $next; $changedPacket['created_at'] = 'changed'; rejects(fn() => $registry->registerPacket($changedPacket), 'actual registry refuses changed same revision');
  rejects(fn() => Receipts::assertCurrentReviewReceipt($db->row, str_repeat('e', 64)), 'stale browser acceptance');
  rejects(fn() => $service->accept(array_replace($receipt, ['final_launch' => TRUE])), 'callback cannot authorize launch');
  $project->packet = $next; rejects(fn() => $service->accept($receipt), 'stale callback after successor');
  $failure = array_diff_key($receipt, array_flip(['staging_url', 'artifact_sha256', 'target_path', 'remote_subdirectory', 'qa', 'repository']));
  $failure['schema'] = 'famtastic.site-studio.staging-failure.v1'; $failure['status'] = 'failed'; $failure['event_id'] = 'synthetic-failure'; $failure['error'] = ['code' => 'unsupported_scope', 'stage' => 'build'];
  $project->packet = $packet; $db->row = $row; $db->notifications = [];
  $service->accept($failure); check(count($db->notifications) === 0 && !Receipts::checkoutGateSatisfied($db->row) && $db->row['staging_status'] === 'failed', 'failure never notifies readiness or checkout');
  check(!$service->accept($failure)['newly_processed'] && count($db->notifications) === 0, 'duplicate failure is silent');
  rejects(fn() => Producer::attach($packet, $row, [], [], 905, 2, ''), 'missing metadata fails closed');
  $bad = $evidence; $bad['spec']['capability_class'] = 'application'; rejects(fn() => Producer::attach($packet, $row, [], ['selected_build_continuation' => $bad], 905, 2, ''), 'unsupported scope distinct from transport');
  $bad = $evidence; $bad['files'][0]['path'] = '../index.html'; rejects(fn() => Producer::attach($packet, $row, [], ['selected_build_continuation' => $bad], 905, 2, ''), 'unsafe path');
  $db->row = $row; $db->notifications = []; $project->packet = $packet;
  $registry->recordSelectedException(901, 'a', 'selected_continuation_evidence_required: missing approved scope', 'Keep the selected direction');
  check($db->row['proof_review_status'] === 'selected' && $db->row['staging_status'] === 'failed', 'missing metadata preserves selected intent and records exception');
  check(isset($project->studio['selected_staging_exception']) && count($db->notifications) === 0, 'operational exception is durable and queues no ready mail');
  rejects(fn() => $service->accept($receipt), 'old callback blocked while selected evidence unresolved');
  rejects(fn() => $registry->assertActiveSelectedPacket($packet), 'old dispatch blocked while selected evidence unresolved');
  $legacy = $packet; unset($legacy['continuation']); $legacy['packet_id'] = 'staging-packet:request:901:direction:a'; $legacy['idempotency_key'] = $legacy['packet_id'];
  $reconciled = Producer::reconcileLegacy($legacy, $row, ['display_name' => 'Synthetic customer', 'email' => 'synthetic@example.test'], ['selected_build_continuation' => $packet['continuation']], 'synthetic-recorded-migration');
  check($reconciled['selected_artifacts'] === $legacy['selected_artifacts'] && $reconciled['project_id'] === $legacy['project_id'], 'explicit legacy reconciliation preserves source and project');
  $project->packet = $legacy;
  check($registry->registerPacket($reconciled)['newly_registered'], 'actual registry accepts evidence-bound legacy upgrade');
  check(!isset($project->studio['selected_staging_exception']), 'valid registration resolves operational exception');
  rejects(fn() => Producer::reconcileLegacy($legacy, $row, [], [], 'synthetic-recorded-migration'), 'legacy metadata gaps remain explicit');
  $wrongContext = $row; $wrongContext['project_id'] = 999;
  rejects(fn() => Producer::reconcileLegacy($legacy, $wrongContext, [], [], 'synthetic-recorded-migration'), 'legacy cross-project reconciliation rejected');
  $badUpgrade = $reconciled; $badUpgrade['selected_artifacts'][0]['source_artifact_sha256'] = str_repeat('f', 64);
  rejects(fn() => Producer::assertSuccessor($legacy, $badUpgrade), 'legacy upgrade cannot replace selected source');
  echo "PASS: $count selected staging assertions (synthetic adapters; no Drupal database/runtime proof).\n";
}
