<?php
/** Executes the real portal selection seam with synthetic persistence/files. */
declare(strict_types=1);
namespace Drupal\Core\Entity { interface EntityTypeManagerInterface { public function getStorage($name); } }
namespace Drupal\Component\Datetime { interface TimeInterface { public function getRequestTime(); } }
namespace Drupal\Core\Database {
  class Connection {
    public function select(...$args) { return new class {
      public function fields(...$args) { return $this; }
      public function condition(...$args) { return $this; }
      public function orderBy(...$args) { return $this; }
      public function execute() { return $this; }
      public function fetchAll(...$args) { return [['public_id' => 'asset-1', 'kind' => 'photo', 'original_name' => 'photo.png', 'mime_type' => 'image/png', 'size_bytes' => 12, 'ownership_confirmed' => 0, 'ai_use_consent' => 1]]; }
    }; }
  }
}
namespace {
  class Drupal { public static string $dir; public static function root() { return self::$dir . '/web'; } }
  $root = dirname(__DIR__) . '/backend/web/modules/custom/famtastic_pipeline/src/Service/';
  foreach (['ProofAssetContract', 'SelectedSourceIntent', 'CustomerPortalService'] as $class) require $root . $class . '.php';
  $tmp = sys_get_temp_dir() . '/selected-intent-' . bin2hex(random_bytes(6));
  mkdir($tmp); mkdir($tmp . '/web'); mkdir($tmp . '/proofs');
  Drupal::$dir = $tmp;
  $html = '<!doctype html><html><body><h1>Selected concept</h1></body></html>';
  file_put_contents($tmp . '/proofs/index.html', $html);
  $dna = ['proof_id' => 'proof-a', 'direction_id' => 'a', 'direction_name' => 'Selected',
    'spec_snapshot' => ['site_name' => 'Synthetic', 'pages' => ['index.html']],
    'style_fingerprint' => 'original', 'font_pairing' => 'original',
    'media_fulfillment' => ['status' => 'not_needed', 'assets' => []],
    'content_verification' => ['valid' => TRUE], 'asset_manifest' => []];
  $project = new class {
    public array $studio = [];
    public function id() { return 902; }
    public function get($key) { return (object) ['value' => json_encode($this->studio)]; }
    public function set($key, $value) { $this->studio = json_decode($value, TRUE); return $this; }
    public function save() { return 1; }
  };
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
  foreach (['entities' => $entities, 'database' => new \Drupal\Core\Database\Connection(), 'time' => new class implements \Drupal\Component\Datetime\TimeInterface { public function getRequestTime() { return 1789600000; } }] as $name => $value) $ref->getProperty($name)->setValue($service, $value);
  $row = ['id' => 901, 'public_id' => 'request-1', 'project_id' => 902, 'customer_id' => 903, 'proof_campaign_id' => 904,
    'intake_data' => json_encode(['page_count' => 3, 'page_list' => 'Home, About, Contact', 'booking_details' => 'request appointments'])];
  try {
    foreach ([NULL, 'Change the contact details'] as $notes) {
      try { $ref->getMethod('createSelectedProofStaging')->invoke($service, $row, $variant, 'a', $notes); throw new \RuntimeException('Concept must not dispatch'); }
      catch (\InvalidArgumentException $e) { if (!str_starts_with($e->getMessage(), 'selected_continuation_blocked:')) throw $e; }
      $intent = $project->studio['selected_source_intent'];
      if ($intent['source']['artifacts'][0]['sha256'] !== hash('sha256', $html) || $intent['source']['design_dna'] !== $dna || $intent['operation'] !== 'plan_remaining_work') throw new \RuntimeException('Source not preserved');
      if ($intent['asset_authority']['records'][0]['ownership_confirmed'] || !$intent['asset_authority']['records'][0]['ai_use_consent'] || $intent['asset_authority']['output_bindings']) throw new \RuntimeException('Consent confused with rights');
      if ($intent['scope']['snapshot']['page_count'] !== 3 || count($intent['issues']) !== 4 || ($notes !== NULL && !$intent['requested_changes'])) throw new \RuntimeException('Scope/issues lost');
    }
    echo ($argv[1] ?? '') === '--intent' ? json_encode($intent, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : "PASS: real portal selection seam preserves source, requested scope, distinct consent, pending revisions and four stage issues (2 cases).\n";
  } finally { unlink($tmp . '/proofs/index.html'); rmdir($tmp . '/proofs'); rmdir($tmp . '/web'); rmdir($tmp); }
}
