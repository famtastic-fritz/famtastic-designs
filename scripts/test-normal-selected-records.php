<?php
/** Real service writers with synthetic persistence, files and installation policy. */
declare(strict_types=1);
namespace Drupal\Core\Entity { interface EntityTypeManagerInterface { public function getStorage($name); } }
namespace Drupal\Component\Datetime { interface TimeInterface { public function getRequestTime(); } }
namespace Drupal\Core\Config { interface ConfigFactoryInterface { public function get($name); } }
namespace Drupal\Core\File { interface FileSystemInterface { const CREATE_DIRECTORY = 1, MODIFY_PERMISSIONS = 2, EXISTS_REPLACE = 1, EXISTS_ERROR = 0; } }
namespace Drupal\Core\Controller { class ControllerBase {} }
namespace Drupal\Core\Session { interface AccountProxyInterface {} }
namespace Drupal\Component\Uuid { interface UuidInterface {} }
namespace Drupal\file { interface FileRepositoryInterface {} }
namespace Drupal\file\FileUsage { interface FileUsageInterface {} }
namespace Symfony\Component\HttpFoundation {
  class Response {} class JsonResponse extends Response { public function __construct(public array $data, public int $status = 200) {} }
  class Request {
    public object $request; public object $files; public object $headers; public string $content = '';
    public function getContent() { return $this->content; }
    public static function callback(array $data): self { $r = new self([], new \stdClass()); $r->content = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); $r->headers = new class($r->content) { public function __construct(private string $raw) {} public function get($key, $default = '') { return 'sha256=' . hash_hmac('sha256', $this->raw, 'synthetic-association-secret'); } }; return $r; }
    public function __construct(array $values, object $file) {
      $this->request = new class($values) { public function __construct(private array $values) {} public function get($key, $default = NULL) { return $this->values[$key] ?? $default; } public function getBoolean($key) { return !empty($this->values[$key]); } };
      $this->files = new class($file) { public function __construct(private object $file) {} public function get($key) { return $this->file; } };
    }
  }
}
namespace Drupal\Core\Site { class Settings { public static function get($key, $default = NULL) { return $key === 'site_studio_callback_secret' ? 'synthetic-association-secret' : $default; } public static function getHashSalt() { return 'synthetic-share-salt'; } } }
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
  // This legacy fixture has no private offers or managed-admission events.
  // Preserve grouped-query syntax but reject any attempt to seed managed events;
  // their actual query/transaction semantics are exercised in the SQLite suite.
  class ConditionGroup {
    public array $conditions = [];
    public function isNull($key) { $this->conditions[] = [$key, NULL, 'IS NULL']; return $this; }
    public function condition($key, $value = NULL, $operator = '=') { $this->conditions[] = [$key, $value, $operator]; return $this; }
  }
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
    private array $conditions = [];
    public function __construct(private Connection $db, private string $table, private bool $write = FALSE) {}
    public function fields(...$args) { if ($this->write) $this->values = $args[0]; return $this; }
    public function insertFields($values) { $this->values = $values; return $this; }
    public function updateFields($values) { $this->values = $values + $this->values; return $this; }
    public function orConditionGroup() { return new ConditionGroup(); }
    public function andConditionGroup() { return new ConditionGroup(); }
    public function condition($key, $value = NULL, ...$args) {
      if ($key instanceof ConditionGroup) {
        if (!in_array($this->table, ['famtastic_private_offer', 'famtastic_event'], TRUE)) throw new \LogicException('Grouped queries require an explicit fixture implementation');
        return $this;
      }
      $this->conditions[$key] = $value;
      return $this;
    }
    private function asset() { $row = $this->db->tables['famtastic_request_asset'] ?? FALSE; if (!$row) return FALSE; $row += ['id' => 1]; foreach ($this->conditions as $key => $value) if ((string) ($row[$key] ?? '') !== (string) $value) return FALSE; return $row; }
    public function __call($name, $args) { return $this; }
    public function execute() { if ($this->table === 'famtastic_event' && ($this->write || !empty($this->db->tables[$this->table]))) throw new \LogicException('This legacy fixture cannot model managed admission events; use the real SQLite suite.'); if ($this->write) { $this->db->writes[] = $this->table; $this->db->tables[$this->table] = $this->values + ($this->table === 'famtastic_request_asset' ? ($this->db->tables[$this->table] ?? []) : []); if ($this->table === 'famtastic_project_request') $this->db->row = $this->values + $this->db->row; return 1; } return $this; }
    public function fetchCol() {
      if ($this->table !== 'famtastic_project_request') throw new \LogicException('Unsupported fixture column query');
      foreach ($this->conditions as $key => $value) if ((string) ($this->db->row[$key] ?? '') !== (string) $value) return [];
      return isset($this->db->row['id']) ? [$this->db->row['id']] : [];
    }
    public function fetchField() { return match ($this->table) { 'famtastic_membership' => 1, 'famtastic_website_proof_research_snapshot' => $this->db->tables[$this->table]['snapshot_json'] ?? FALSE, default => 0 }; }
    public function fetchAssoc() { return match ($this->table) { 'famtastic_request_asset' => $this->asset(), 'famtastic_project_request' => $this->db->row, 'famtastic_customer_resource' => ['organization_id' => 907, 'resource_type' => 'project', 'resource_id' => 902], 'famtastic_customer' => ['id' => 903, 'display_name' => 'Synthetic owner', 'email' => 'owner@example.invalid'], 'famtastic_website_proof_research_snapshot' => $this->db->tables[$this->table] ?? FALSE, default => FALSE }; }
    public function fetchAll(...$args) { return $this->table === 'famtastic_request_asset' && $this->asset() ? [$this->asset()] : []; }
  }
}
namespace {
  class Drupal { public static string $dir; public static function root() { return self::$dir . '/web'; } }
  if (!function_exists('mb_substr')) { function mb_substr($s, $start, $length) { return substr($s, $start, $length); } }
  if (!function_exists('mb_strtolower')) { function mb_strtolower($s) { return strtolower($s); } }
  $root = dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src/Service/';
  foreach (['OutreachMailer', 'ProofAssetContract', 'FreshProofInput', 'FreshProofBinding', 'FreshProofAdmission', 'SelectedAssetRights', 'SelectedSourceCapture', 'SelectedRequestContent', 'SelectedRecordResolver', 'SelectedSourceIntent', 'SelectedFinalizedSource', 'SelectedStagingContinuation', 'SelectedPlanningPacket', 'SiteStudioBuildPacketService', 'CustomerPortalService', 'ProofCampaignService', 'StagingReceiptService', 'SiteStudioStagingClient', 'AutomationWorker'] as $class) require $root . $class . '.php';
  require $root . 'CharacterAssetService.php';
  require $root . 'SelectedSourceAssociation.php';
  require dirname($root) . '/Controller/WebsiteRequestProofController.php';
  require dirname($root) . '/Controller/SiteStudioCallbackController.php';
  $interactive = in_array('--association', $argv, TRUE);
  $input = json_decode($interactive ? fgets(STDIN) : stream_get_contents(STDIN), TRUE, 512, JSON_THROW_ON_ERROR);
  $tmp = sys_get_temp_dir() . '/normal-selected-' . bin2hex(random_bytes(6)); mkdir($tmp); mkdir($tmp . '/web'); Drupal::$dir = $tmp;
  $set = static function(object $object, array $fields): void { $r = new \ReflectionClass($object); foreach ($fields as $k => $v) $r->getProperty($k)->setValue($object, $v); };
  $project = empty($input['no_project']) ? new \Drupal\famtastic_pipeline\Entity\Project(['studio_json' => '{}'], 902) : NULL;
  $campaign = new \Drupal\famtastic_pipeline\Entity\ProofCampaign(['campaign_id' => json_decode($input['raw_callback'], TRUE, 512, JSON_THROW_ON_ERROR)['campaign_id'], 'studio_job_id' => 'normal-job', 'prospect_id' => 906, 'callback_event_ids' => '[]'], 904);
  $entities = new class($project, $campaign) implements \Drupal\Core\Entity\EntityTypeManagerInterface {
    public array $variants = []; public int $projectsCreated = 0;
    public function __construct(public ?object $project, public object $campaign) {}
    public function getStorage($name) { return new class($this, $name) {
      public function __construct(private object $all, private string $name) {}
      public function load($id) { return match ($this->name) { 'famtastic_project' => $this->all->project, 'proof_campaign' => $this->all->campaign, 'proof_variant' => $this->all->variants[$id] ?? NULL, default => NULL }; }
      public function create($data) { if ($this->name === 'famtastic_project') { $this->all->projectsCreated++; return $this->all->project = new \Drupal\famtastic_pipeline\Entity\Project($data, 902); } $id = 905 + count($this->all->variants); return $this->all->variants[$id] = new \Drupal\famtastic_pipeline\Entity\ProofVariant($data, $id); }
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
  $db->row = ['id' => 901, 'public_id' => 'normal-request', 'project_id' => $project ? 902 : NULL, 'customer_id' => 903, 'organization_id' => 907, 'prospect_id' => 906, 'proof_campaign_id' => 904,
    'status' => 'draft', 'project_name' => 'Synthetic request', 'business_name' => 'Synthetic', 'intake_data' => '{}', 'proof_review_status' => 'building', 'submitted_at' => NULL];
  $clock = new class implements \Drupal\Component\Datetime\TimeInterface { public int $now = 1789600000; public function getRequestTime() { return $this->now; } };
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
    public function prepareDirectory($path, $flags) { return str_starts_with($path, 'private://') || is_dir($path) || mkdir($path, 0700, TRUE); }
    public function saveData($bytes, $path, $flags) { file_put_contents($path, $bytes); return $path; }
  };
  $set($proofs, ['entityTypeManager' => $entities, 'configFactory' => $config, 'time' => $clock, 'fileSystem' => $fs, 'ledger' => $ledger, 'database' => $db, 'portal' => $portal, 'previews' => new \Drupal\famtastic_pipeline\Service\PublicPreviewDeliveryService()]);
  try {
    $uploadResult = NULL;
    if (isset($input['upload'])) {
      $upload = $input['upload']; file_put_contents($tmp . '/upload.png', base64_decode($upload['base64'], TRUE));
      $file = new class($tmp . '/upload.png') { public function __construct(private string $path) {} public function isValid() { return TRUE; } public function getSize() { return filesize($this->path); } public function getPathname() { return $this->path; } public function getClientOriginalName() { return 'logo.png'; } };
      $account = new class implements \Drupal\Core\Session\AccountProxyInterface { public function isAuthenticated() { return TRUE; } public function id() { return 777; } };
      $repository = new class($tmp) implements \Drupal\file\FileRepositoryInterface { public function __construct(private string $tmp) {} public function writeData($bytes, $path, $flags) { file_put_contents($this->tmp . '/private-upload.png', $bytes); return new class { public function setPermanent() {} public function save() {} public function id() { return 1001; } }; } };
      $usage = new class implements \Drupal\file\FileUsage\FileUsageInterface { public function add(...$args) {} };
      $uuid = new class implements \Drupal\Component\Uuid\UuidInterface { public function generate() { return 'normal-upload-1'; } };
      $character = (new \ReflectionClass(\Drupal\famtastic_pipeline\Service\CharacterAssetService::class))->newInstanceWithoutConstructor();
      $controller = new \Drupal\famtastic_pipeline\Controller\WebsiteRequestProofController($db, $entities, $portal, $account, $fs, $repository, $usage, $uuid, $character);
      $uploadResult = $controller->uploadAsset(new \Symfony\Component\HttpFoundation\Request($upload['fields'] ?? ['ownership_confirmed' => TRUE], $file), 'normal-request');
      if (isset($input['asset_changes'], $db->tables['famtastic_request_asset'])) $db->tables['famtastic_request_asset'] = $input['asset_changes'] + $db->tables['famtastic_request_asset'];
    }
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
    $project = $entities->project;
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
    if ($interactive) {
      $receipts = new \Drupal\famtastic_pipeline\Service\StagingReceiptService($db, $entities, $ledger, $clock, $config);
      $callbacks = new \Drupal\famtastic_pipeline\Controller\SiteStudioCallbackController($proofs, $registry, $receipts, $portal);
      echo json_encode(['packet' => $packet, 'artifact_bytes' => $bytes, 'mapping_absent' => !isset($state['selected_source_mapping'])], JSON_THROW_ON_ERROR) . "\n"; flush();
      while (($line = fgets(STDIN)) !== FALSE) {
        $command = json_decode($line, TRUE, 512, JSON_THROW_ON_ERROR);
        if (!empty($command['close'])) return;
        if (isset($command['row_change'])) $db->row = $command['row_change'] + $db->row;
        if (isset($command['now'])) $clock->now = $command['now'];
        if (isset($command['selected_html'])) file_put_contents($tmp . '/' . $variant->get('artifact_path')->value, $command['selected_html']);
        if (isset($command['update'])) $portal->updateWebsiteRequest(903, 'normal-request', $command['update'], json_encode($command['update'], JSON_THROW_ON_ERROR));
        $response = isset($command['callback']) ? $callbacks->handle(\Symfony\Component\HttpFoundation\Request::callback($command['callback'])) : new \Symfony\Component\HttpFoundation\JsonResponse(['ok' => TRUE]);
        $current = json_decode($project->get('studio_json')->value, TRUE);
        $currentBytes = [];
        foreach ($current['selected_dispatch_packet']['artifacts'] ?? [] as $a) if (!str_starts_with($a['path'], 'next-source/')) $currentBytes[$a['sha256']] = base64_encode(file_get_contents($tmp . '/' . $a['path']));
        echo json_encode(['status' => $response->status, 'response' => $response->data, 'state' => $current, 'row' => $db->row, 'artifact_bytes' => $currentBytes], JSON_THROW_ON_ERROR) . "\n"; flush();
      }
      return;
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
      if (!empty($input['accept_review'])) $portal->acceptWebsiteStagingReview(903, 'normal-request', $db->row['staging_receipt_hash']);
      if (!empty($input['studio_origin'])) {
        $envelope = ['request_id' => 'normal-request', 'project_id' => '902', 'customer_id' => '903', 'source_export' => $input['receipt']['source_completion']['source_export']];
        $registry->registerSourceExport($envelope);
        if ($registry->registerSourceExport($envelope)['newly_processed']) throw new \RuntimeException('Source retry duplicated mapping');
      }
    }
    if (isset($input['followup_request'])) $portal->updateWebsiteRequest(903, 'normal-request', $input['followup_request'], $input['followup_raw'] ?? json_encode($input['followup_request'], JSON_THROW_ON_ERROR));
    foreach ($input['followup_requests'] ?? [] as $followup) $portal->updateWebsiteRequest(903, 'normal-request', $followup, json_encode($followup, JSON_THROW_ON_ERROR));
    if (!empty($input['withdraw_after_receipt'])) {
      if ($controller->withdrawAsset(new \Symfony\Component\HttpFoundation\Request([], $file), 'normal-request', 'normal-upload-1')->status !== 200) throw new \RuntimeException('Reference withdrawal failed');
      $afterWithdrawal = $project->get('studio_json')->value;
      $controller->withdrawAsset(new \Symfony\Component\HttpFoundation\Request([], $file), 'normal-request', 'normal-upload-1');
      if ($afterWithdrawal !== $project->get('studio_json')->value || $receipts->isReady(901)) throw new \RuntimeException('Withdrawal retry or checkout boundary failed');
      try { $registry->assertActiveSelectedPacket($packet); throw new \RuntimeException('Withdrawn reference remained dispatchable'); }
      catch (\InvalidArgumentException) { $negative['asset_rights_changed'] = TRUE; }
      $reupload = $controller->uploadAsset(new \Symfony\Component\HttpFoundation\Request($upload['fields'], $file), 'normal-request');
      if ($reupload->status !== 409 || $reupload->data['ok'] !== FALSE || $db->tables['famtastic_request_asset']['status'] !== 'withdrawn') throw new \RuntimeException('Withdrawn reupload claimed active success');
      $negative['withdrawn_reupload_rejected'] = $reupload->data['message'];
    }
    if (isset($input['later_asset_changes'])) {
      $db->tables['famtastic_request_asset'] = $input['later_asset_changes'] + $db->tables['famtastic_request_asset'];
      try { $registry->assertActiveSelectedPacket($packet); throw new \RuntimeException('Changed asset rights remained dispatchable'); }
      catch (\InvalidArgumentException) { $negative['asset_rights_changed'] = TRUE; }
      if (isset($receipts) && $receipts->isReady(901)) throw new \RuntimeException('Withdrawn asset remained checkout-ready');
      $portal->refreshSelectedWebsiteRequest(903, 'normal-request');
    }
    $finalState = json_decode($project->get('studio_json')->value, TRUE);
    $finalPacket = $finalState['selected_dispatch_packet'];
    $finalBytes = [];
    if ($finalPacket['schema'] === 'famtastic.site-studio.build-packet.v1') foreach ($finalPacket['artifacts'] as $a) {
      if (str_starts_with($a['path'], 'next-source/')) continue;
      $hash = $a['sha256']; $rev = $finalPacket['continuation']['selection_revision']; $stamp = 1789600000;
      $signature = hash_hmac('sha256', "selected-artifact.v1\nnormal-request\n$rev\n$hash\n$stamp", 'synthetic-reader-secret');
      $finalBytes[$hash] = base64_encode($registry->readSelectedArtifact('normal-request', $rev, $hash, "FAMtastic-Artifact $stamp:$signature", 'synthetic-reader-secret', $stamp));
    }
    $variantDna = json_decode($variant->get('design_dna')->value, TRUE);
    $privateReference = $ref->getMethod('proofContainsPrivateReference')->invoke($portal, $db->row);
    if ($privateReference) {
      $db->row['proof_share_enabled'] = 1;
      $shareSignature = $ref->getMethod('proofShareSignature')->invoke($portal, $db->row);
      if ($portal->sharedWebsiteRequest('normal-request', $shareSignature) !== NULL || $portal->publicWebsiteProofShare('normal-request', $shareSignature) !== NULL) throw new \RuntimeException('Private reference exposed through anonymous sharing');
      try { $ref->getMethod('changeWebsiteProofShare')->invoke($portal, $db->row, 'enable', 777); throw new \RuntimeException('Private reference sharing enabled'); }
      catch (\InvalidArgumentException) { $negative['public_reference_denied'] = TRUE; }
    }
    $capturedRaw = file_get_contents(dirname($tmp . '/' . $variant->get('artifact_path')->value) . '/' . $variantDna['source_capture']['raw_callback_file']);
    echo json_encode(['packet' => $packet, 'wire' => $http->wire, 'receipt_result' => $accepted, 'artifact_bytes' => $bytes, 'reader_negatives' => $negative, 'jobs' => $jobs, 'intake' => json_decode($db->row['intake_data'], TRUE), 'variant_dna' => $variantDna, 'captured_raw_callback' => $capturedRaw, 'projects_created' => $entities->projectsCreated, 'final_state' => $finalState, 'final_row' => $db->row, 'final_packet' => $finalPacket, 'final_artifact_bytes' => $finalBytes, 'final_jobs' => $ledger->jobs], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  } finally {
    $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($walk as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); } rmdir($tmp);
  }
}
