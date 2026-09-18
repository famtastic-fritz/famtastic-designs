<?php
/** Real service writers with synthetic persistence, files and installation policy. */
declare(strict_types=1);
namespace Drupal\Core\Entity { interface EntityTypeManagerInterface { public function getStorage($name); } }
namespace Drupal\Component\Datetime { interface TimeInterface { public function getRequestTime(); } }
namespace Drupal\Core\Config { interface ConfigFactoryInterface { public function get($name); } }
namespace Drupal\Core\File { interface FileSystemInterface { const CREATE_DIRECTORY = 1, MODIFY_PERMISSIONS = 2, EXISTS_REPLACE = 1; } }
namespace Drupal\Core\Site { class Settings { public static function get($key, $default = NULL) { return $default; } } }
namespace Drupal\Core\Database\Statement { class FetchAs { const Associative = 2; } }
namespace GuzzleHttp { interface ClientInterface {} }
namespace Drupal\famtastic_pipeline\Entity {
  class MemoryEntity {
    public function __construct(public array $data = [], public int $number = 1) {}
    public function id() { return $this->number; }
    public function get($key) { return (object) ['value' => $this->data[$key] ?? '', 'target_id' => $this->data[$key] ?? 0]; }
    public function set($key, $value) { $this->data[$key] = $value; return $this; }
    public function save() { return 1; }
  }
  class Project extends MemoryEntity {} class ProofCampaign extends MemoryEntity {} class ProofVariant extends MemoryEntity {}
}
namespace Drupal\famtastic_pipeline\Service {
  class OperationalLedger { public array $jobs = []; public function recordEvent(...$args) { return TRUE; } public function enqueue($key, $type, $payload, ...$args) { $this->jobs[$key] ??= compact('type', 'payload'); return count($this->jobs); } }
  class PublicPreviewDeliveryService { public function isPublicDeliveryForCampaign(...$args) { return FALSE; } }
}
namespace Drupal\Core\Database {
  class Connection {
    public array $row = []; public array $writes = []; public array $tables = [];
    public function startTransaction() { return new class { public function rollBack() {} }; }
    public function escapeLike($s) { return $s; }
    public function select($table, ...$args) { return new Query($this, $table); }
    public function update($table) { return new Query($this, $table, TRUE); }
    public function insert($table) { return new Query($this, $table, TRUE); }
    public function merge($table) { return new Query($this, $table, TRUE); }
  }
  class Query {
    private array $values = [];
    public function __construct(private Connection $db, private string $table, private bool $write = FALSE) {}
    public function fields(...$args) { if ($this->write) $this->values = $args[0]; return $this; }
    public function insertFields($values) { $this->values = $values; return $this; }
    public function updateFields($values) { $this->values = $values + $this->values; return $this; }
    public function __call($name, $args) { return $this; }
    public function execute() { if ($this->write) { $this->db->writes[] = $this->table; $this->db->tables[$this->table] = $this->values; if ($this->table === 'famtastic_project_request') $this->db->row = $this->values + $this->db->row; return 1; } return $this; }
    public function fetchField() { return match ($this->table) { 'famtastic_membership' => 1, 'famtastic_website_proof_research_snapshot' => $this->db->tables[$this->table]['snapshot_json'] ?? FALSE, default => 0 }; }
    public function fetchAssoc() { return match ($this->table) { 'famtastic_project_request' => $this->db->row, 'famtastic_customer_resource' => ['organization_id' => 907, 'resource_type' => 'project', 'resource_id' => 902], 'famtastic_customer' => ['id' => 903, 'display_name' => 'Synthetic owner', 'email' => 'owner@example.invalid'], 'famtastic_website_proof_research_snapshot' => $this->db->tables[$this->table] ?? FALSE, default => FALSE }; }
    public function fetchAll(...$args) { return []; }
  }
}
namespace {
  class Drupal { public static string $dir; public static function root() { return self::$dir . '/web'; } }
  if (!function_exists('mb_substr')) { function mb_substr($s, $start, $length) { return substr($s, $start, $length); } }
  if (!function_exists('mb_strtolower')) { function mb_strtolower($s) { return strtolower($s); } }
  $root = dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src/Service/';
  foreach (['OutreachMailer', 'ProofAssetContract', 'SelectedSourceCapture', 'SelectedRequestContent', 'SelectedRecordResolver', 'SelectedSourceIntent', 'SelectedFinalizedSource', 'SelectedStagingContinuation', 'SelectedPlanningPacket', 'SiteStudioBuildPacketService', 'CustomerPortalService', 'ProofCampaignService', 'StagingReceiptService', 'SiteStudioStagingClient', 'AutomationWorker'] as $class) require $root . $class . '.php';
  $input = json_decode(stream_get_contents(STDIN), TRUE, 512, JSON_THROW_ON_ERROR);
  $tmp = sys_get_temp_dir() . '/normal-selected-' . bin2hex(random_bytes(6)); mkdir($tmp); mkdir($tmp . '/web'); Drupal::$dir = $tmp;
  $set = static function(object $object, array $fields): void { $r = new \ReflectionClass($object); foreach ($fields as $k => $v) $r->getProperty($k)->setValue($object, $v); };
  $project = new \Drupal\famtastic_pipeline\Entity\Project(['studio_json' => '{}'], 902);
  $campaign = new \Drupal\famtastic_pipeline\Entity\ProofCampaign(['campaign_id' => 'normal-proof', 'studio_job_id' => 'normal-job', 'prospect_id' => 906, 'callback_event_ids' => '[]'], 904);
  $entities = new class($project, $campaign) implements \Drupal\Core\Entity\EntityTypeManagerInterface {
    public array $variants = [];
    public function __construct(public object $project, public object $campaign) {}
    public function getStorage($name) { return new class($this, $name) {
      public function __construct(private object $all, private string $name) {}
      public function load($id) { return match ($this->name) { 'famtastic_project' => $this->all->project, 'proof_campaign' => $this->all->campaign, 'proof_variant' => $this->all->variants[$id] ?? NULL, default => NULL }; }
      public function create($data) { $id = 905 + count($this->all->variants); return $this->all->variants[$id] = new \Drupal\famtastic_pipeline\Entity\ProofVariant($data, $id); }
      public function loadMultiple($ids) { return array_intersect_key($this->all->variants, array_flip($ids)); }
      public function getQuery() { return new class($this->all, $this->name) {
        private ?string $direction = NULL; private bool $count = FALSE;
        public function __construct(private object $all, private string $name) {} public function __call($n, $a) { return $this; }
        public function condition($key, $value) { if ($key === 'direction_id') $this->direction = $value; return $this; }
        public function count() { $this->count = TRUE; return $this; }
        public function execute() { $ids = $this->name === 'proof_campaign' ? [904] : array_keys(array_filter($this->all->variants, fn($v) => $this->direction === NULL || $v->get('direction_id')->value === $this->direction)); return $this->count ? count($ids) : $ids; }
      }; }
    }; }
  };
  $db = new \Drupal\Core\Database\Connection();
  $db->row = ['id' => 901, 'public_id' => 'normal-request', 'project_id' => 902, 'customer_id' => 903, 'organization_id' => 907, 'prospect_id' => 906, 'proof_campaign_id' => 904,
    'status' => 'draft', 'project_name' => 'Synthetic request', 'business_name' => 'Synthetic', 'intake_data' => '{}', 'proof_review_status' => 'building', 'submitted_at' => NULL];
  $clock = new class implements \Drupal\Component\Datetime\TimeInterface { public function getRequestTime() { return 1789600000; } };
  $config = new class($input['installation']) implements \Drupal\Core\Config\ConfigFactoryInterface {
    public function __construct(private array $installation) {} public function get($name) { return new class($this->installation) {
      public function __construct(private array $installation) {} public function get($key) { return $key === 'selected_staging' ? $this->installation : 'https://agency.example.invalid'; }
    }; }
  };
  $ledger = new \Drupal\famtastic_pipeline\Service\OperationalLedger();
  $registry = new \Drupal\famtastic_pipeline\Service\SiteStudioBuildPacketService($db, $entities, $ledger, $clock, $config);
  $portal = (new \ReflectionClass(\Drupal\famtastic_pipeline\Service\CustomerPortalService::class))->newInstanceWithoutConstructor();
  $set($portal, ['database' => $db, 'entities' => $entities, 'time' => $clock, 'configFactory' => $config, 'ledger' => $ledger, 'siteStudioPackets' => $registry]);
  $proofs = (new \ReflectionClass(\Drupal\famtastic_pipeline\Service\ProofCampaignService::class))->newInstanceWithoutConstructor();
  $fs = new class implements \Drupal\Core\File\FileSystemInterface {
    public function prepareDirectory($path, $flags) { return is_dir($path) || mkdir($path, 0700, TRUE); }
    public function saveData($bytes, $path, $flags) { file_put_contents($path, $bytes); return $path; }
  };
  $set($proofs, ['entityTypeManager' => $entities, 'configFactory' => $config, 'time' => $clock, 'fileSystem' => $fs, 'ledger' => $ledger, 'database' => $db, 'portal' => $portal, 'previews' => new \Drupal\famtastic_pipeline\Service\PublicPreviewDeliveryService()]);
  try {
    $raw = $input['raw_callback']; $callback = json_decode($raw, TRUE, 512, JSON_THROW_ON_ERROR);
    $first = $proofs->acceptCallback($callback['event_id'], $callback['campaign_id'], $callback['job_id'], $callback['variants'], $raw);
    $retry = $proofs->acceptCallback($callback['event_id'], $callback['campaign_id'], $callback['job_id'], $callback['variants'], $raw);
    if (!$first['newly_processed'] || $retry['newly_processed']) throw new \RuntimeException('Duplicate callback wrote new variants');
    $request = $input['request']; $requestRaw = $input['raw_request'] ?? json_encode($request, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $portal->updateWebsiteRequest(903, 'normal-request', $request, $requestRaw);
    $ref = new \ReflectionClass($portal);
    $variant = $first['variants'][0];
    $portal->saveWebsiteRequestProofResearchSnapshot(901, 1, ['overview' => 'Synthetic owner review', 'direction_rationale' => ['a' => 'Clear introduction', 'b' => 'Bold introduction', 'c' => 'Expressive introduction'], 'sources' => ['Synthetic authored brief'], 'researched_at' => '2026-09-17']);
    $portal->approveWebsiteRequestProof(901, 1);
    $portal->decideWebsiteRequestProof(903, 'normal-request', ['action' => 'select', 'direction' => 'a']);
    $state = json_decode($project->get('studio_json')->value, TRUE);
    $packet = $state['selected_dispatch_packet'];
    $portal->decideWebsiteRequestProof(903, 'normal-request', ['action' => 'select', 'direction' => 'a']);
    if ($state !== json_decode($project->get('studio_json')->value, TRUE)) throw new \RuntimeException('Selection retry changed state');
    $bytes = []; $negative = [];
    if ($packet['schema'] === 'famtastic.site-studio.build-packet.v1') foreach ($packet['artifacts'] as $a) {
      $hash = $a['sha256']; $rev = $packet['continuation']['selection_revision']; $stamp = 1789600000;
      $signature = hash_hmac('sha256', "selected-artifact.v1\nnormal-request\n$rev\n$hash\n$stamp", 'synthetic-reader-secret');
      $auth = "FAMtastic-Artifact $stamp:$signature";
      $auth = $input['artifact_authorizations'][$hash] ?? $auth;
      $bytes[$hash] = base64_encode($registry->readSelectedArtifact('normal-request', $rev, $hash, $auth, 'synthetic-reader-secret', $stamp));
      foreach (['bad_signature', 'expired', 'wrong_revision'] as $case) {
        try { $registry->readSelectedArtifact('normal-request', $case === 'wrong_revision' ? $rev + 1 : $rev, $hash, $case === 'bad_signature' ? $auth . '0' : $auth, 'synthetic-reader-secret', $case === 'expired' ? $stamp + 301 : $stamp); throw new \RuntimeException('Reader accepted ' . $case); }
        catch (\InvalidArgumentException) { $negative[$case] = TRUE; }
      }
    }
    putenv('SITE_STUDIO_STAGING_URL=https://studio.example.invalid/api/pipeline/staging/accept'); putenv('FAMTASTIC_STUDIO_DISPATCH_SECRET=synthetic-normal-secret');
    $http = new class implements \GuzzleHttp\ClientInterface {
      public array $wire = [];
      public function request($method, $url, $options) {
        $this->wire = compact('method', 'url') + $options; $packet = json_decode($options['body'], TRUE)['packet'];
        return new class($packet) { public function __construct(private array $p) {} public function getStatusCode() { return 202; } public function getBody() { return json_encode(['accepted' => TRUE, 'status' => 'accepted_waiting_callback', 'receipt' => ['receipt_id' => 'normal-acceptance', 'packet_id' => $this->p['packet_id'], 'idempotency_key' => $this->p['idempotency_key']]]); } };
      }
    };
    $workerRef = new \ReflectionClass(\Drupal\famtastic_pipeline\Service\AutomationWorker::class); $worker = $workerRef->newInstanceWithoutConstructor();
    $set($worker, ['portal' => $portal, 'stagingClient' => new \Drupal\famtastic_pipeline\Service\SiteStudioStagingClient($http, $config)]);
    $jobs = array_values(array_filter($ledger->jobs, fn($j) => $j['type'] === 'site_studio_staging_prepare'));
    if (count($jobs) !== 1) throw new \RuntimeException('Duplicate staging job');
    $workerRef->getMethod('prepareSiteStudioStaging')->invoke($worker, $jobs[0]);
    $accepted = NULL;
    if (isset($input['receipt'])) {
      $receipts = new \Drupal\famtastic_pipeline\Service\StagingReceiptService($db, $entities, $ledger, $clock, $config);
      $accepted = $receipts->accept($input['receipt']);
      if (!$accepted['newly_processed'] || $receipts->accept($input['receipt'])['newly_processed'] || $receipts->isReady(901)) throw new \RuntimeException('Receipt retry or checkout boundary failed');
    }
    $variantDna = json_decode($variant->get('design_dna')->value, TRUE);
    $capturedRaw = file_get_contents(dirname($tmp . '/' . $variant->get('artifact_path')->value) . '/' . $variantDna['source_capture']['raw_callback_file']);
    echo json_encode(['packet' => $packet, 'wire' => $http->wire, 'receipt_result' => $accepted, 'artifact_bytes' => $bytes, 'reader_negatives' => $negative, 'jobs' => $jobs, 'intake' => json_decode($db->row['intake_data'], TRUE), 'variant_dna' => $variantDna, 'captured_raw_callback' => $capturedRaw], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  } finally {
    $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($walk as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($tmp);
  }
}
