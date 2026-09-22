<?php
declare(strict_types=1);
require_once __DIR__ . '/../worker-mariadb/bootstrap.php';

function reviewLineage(): array {
  $lineage = json_decode(file_get_contents(__DIR__ . '/lineage.json'), TRUE, flags: JSON_THROW_ON_ERROR);
  foreach ($lineage['files'] as $file => $hash) {
    proofNeed(!is_link(PROOF_ROOT . '/' . $file) && hash_file('sha256', PROOF_ROOT . '/' . $file) === $hash, 'review_source_changed:' . $file);
  }
  $file = __DIR__ . '/' . $lineage['baseline_fixture'];
  proofNeed(!is_link($file) && filesize($file) === $lineage['baseline_bytes'] && filesize($file) < 32768
    && hash_file('sha256', $file) === $lineage['baseline_sha256'], 'review_baseline_changed');
  return $lineage;
}

function reviewLoad(string $mode): void {
  proofNeed(in_array($mode, ['current', 'baseline'], TRUE), 'invalid_review_mode');
  $lineage = reviewLineage();
  // The old worker ownership marker and frozen worker source stay unchanged.
  // Only FullSiteReviewService is replaced in the explicit negative process.
  proofLoad('current');
  $vendor = getenv('FAMTASTIC_BACKEND_VENDOR');
  $loader = require $vendor . '/autoload.php';
  foreach (['file', 'user'] as $module) $loader->addPsr4('Drupal\\' . $module . '\\', dirname($vendor) . '/web/core/modules/' . $module . '/src', TRUE);
  if ($mode === 'baseline') {
    proofNeed(!class_exists(Drupal\famtastic_pipeline\Service\FullSiteReviewService::class, FALSE), 'review_service_already_loaded');
    require __DIR__ . '/' . $lineage['baseline_fixture'];
  }
  new Drupal\Core\Site\Settings([]); // No admission, providers or installed settings.
}

/** Tiny create-only fixtures/barriers beneath the provisioner's private root. */
final class ReviewFiles {
  public readonly string $root;
  public function __construct(string $configPath, string $hint, bool $create = FALSE) {
    proofNeed(preg_match('/^[a-f0-9]{24}\.[a-z-]{3,40}$/', $hint) === 1, 'invalid_review_run');
    $parent = dirname(realpath($configPath));
    $path = $parent . '/private-review-' . $hint;
    proofNeed(!is_link($path), 'review_root_link');
    if ($create) proofNeed(!file_exists($path) && mkdir($path, 0700), 'review_root_not_create_only');
    $real = realpath($path);
    proofNeed($real !== FALSE && dirname($real) === $parent && is_dir($real)
      && fileowner($real) === posix_geteuid() && (fileperms($real) & 0077) === 0, 'review_root_not_private');
    $this->root = $real;
    if ($create) {
      foreach (['private', 'source', 'barriers'] as $dir) proofNeed(mkdir($this->path($dir), 0700), 'fixture_mkdir_failed');
      $this->write('source/index.html', '<!doctype html><html><body><h1>Synthetic private review only</h1></body></html>');
      $this->write('upload.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jN1sAAAAASUVORK5CYII=', TRUE));
    }
  }
  public function path(string $relative): string {
    proofNeed(strlen($relative) < 240 && preg_match('~^[a-zA-Z0-9._-]+(?:/[a-zA-Z0-9._-]+){0,4}$~', $relative) === 1, 'invalid_fixture_path');
    $path = $this->root;
    foreach (explode('/', $relative) as $part) {
      proofNeed(!in_array($part, ['.', '..'], TRUE), 'fixture_path_traversal');
      $path .= '/' . $part; proofNeed(!is_link($path), 'fixture_path_link');
    }
    return $path;
  }
  public function write(string $relative, string $bytes): void {
    proofNeed(strlen($bytes) <= 2048, 'fixture_too_large');
    $handle = fopen($this->path($relative), 'xb');
    proofNeed($handle !== FALSE, 'fixture_not_create_only');
    try { proofNeed(fwrite($handle, $bytes) === strlen($bytes) && fflush($handle), 'fixture_write_failed'); }
    finally { fclose($handle); }
    proofNeed(chmod($this->path($relative), 0600), 'fixture_mode_failed');
  }
  public function has(string $name): bool { clearstatcache(); return is_file($this->path('barriers/' . $name)); }
  public function mark(string $name): void { if (!$this->has($name)) $this->write('barriers/' . $name, "1\n"); }
  public function manifest(int $version = 1): array {
    proofNeed(in_array($version, [1, 2], TRUE), 'invalid_fixture_version');
    $bytes = file_get_contents($this->path('source/index.html'));
    proofNeed(is_string($bytes) && strlen($bytes) < 1024, 'invalid_source_fixture');
    return ['schema' => 'famtastic.full-site-review.v1', 'review_id' => 'synthetic-version-' . $version,
      'title' => 'Synthetic only ' . $version, 'entry_path' => 'index.html', 'source_commit' => str_repeat('a', 40), 'build_id' => 'synthetic-private-review',
      'pages' => [['label' => 'Home', 'path' => 'index.html']], 'documents' => [],
      'files' => [['path' => 'index.html', 'role' => 'page', 'media_type' => 'text/html', 'sha256' => hash('sha256', $bytes), 'bytes' => strlen($bytes)]],
      'research' => ['overview' => 'Synthetic concurrency fixture, not creative or delivery proof.', 'sources' => []]];
  }
}
