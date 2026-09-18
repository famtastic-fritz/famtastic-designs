<?php
/** Executes the real portal selection seam with synthetic persistence/files. */
declare(strict_types=1);
namespace Drupal\Core\Entity { interface EntityTypeManagerInterface { public function getStorage($name); } }
namespace Drupal\Component\Datetime { interface TimeInterface { public function getRequestTime(); } }
namespace Drupal\Core\Config { interface ConfigFactoryInterface { public function get($name); } }
namespace Drupal\famtastic_pipeline\Entity {
  class Project {
    public array $studio = [];
    public function id() { return 902; }
    public function get($key) { return (object) ['value' => json_encode($this->studio)]; }
    public function set($key, $value) { if ($key === 'studio_json') $this->studio = json_decode($value, TRUE); return $this; }
    public function save() { return 1; }
  }
}
namespace Drupal\famtastic_pipeline\Service { class OperationalLedger { public array $jobs = []; public function recordEvent(...$args) { return TRUE; } public function enqueue($key, $type, $payload, ...$args) { $this->jobs[$key] ??= ['type' => $type, 'payload' => $payload]; return count($this->jobs); } } }
namespace GuzzleHttp { interface ClientInterface {} }
namespace Drupal\Core\Database {
  class Connection {
    public array $row = [];
    public function update(...$args) { return $this->select(...$args); }
    public function startTransaction() { return new class { public function rollBack() {} }; }
    public function select(...$args) { return new class($this) {
      public function __construct(private Connection $db) {}
      public function fields(...$args) { return $this; }
      public function condition(...$args) { return $this; }
      public function orderBy(...$args) { return $this; }
      public function range(...$args) { return $this; }
      public function forUpdate() { return $this; }
      public function execute() { return $this; }
      public function fetchAssoc() { return $this->db->row + ['display_name' => 'Synthetic customer', 'email' => 'synthetic@example.test']; }
      public function fetchAll(...$args) { return [['public_id' => 'asset-1', 'kind' => 'photo', 'original_name' => 'photo.png', 'mime_type' => 'image/png', 'size_bytes' => 12, 'ownership_confirmed' => 0, 'ai_use_consent' => 1]]; }
    }; }
  }
}
namespace {
  class Drupal { public static string $dir; public static function root() { return self::$dir . '/web'; } }
  $root = dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src/Service/';
  foreach (['ProofAssetContract', 'SelectedAssetRights', 'SelectedSourceIntent', 'SelectedFinalizedSource', 'SelectedStagingContinuation', 'SelectedPlanningPacket', 'SiteStudioBuildPacketService', 'CustomerPortalService', 'SiteStudioStagingClient', 'AutomationWorker'] as $class) require $root . $class . '.php';
  if (!function_exists('mb_strtolower')) { function mb_strtolower($text) { return strtolower($text); } }
  if (!function_exists('mb_substr')) { function mb_substr($text, $start, $length) { return substr($text, $start, $length); } }
  $input = ($argv[1] ?? '') === '--export-packet' ? json_decode(stream_get_contents(STDIN), TRUE, 512, JSON_THROW_ON_ERROR) : NULL;
  $tmp = sys_get_temp_dir() . '/selected-intent-' . bin2hex(random_bytes(6));
  mkdir($tmp); mkdir($tmp . '/web'); mkdir($tmp . '/proofs');
  Drupal::$dir = $tmp;
  $html = $input ? base64_decode($input['html_base64'], TRUE) : '<!doctype html><html><body><h1>Selected concept</h1></body></html>';
  file_put_contents($tmp . '/proofs/index.html', $html);
  $dna = ['proof_id' => 'proof-a', 'direction_id' => 'a', 'direction_name' => 'Selected',
    'spec_snapshot' => ['site_name' => 'Synthetic Café 雪', 'pages' => ['index.html'], 'small_numeric' => 1e-7, 'large_numeric' => 1e30, 'empty_list' => []],
    'style_fingerprint' => 'original', 'font_pairing' => 'original',
    'media_fulfillment' => ['status' => 'not_needed', 'assets' => []],
    'content_verification' => ['valid' => TRUE], 'asset_manifest' => []];
  $project = new \Drupal\famtastic_pipeline\Entity\Project();
  $variant = new class($dna) {
    public function __construct(private array $dna) {}
    public function id() { return 905; }
    public function get($key) { return (object) ['value' => match ($key) { 'artifact_path' => 'proofs/index.html', 'design_dna' => json_encode($this->dna), default => '' }]; }
  };
  $entities = new class($project) implements \Drupal\Core\Entity\EntityTypeManagerInterface {
    public function __construct(private object $project) {}
    public function getStorage($name) { return new class($this->project) { public function __construct(private object $project) {} public function load($id) { return $this->project; } }; }
  };
  $ref = new \ReflectionClass(\Drupal\famtastic_pipeline\Service\CustomerPortalService::class);
  $service = $ref->newInstanceWithoutConstructor();
  $db = new \Drupal\Core\Database\Connection();
  $clock = new class implements \Drupal\Component\Datetime\TimeInterface { public function getRequestTime() { return 1789600000; } };
  foreach (['entities' => $entities, 'database' => $db, 'time' => $clock] as $name => $value) $ref->getProperty($name)->setValue($service, $value);
  $row = ['id' => 901, 'public_id' => 'request-1', 'project_id' => 902, 'customer_id' => 903, 'proof_campaign_id' => 904, 'prospect_id' => 906,
    'intake_data' => json_encode(['page_count' => 3, 'page_list' => 'Home, About, Contact', 'booking_details' => 'request appointments', 'products_services' => ['small' => 1e-7, 'large' => 1e30, 'label' => 'Café 雪']])];
  $db->row = $row;
  $ledger = new \Drupal\famtastic_pipeline\Service\OperationalLedger();
  $config = new class implements \Drupal\Core\Config\ConfigFactoryInterface { public function get($name) { return new class { public function get($key) { return 'https://agency.example.test'; } }; } };
  $registry = new \Drupal\famtastic_pipeline\Service\SiteStudioBuildPacketService($db, $entities, $ledger, $clock, $config);
  foreach (['siteStudioPackets' => $registry, 'ledger' => $ledger] as $name => $value) $ref->getProperty($name)->setValue($service, $value);
  try {
    if ($input) {
      $row['intake_data'] = json_encode(['page_count' => 1, 'page_list' => 'Home']);
      $export = $input['source_export'];
      $authority = $input['authority'];
      $project->studio['selected_source_authority'] = $authority;
      $db->row = $row;
      $envelope = ['project_id' => '902', 'customer_id' => '903', 'request_id' => 'request-1', 'source_export' => $export];
      if (!$registry->registerSourceExport($envelope)['newly_processed'] || $registry->registerSourceExport($envelope)['newly_processed']) throw new \RuntimeException('Source export registration not idempotent');
      $ref->getMethod('createSelectedProofStaging')->invoke($service, $row, $variant, 'a', NULL);
      echo json_encode(['packet' => $project->studio['selected_dispatch_packet'], 'stored_source_export' => $project->studio['next_source_export']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
      return;
    }
    foreach ([NULL, 'Change the contact details', 'Change the contact details'] as $index => $notes) {
      $selection = $ref->getMethod('createSelectedProofStaging')->invoke($service, $row, $variant, 'a', $notes);
      if ($selection['status'] !== 'planning_queued') throw new \RuntimeException('Concept must take planning path.');
      $intent = $project->studio['selected_source_intent'];
      if ($intent['source']['artifacts'][0]['sha256'] !== hash('sha256', $html) || $intent['source']['design_dna'] !== $dna || $intent['operation'] !== 'plan_remaining_work') throw new \RuntimeException('Source not preserved');
      if ($intent['asset_authority']['records'][0]['ownership_confirmed'] || !$intent['asset_authority']['records'][0]['ai_use_consent'] || $intent['asset_authority']['output_bindings']) throw new \RuntimeException('Consent confused with rights');
      if ($intent['scope']['snapshot']['page_count'] !== 3 || count($intent['issues']) !== 4 || ($notes !== NULL && !$intent['requested_changes'])) throw new \RuntimeException('Scope/issues lost');
      if ($intent['selection']['revision'] !== ($index === 0 ? 1 : 2) || count($project->studio['selected_source_intent_history'] ?? []) !== ($index === 0 ? 0 : 1)) throw new \RuntimeException('Intent revision/history not immutable or retry-idempotent');
      if (count($ledger->jobs) !== ($index === 0 ? 1 : 2) || isset($project->studio['site_studio_build_packet'])) throw new \RuntimeException('One planning dispatch per revision required.');
    }
    if (($argv[1] ?? '') === '--planning-result') {
      $result = json_decode(stream_get_contents(STDIN), TRUE, 512, JSON_THROW_ON_ERROR);
      $accepted = $registry->acceptPlanningResult($result);
      $duplicate = $registry->acceptPlanningResult($result);
      echo json_encode(['accepted' => $accepted, 'duplicate' => $duplicate, 'planning' => $project->studio['selected_source_plan'], 'build_packet' => isset($project->studio['site_studio_build_packet'])]);
      return;
    }
    if (($argv[1] ?? '') === '--planning-dispatch') {
      putenv('SITE_STUDIO_STAGING_URL=https://studio.example.test/api/pipeline/staging/accept');
      putenv('FAMTASTIC_STUDIO_DISPATCH_SECRET=synthetic-planning-secret');
      $http = new class implements \GuzzleHttp\ClientInterface {
        public array $wire = [];
        public function request($method, $url, $options) {
          $this->wire = ['method' => $method, 'url' => $url] + $options;
          $p = json_decode($options['body'], TRUE)['packet'];
          return new class($p) {
            public function __construct(private array $packet) {}
            public function getStatusCode() { return 202; }
            public function getBody() { return json_encode(['accepted' => TRUE, 'status' => 'accepted_waiting_callback', 'receipt' => ['receipt_id' => 'synthetic-receipt', 'packet_id' => $this->packet['packet_id'], 'idempotency_key' => $this->packet['idempotency_key']]]); }
          };
        }
      };
      $workerRef = new \ReflectionClass(\Drupal\famtastic_pipeline\Service\AutomationWorker::class);
      $worker = $workerRef->newInstanceWithoutConstructor();
      $workerRef->getProperty('portal')->setValue($worker, $service);
      $workerRef->getProperty('stagingClient')->setValue($worker, new \Drupal\famtastic_pipeline\Service\SiteStudioStagingClient($http, $config));
      $jobs = array_values($ledger->jobs);
      try { $workerRef->getMethod('prepareSiteStudioStaging')->invoke($worker, $jobs[0]); throw new \RuntimeException('Stale planning dispatch was accepted.'); }
      catch (\InvalidArgumentException $error) { if (!str_contains($error->getMessage(), 'superseded')) throw $error; }
      $workerRef->getMethod('prepareSiteStudioStaging')->invoke($worker, $jobs[1]);
      echo json_encode(['wire' => $http->wire, 'jobs' => $jobs]); return;
    }
    echo ($argv[1] ?? '') === '--intent' ? json_encode($intent, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : "PASS: real portal selection seam preserves source, requested scope, distinct consent, pending revisions, immutable history and retry identity (3 cases).\n";
  } finally { unlink($tmp . '/proofs/index.html'); rmdir($tmp . '/proofs'); rmdir($tmp . '/web'); rmdir($tmp); }
}
