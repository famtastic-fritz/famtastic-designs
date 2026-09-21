<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/**
 * Unregistered private preparation only. No claim/import/QA/producer authority.
 *
 * Roots are installation inputs, never worker data. The private root must be
 * exclusively controlled by this OS principal; this is not hostile-host isolation.
 */
class ManagedProofArtifactStore {
  public const INPUT_SCHEMA = 'famtastic.managed-proof-artifacts.v1';
  public const MAX_PREPARED_BYTES = 40 * 1024 * 1024;
  private const DIRECTIONS = ['a' => 'Safe', 'b' => 'Wild', 'c' => 'OMG'];

  public function __construct(private readonly ?string $privateRoot = NULL, private readonly ?string $documentRoot = NULL) {}

  /**
   * Prepare a new bundle, never reuse/adopt a prior directory, even on retry.
   * Expected input is the caller's pure-validator result, NOT an authority grant.
   * Every failure leaves any partial files private and unreferenced for review.
   */
  public function prepare(string $rawCallback, array $expected): array {
    $root = $this->root();
    if ($rawCallback === '' || strlen($rawCallback) > ProofAssetContract::MAX_CALLBACK_BYTES) throw new \InvalidArgumentException('Managed artifact wire exceeds the callback bound.');
    $object = json_decode($rawCallback, FALSE, 16, JSON_THROW_ON_ERROR);
    if (!$object instanceof \stdClass || self::wire($object) !== $rawCallback) throw new \InvalidArgumentException('Managed artifact wire must be canonical JSON without duplicate keys.');
    $input = json_decode($rawCallback, TRUE, 16, JSON_THROW_ON_ERROR);
    self::keys($input, ['schema', 'event_id', 'campaign_id', 'job_id', 'variants']);
    if ($input['schema'] !== self::INPUT_SCHEMA) throw new \InvalidArgumentException('Unsupported managed artifact schema.');
    foreach (['event_id', 'job_id'] as $key) {
      if (!is_string($input[$key]) || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,254}\z/', $input[$key])) throw new \InvalidArgumentException('Invalid managed artifact correlation identity.');
    }
    if (!is_string($input['campaign_id']) || !preg_match('/\Apc-[a-z0-9-]{1,124}\z/', $input['campaign_id'])) throw new \InvalidArgumentException('Invalid managed artifact campaign.');
    if (!is_array($input['variants']) || !array_is_list($input['variants']) || count($input['variants']) !== 3) throw new \InvalidArgumentException('Managed variants must be a three-item list.');
    foreach ($input['variants'] as $variant) {
      if (!is_array($variant)) throw new \InvalidArgumentException('Managed variant must be an object.');
      self::keys($variant, ['direction_id', 'html', 'design_dna'], ['assets', 'thumbnail_base64', 'thumbnail_media_type']);
      if (!is_string($variant['direction_id']) || !isset(self::DIRECTIONS[$variant['direction_id']]) || !is_string($variant['html'])
        || !is_array($variant['design_dna']) || strlen(self::wire($variant['design_dna'])) > 32768) throw new \InvalidArgumentException('Invalid managed variant shape or DNA bound.');
      $nodes = 0;
      self::dna($variant['design_dna'], $nodes);
      foreach (['thumbnail_base64', 'thumbnail_media_type'] as $key) {
        if (isset($variant[$key]) && !is_string($variant[$key])) throw new \InvalidArgumentException('Invalid managed thumbnail shape.');
      }
      if (isset($variant['assets'])) {
        if (!is_array($variant['assets']) || !array_is_list($variant['assets']) || count($variant['assets']) > ProofAssetContract::MAX_ASSETS_PER_VARIANT) throw new \InvalidArgumentException('Managed assets must be a bounded list.');
        foreach ($variant['assets'] as $asset) {
          if (!is_array($asset)) throw new \InvalidArgumentException('Managed asset must be an object.');
          self::keys($asset, ['asset_id', 'relative_path', 'media_type', 'base64', 'sha256']);
          foreach ($asset as $value) if (!is_string($value)) throw new \InvalidArgumentException('Managed asset fields must be strings.');
        }
      }
    }
    $normalized = ProofCallbackArtifacts::normalize($input['variants'], self::DIRECTIONS);
    if ($normalized !== $expected) throw new \InvalidArgumentException('Raw callback differs from normalized artifacts.');

    // Construct the complete bounded inventory before the first filesystem write.
    $files = ['callback.json' => ['role' => 'raw_callback', 'bytes' => $rawCallback]];
    $variants = [];
    foreach ($normalized as $direction => $variant) {
      $files[$direction . '/index.html'] = ['role' => 'html', 'bytes' => $variant['html']];
      $files[$direction . '/design-dna.json'] = ['role' => 'direction_dna', 'bytes' => self::wire($variant['design_dna'])];
      $assets = [];
      foreach ($variant['assets'] as $asset) {
        $path = $direction . '/assets/' . $asset['relative_path'];
        $files[$path] = ['role' => 'asset', 'bytes' => $asset['bytes']];
        unset($asset['bytes']);
        $assets[] = $asset + ['path' => $path];
      }
      $thumbnail = NULL;
      if ($variant['thumbnail'] !== NULL) {
        $thumbnail = $direction . '/thumbnail.' . $variant['thumbnail_extension'];
        $files[$thumbnail] = ['role' => 'thumbnail', 'bytes' => $variant['thumbnail']];
      }
      $variants[$direction] = ['html' => $direction . '/index.html', 'design_dna' => $direction . '/design-dna.json', 'assets' => $assets, 'thumbnail' => $thumbnail];
    }
    ksort($files);
    $manifest = ['schema' => 'famtastic.prepared-proof-artifacts.v1', 'status' => 'private_preparation_only', 'deliverable' => FALSE,
      'correlation' => array_intersect_key($input, array_flip(['event_id', 'campaign_id', 'job_id'])), 'variants' => $variants, 'files' => []];
    $total = 0;
    foreach ($files as $path => $file) {
      $size = strlen($file['bytes']); $total += $size;
      $manifest['files'][] = ['path' => $path, 'role' => $file['role'], 'sha256' => hash('sha256', $file['bytes']), 'size_bytes' => $size];
    }
    $wire = self::wire($manifest);
    if ($total + strlen($wire) > self::MAX_PREPARED_BYTES) throw new \InvalidArgumentException('Managed preparation exceeds its storage bound.');
    $id = $this->newBundleId();
    if (!preg_match('/\A[a-f0-9]{32}\z/', $id)) throw new \RuntimeException('Invalid server preparation identity.');
    $directory = $root . '/' . $id;
    if ($this->root() !== $root || !@mkdir($directory, 0700)) throw new \RuntimeException('Preparation directory already exists or cannot be created.');
    foreach ($files as $path => $file) $this->createFile($directory, $path, $file['bytes']);
    // Recheck all bytes after all writes, before publishing the completion marker.
    foreach ($manifest['files'] as $file) $this->verifyFile($directory . '/' . $file['path'], $file['size_bytes'], $file['sha256']);
    $this->createFile($directory, 'manifest.json', $wire);
    return ['directory' => $directory, 'manifest' => $manifest, 'manifest_sha256' => hash('sha256', $wire), 'manifest_size_bytes' => strlen($wire)];
  }

  private static function wire(mixed $data): string {
    return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
  }

  private static function keys(array $data, array $required, array $optional = []): void {
    if (array_diff($required, array_keys($data)) || array_diff(array_keys($data), [...$required, ...$optional])) throw new \InvalidArgumentException('Managed artifact object has missing or unknown fields.');
  }

  /** Bounded descriptive JSON, not upstream storage/continuation/secret authority. */
  private static function dna(mixed $value, int &$nodes, int $depth = 0): void {
    if (++$nodes > 512 || $depth > 8 || (is_string($value) && strlen($value) > 4096)) throw new \InvalidArgumentException('Managed direction DNA exceeds its structural bound.');
    if (!is_array($value)) return;
    foreach ($value as $key => $child) {
      if (is_string($key) && (!preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,63}\z/', $key)
        || preg_match('/token|secret|password|credential|authorization|cookie|session|api_?key|private_?key/i', $key)
        || in_array($key, ['source_capture', 'asset_manifest', 'selected_build_artifacts', 'selected_build_continuation'], TRUE))) throw new \InvalidArgumentException('Managed direction DNA contains a prohibited field.');
      self::dna($child, $nodes, $depth + 1);
    }
  }

  private function root(): string {
    if ($this->privateRoot === NULL || $this->documentRoot === NULL) throw new \RuntimeException('Managed artifact storage is unconfigured.');
    foreach ([$this->privateRoot, $this->documentRoot] as $path) {
      if ($path === '/' || !str_starts_with($path, '/') || realpath($path) !== $path || !is_dir($path)) throw new \RuntimeException('Artifact roots require existing canonical absolute directories.');
      self::noLinks($path);
    }
    if ($this->privateRoot === $this->documentRoot || str_starts_with($this->privateRoot, $this->documentRoot . '/')
      || str_starts_with($this->documentRoot, $this->privateRoot . '/')) throw new \RuntimeException('Private artifact root must be disjoint from the document root.');
    $mode = fileperms($this->privateRoot);
    if ($mode === FALSE || ($mode & 0077) !== 0 || !is_writable($this->privateRoot)
      || (function_exists('posix_geteuid') && fileowner($this->privateRoot) !== posix_geteuid())) throw new \RuntimeException('Private artifact root must be owner-only and writable.');
    return $this->privateRoot;
  }

  private static function noLinks(string $path): void {
    $cursor = '';
    foreach (explode('/', ltrim($path, '/')) as $part) {
      $cursor .= '/' . $part;
      clearstatcache(TRUE, $cursor);
      if (is_link($cursor)) throw new \RuntimeException('Symbolic links are forbidden in artifact storage.');
    }
  }

  private function createFile(string $directory, string $relative, string $bytes): void {
    $root = $this->root();
    if (dirname($directory) !== $root) throw new \RuntimeException('Preparation escaped its private root.');
    $parent = $directory;
    foreach (explode('/', dirname($relative)) as $part) {
      if ($part === '.') continue;
      $parent .= '/' . $part;
      self::noLinks($parent);
      if (!is_dir($parent) && !@mkdir($parent, 0700)) throw new \RuntimeException('Cannot create private artifact directory.');
    }
    self::noLinks($parent);
    if (realpath($parent) !== $parent || !str_starts_with($parent . '/', $directory . '/')) throw new \RuntimeException('Unsafe artifact parent directory.');
    $path = $directory . '/' . $relative;
    $handle = @fopen($path, 'xb');
    if ($handle === FALSE) throw new \RuntimeException('Artifact collision or exclusive write failure.');
    try {
      if (!chmod($path, 0600) || $this->writeBytes($handle, $bytes) !== strlen($bytes) || !fflush($handle)
        || !fsync($handle)) throw new \RuntimeException('Incomplete artifact write.');
      if (!chmod($path, 0400)) throw new \RuntimeException('Cannot seal private artifact.');
    }
    finally { fclose($handle); }
    $this->verifyFile($path, strlen($bytes), hash('sha256', $bytes));
  }

  private function verifyFile(string $path, int $size, string $hash): void {
    self::noLinks($path);
    clearstatcache(TRUE, $path);
    if (!is_file($path) || filesize($path) !== $size || hash_file('sha256', $path) !== $hash) throw new \RuntimeException('Prepared artifact size or hash mismatch.');
  }

  /** Narrow filesystem fault seams for offline tests, not worker parameters. */
  protected function newBundleId(): string { return bin2hex(random_bytes(16)); }
  protected function writeBytes(mixed $handle, string $bytes): int|false { return fwrite($handle, $bytes); }
}
