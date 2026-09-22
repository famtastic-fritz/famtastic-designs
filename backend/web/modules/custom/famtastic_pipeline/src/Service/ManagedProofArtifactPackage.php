<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Unregistered byte packaging only. This class never creates import authority. */
class ManagedProofArtifactPackage {
  public const SCHEMA = 'famtastic.managed-proof-package.v1';
  public const MAX_PACKAGE_BYTES = 24 * 1024 * 1024;
  public const MAX_HTML_BYTES = 500000 + 694 + 1;
  private const POLICY_SHA256 = 'bb45adcade4cb87faac70ca84b14f8b1cb347395fbc8f3e14f50215d3704e914';
  private const MAX_MANIFEST_BYTES = 65536;

  /**
   * All dependencies are trusted installation inputs; defaults close all APIs.
   * Resolver must authorize the current caller/rights and load a committed exact
   * receipt. A resolver that echoes request data is NOT an implementation of it.
   * No resolver, receipt writer, DI registration or HTTP caller ships here.
   */
  public function __construct(
    private readonly ?ManagedProofArtifactStore $prepared = NULL,
    private readonly ?ManagedProofPackageFiles $storage = NULL,
    private readonly ?string $canonicalLogoFile = NULL,
    private readonly ?\Closure $resolveReceipt = NULL,
  ) {}

  /** Content facts only, outside a DB transaction. Never adopt or overwrite. */
  public function prepare(string $bundleId, string $manifestSha256): array {
    $root = $this->storage()->root();
    $id = $this->newPackageId(); self::packageId($id);
    $content = $this->content($id, $bundleId, $manifestSha256);
    $directory = $root . '/' . $id;
    if ($this->storage()->root() !== $root || !@mkdir($directory, 0700)) throw new \RuntimeException('Package already exists or cannot be created.');
    foreach ($content['files'] as $path => $bytes) $this->storage()->create($directory, $path, $bytes);
    $this->storage()->verify($directory, $content['files']);
    // Last file is a completeness marker, NOT a receipt/commit/authority marker.
    $this->storage()->create($directory, 'manifest.json', $content['wire']);
    return ['package_id' => $id, 'package_manifest_sha256' => hash('sha256', $content['wire']), 'manifest' => $content['manifest']];
  }

  /**
   * Pre-import CONTENT FACTS only, outside a DB transaction. No receipt exists
   * yet and none is minted. The caller must independently fence live authority
   * and commit a receipt later. IDs/hashes are not account or read permission.
   */
  public function verifyPreparedPackage(string $packageId, string $packageHash, string $bundleId, string $preparedHash): array {
    $content = $this->verifiedContent($packageId, $packageHash, $bundleId, $preparedHash);
    return ['package_id' => $packageId, 'package_manifest_sha256' => $packageHash, 'manifest' => $content['manifest']];
  }

  /** Only opaque server IDs and fixed roles cross this read boundary. */
  public function read(string $receiptId, string $direction, string $role, ?string $assetId = NULL): array {
    if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/', $receiptId)
      || !in_array($direction, ['a', 'b', 'c'], TRUE) || !in_array($role, ['html', 'asset', 'thumbnail', 'system_logo'], TRUE)
      || ($role === 'asset' ? !is_string($assetId) || !preg_match('/\A[a-z][a-z0-9_-]{0,63}\z/', $assetId) : $assetId !== NULL)) throw new \InvalidArgumentException('Invalid package read role or identity.');
    if ($this->resolveReceipt === NULL) throw new \RuntimeException('Managed package receipt resolver is unconfigured.');
    $binding = ($this->resolveReceipt)($receiptId, $direction, $role, $assetId);
    $keys = ['receipt_id', 'package_id', 'package_manifest_sha256', 'prepared_bundle_id', 'prepared_manifest_sha256', 'allowed_roles'];
    if (!is_array($binding) || count($binding) !== count($keys) || array_diff($keys, array_keys($binding))
      || array_filter(array_diff_key($binding, ['allowed_roles' => TRUE]), static fn($value) => !is_string($value))
      || $binding['receipt_id'] !== $receiptId || !is_array($binding['allowed_roles']) || count($binding['allowed_roles']) > 3) throw new \RuntimeException('Invalid authoritative package receipt binding.');
    foreach ($binding['allowed_roles'] as $d => $roles) {
      if (!in_array($d, ['a', 'b', 'c'], TRUE) || !is_array($roles) || !array_is_list($roles) || count($roles) > 4
        || array_filter($roles, static fn($r) => !is_string($r) || !in_array($r, ['html', 'asset', 'thumbnail', 'system_logo'], TRUE))
        || count(array_unique($roles)) !== count($roles)) throw new \RuntimeException('Invalid authoritative package role grant.');
    }
    if (!in_array($role, $binding['allowed_roles'][$direction] ?? [], TRUE)) throw new \RuntimeException('Package role is not authorized.');
    $content = $this->verifiedContent($binding['package_id'], $binding['package_manifest_sha256'], $binding['prepared_bundle_id'], $binding['prepared_manifest_sha256']);
    $variant = $content['manifest']['variants'][$direction];
    $path = $role === 'asset' ? ($variant['assets'][$assetId] ?? NULL) : $variant[$role];
    if ($path === NULL) throw new \RuntimeException('Requested package role is absent.');
    $file = $content['manifest']['files'][$path];
    // Verified bytes in memory, not a filesystem path, URL, DNA or authority.
    return ['bytes' => $content['files'][$path], 'media_type' => $file['media_type'], 'sha256' => $file['sha256'], 'size_bytes' => $file['size_bytes']];
  }

  /** Shared by pre-import facts and the strictly receipt-gated role reader. */
  private function verifiedContent(string $packageId, string $packageHash, string $bundleId, string $preparedHash): array {
    self::packageId($packageId); self::digest($packageHash);
    $root = $this->storage()->root(); $directory = $root . '/' . $packageId;
    ManagedProofPackageFiles::directory($directory);
    $wire = ManagedProofPackageFiles::read($directory . '/manifest.json', self::MAX_MANIFEST_BYTES);
    if (!hash_equals($packageHash, hash('sha256', $wire))) throw new \RuntimeException('Package manifest hash mismatch.');
    // Rebuild from independently pinned source bytes, NEVER parse stored paths.
    $content = $this->content($packageId, $bundleId, $preparedHash);
    if ($content['wire'] !== $wire) throw new \RuntimeException('Package manifest differs from regenerated source.');
    $this->storage()->verify($directory, $content['files'] + ['manifest.json' => $wire]);
    if ($this->storage()->root() !== $root) throw new \RuntimeException('Package root changed.');
    return $content;
  }

  private function content(string $id, string $bundleId, string $manifestSha256): array {
    if ($this->prepared === NULL || $this->canonicalLogoFile === NULL) throw new \RuntimeException('Managed package preparation is unconfigured.');
    $source = $this->prepared->verifyPrepared($bundleId, $manifestSha256);
    $policy = SelectedCreatorCreditProjection::policy(); $logoPolicy = $policy['system_asset'];
    if (hash('sha256', json_encode($policy, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) !== self::POLICY_SHA256) throw new \RuntimeException('Package projection policy changed.');
    $logo = ManagedProofPackageFiles::read($this->canonicalLogoFile, $logoPolicy['bytes'], FALSE);
    if (strlen($logo) !== $logoPolicy['bytes'] || hash('sha256', $logo) !== $logoPolicy['sha256']) throw new \RuntimeException('Canonical package logo differs from pinned policy.');
    $files = []; $inventory = []; $variants = [];
    $add = static function(string $path, string $role, string $mediaType, string $bytes) use (&$files, &$inventory): void {
      foreach (array_keys($files) as $existing) {
        $a = strtolower($path); $b = strtolower($existing);
        if ($a === $b || str_starts_with($a, $b . '/') || str_starts_with($b, $a . '/')) throw new \InvalidArgumentException('Package asset collides with a reserved or existing path.');
      }
      $files[$path] = $bytes;
      $inventory[$path] = ['role' => $role, 'media_type' => $mediaType, 'sha256' => hash('sha256', $bytes), 'size_bytes' => strlen($bytes)];
    };
    foreach (['a', 'b', 'c'] as $direction) {
      $input = $source['normalized'][$direction]; $original = $input['html'];
      $projection = SelectedCreatorCreditProjection::project($original, ['path' => $direction . '/index.html', 'sha256' => hash('sha256', $original), 'bytes' => strlen($original)]);
      $derived = SelectedCreatorCreditProjection::derive($original);
      if (strlen($original) > 500000 || strlen($derived) > self::MAX_HTML_BYTES) throw new \RuntimeException('Credited package HTML exceeds its exact bound.');
      $home = $direction . '/index.html'; $logoPath = $direction . '/' . SelectedCreatorCreditProjection::ASSET_PATH;
      $add($home, 'html', 'text/html; charset=UTF-8', $derived);
      $add($logoPath, 'system_logo', 'image/png', $logo);
      SelectedCreatorCreditProjection::assertHomeAndLogo([
        ['path' => 'index.html', 'sha256' => hash('sha256', $derived), 'bytes' => strlen($derived)],
        ['path' => SelectedCreatorCreditProjection::ASSET_PATH, 'sha256' => hash('sha256', $logo), 'bytes' => strlen($logo)],
      ], $projection);
      $assets = [];
      foreach ($input['assets'] as $asset) {
        $path = $direction . '/assets/' . $asset['relative_path'];
        $add($path, 'asset', $asset['media_type'], $asset['bytes']); $assets[$asset['asset_id']] = $path;
      }
      $thumbnail = NULL;
      if ($input['thumbnail'] !== NULL) {
        $thumbnail = $direction . '/thumbnail.' . $input['thumbnail_extension'];
        $add($thumbnail, 'thumbnail', $input['thumbnail_extension'] === 'png' ? 'image/png' : 'image/jpeg', $input['thumbnail']);
      }
      $variants[$direction] = ['projection' => $projection, 'html' => $home, 'system_logo' => $logoPath, 'assets' => $assets, 'thumbnail' => $thumbnail];
    }
    ksort($files); ksort($inventory);
    $manifest = ['schema' => self::SCHEMA, 'package_id' => $id, 'status' => 'private_package_only', 'deliverable' => FALSE,
      'source' => ['bundle_id' => $bundleId, 'manifest_sha256' => $manifestSha256], 'variants' => $variants, 'files' => $inventory];
    $wire = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    if (strlen($wire) > self::MAX_MANIFEST_BYTES || array_sum(array_map('strlen', $files)) + strlen($wire) > self::MAX_PACKAGE_BYTES) throw new \RuntimeException('Package exceeds bounded storage.');
    return ['files' => $files, 'manifest' => $manifest, 'wire' => $wire];
  }

  private function storage(): ManagedProofPackageFiles {
    return $this->storage ?? throw new \RuntimeException('Managed package storage is unconfigured.');
  }
  private static function packageId(string $id): void {
    if (!preg_match('/\Amp-[a-f0-9]{32}\z/', $id)) throw new \InvalidArgumentException('Invalid server package identity.');
  }
  private static function digest(string $hash): void {
    if (!preg_match('/\A[a-f0-9]{64}\z/', $hash)) throw new \InvalidArgumentException('Invalid package manifest digest.');
  }
  protected function newPackageId(): string { return 'mp-' . bin2hex(random_bytes(16)); }
}
