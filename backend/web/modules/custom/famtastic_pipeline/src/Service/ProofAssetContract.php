<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

/**
 * Normalizes the bounded binary asset contract carried beside proof HTML.
 *
 * Proof pages remain script-free and the bytes are never exposed directly
 * from the filesystem.  This class deliberately supports only image formats
 * that can be safely rendered by the signed proof reader.
 */
final class ProofAssetContract {

  /** One rendered source asset may be at most 1.5 MB. */
  public const MAX_ASSET_BYTES = 1500000;

  /** A direction may carry a small, intentional image set rather than a dump. */
  public const MAX_ASSETS_PER_VARIANT = 4;

  /** Keeps one three-direction callback within the signed transport budget. */
  public const MAX_ASSET_BYTES_PER_VARIANT = 3000000;

  /** Includes base64 expansion, HTML, and one thumbnail for each direction. */
  public const MAX_CALLBACK_BYTES = 24 * 1024 * 1024;

  /**
   * Decodes and validates callback assets.
   *
   * Canonical wire shape:
   *
   * @code
   * [{
   *   "asset_id": "hero",
   *   "relative_path": "hero.webp",
   *   "media_type": "image/webp",
   *   "base64": "...",
   *   "sha256": "..."
   * }]
   * @endcode
   *
   * `id` and `path` remain accepted as narrow compatibility aliases for a
   * callback produced before this contract name was settled. They are emitted
   * only under the canonical names.
   *
   * @return list<array{asset_id:string,relative_path:string,media_type:string,bytes:string,sha256:string,size_bytes:int}>
   */
  public static function normalizeCallbackAssets(mixed $assets): array {
    if ($assets === NULL) {
      return [];
    }
    if (!is_array($assets) || !array_is_list($assets)) {
      throw new \InvalidArgumentException('Proof assets must be a list.');
    }
    if (count($assets) > self::MAX_ASSETS_PER_VARIANT) {
      throw new \InvalidArgumentException('A proof direction exceeds the maximum number of supported assets.');
    }

    $normal = [];
    $seenIds = [];
    $seenPaths = [];
    $total = 0;
    foreach ($assets as $asset) {
      if (!is_array($asset)) {
        throw new \InvalidArgumentException('Each proof asset must be an object.');
      }
      $id = trim((string) ($asset['asset_id'] ?? $asset['id'] ?? ''));
      $relativePath = self::normalizeRelativePath((string) ($asset['relative_path'] ?? $asset['path'] ?? ''));
      $mediaType = strtolower(trim((string) ($asset['media_type'] ?? '')));
      $sha256 = strtolower(trim((string) ($asset['sha256'] ?? '')));
      $base64 = (string) ($asset['base64'] ?? $asset['data_base64'] ?? '');

      if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $id) !== 1) {
        throw new \InvalidArgumentException('Proof asset_id must use lowercase letters, digits, hyphens, or underscores.');
      }
      if (isset($seenIds[$id]) || isset($seenPaths[$relativePath])) {
        throw new \InvalidArgumentException('Proof asset identifiers and relative paths must be unique within a direction.');
      }
      self::assertMediaTypeForPath($mediaType, $relativePath);
      if (preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
        throw new \InvalidArgumentException('Each proof asset requires a lowercase SHA-256 hash.');
      }
      if ($base64 === '' || str_contains($base64, 'data:') || strlen($base64) > self::maxEncodedBytes()) {
        throw new \InvalidArgumentException('Proof asset base64 is missing or exceeds the allowed size.');
      }
      $bytes = base64_decode($base64, TRUE);
      if ($bytes === FALSE || $bytes === '' || strlen($bytes) > self::MAX_ASSET_BYTES) {
        throw new \InvalidArgumentException('Proof asset bytes are invalid or exceed the allowed size.');
      }
      if (!hash_equals($sha256, hash('sha256', $bytes))) {
        throw new \InvalidArgumentException('Proof asset SHA-256 does not match its decoded bytes.');
      }
      if (!self::matchesMagic($mediaType, $bytes)) {
        throw new \InvalidArgumentException('Proof asset bytes do not match the declared media type.');
      }
      $total += strlen($bytes);
      if ($total > self::MAX_ASSET_BYTES_PER_VARIANT) {
        throw new \InvalidArgumentException('Proof direction assets exceed the allowed combined size.');
      }
      $seenIds[$id] = TRUE;
      $seenPaths[$relativePath] = TRUE;
      $normal[] = [
        'asset_id' => $id,
        'relative_path' => $relativePath,
        'media_type' => $mediaType,
        'bytes' => $bytes,
        'sha256' => $sha256,
        'size_bytes' => strlen($bytes),
      ];
    }

    usort($normal, static fn (array $left, array $right): int => strcmp($left['relative_path'], $right['relative_path']));
    return $normal;
  }

  /**
   * Validates the persisted, byte-free asset manifest recorded in Design DNA.
   *
   * @return list<array{asset_id:string,relative_path:string,media_type:string,sha256:string,size_bytes:int,artifact_path:string}>
   */
  public static function normalizeStoredManifest(mixed $assets): array {
    if ($assets === NULL || $assets === '') {
      return [];
    }
    if (!is_array($assets) || !array_is_list($assets) || count($assets) > self::MAX_ASSETS_PER_VARIANT) {
      throw new \InvalidArgumentException('Stored proof asset manifest is invalid.');
    }
    $normal = [];
    $seenIds = [];
    $seenPaths = [];
    $total = 0;
    foreach ($assets as $asset) {
      if (!is_array($asset)) {
        throw new \InvalidArgumentException('Stored proof asset manifest contains an invalid entry.');
      }
      $id = trim((string) ($asset['asset_id'] ?? ''));
      $relativePath = self::normalizeRelativePath((string) ($asset['relative_path'] ?? ''));
      $mediaType = strtolower(trim((string) ($asset['media_type'] ?? '')));
      $sha256 = strtolower(trim((string) ($asset['sha256'] ?? '')));
      $size = $asset['size_bytes'] ?? NULL;
      $artifactPath = trim((string) ($asset['artifact_path'] ?? ''));
      if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $id) !== 1
        || isset($seenIds[$id])
        || isset($seenPaths[$relativePath])
        || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1
        || (!is_int($size) && !(is_string($size) && ctype_digit($size)))
        || (int) $size < 1
        || (int) $size > self::MAX_ASSET_BYTES
        || $artifactPath === '') {
        throw new \InvalidArgumentException('Stored proof asset manifest contains unsafe values.');
      }
      self::assertMediaTypeForPath($mediaType, $relativePath);
      $total += (int) $size;
      if ($total > self::MAX_ASSET_BYTES_PER_VARIANT) {
        throw new \InvalidArgumentException('Stored proof direction assets exceed the allowed combined size.');
      }
      $seenIds[$id] = TRUE;
      $seenPaths[$relativePath] = TRUE;
      $normal[] = [
        'asset_id' => $id,
        'relative_path' => $relativePath,
        'media_type' => $mediaType,
        'sha256' => $sha256,
        'size_bytes' => (int) $size,
        'artifact_path' => $artifactPath,
      ];
    }
    usort($normal, static fn (array $left, array $right): int => strcmp($left['relative_path'], $right['relative_path']));
    return $normal;
  }

  /** Safely validates a request path before it selects a frozen asset. */
  public static function normalizeRelativePath(string $relativePath): string {
    $relativePath = trim(str_replace('\\', '/', $relativePath));
    if ($relativePath === '' || strlen($relativePath) > 180 || str_starts_with($relativePath, '/') || str_contains($relativePath, "\0") || str_contains($relativePath, '?') || str_contains($relativePath, '#') || str_contains($relativePath, '%')) {
      throw new \InvalidArgumentException('Proof asset relative_path is unsafe.');
    }
    $parts = explode('/', $relativePath);
    if (count($parts) > 6) {
      throw new \InvalidArgumentException('Proof asset relative_path is too deep.');
    }
    foreach ($parts as $part) {
      if ($part === '' || $part === '.' || $part === '..' || str_starts_with($part, '.') || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,95}$/', $part) !== 1) {
        throw new \InvalidArgumentException('Proof asset relative_path is unsafe.');
      }
    }
    return implode('/', $parts);
  }

  /** Returns the deterministic, protected artifact path for a validated asset. */
  public static function artifactPath(string $campaignId, string $direction, string $relativePath): string {
    if (preg_match('/^pc-[a-z0-9-]+$/', $campaignId) !== 1 || preg_match('/^[a-f]$/', $direction) !== 1) {
      throw new \InvalidArgumentException('Proof asset campaign or direction is invalid.');
    }
    return 'web/proofs/' . $campaignId . '/' . $direction . '/assets/' . self::normalizeRelativePath($relativePath);
  }

  private static function maxEncodedBytes(): int {
    return (int) ceil(self::MAX_ASSET_BYTES * 4 / 3) + 8;
  }

  private static function assertMediaTypeForPath(string $mediaType, string $relativePath): void {
    $extensions = [
      'image/jpeg' => ['jpg', 'jpeg'],
      'image/png' => ['png'],
      'image/webp' => ['webp'],
      'image/avif' => ['avif'],
    ];
    if (!isset($extensions[$mediaType])) {
      throw new \InvalidArgumentException('Proof assets must be JPEG, PNG, WebP, or AVIF images.');
    }
    $extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
    if (!in_array($extension, $extensions[$mediaType], TRUE)) {
      throw new \InvalidArgumentException('Proof asset file extension does not match its declared media type.');
    }
  }

  private static function matchesMagic(string $mediaType, string $bytes): bool {
    return match ($mediaType) {
      'image/jpeg' => str_starts_with($bytes, "\xff\xd8\xff"),
      'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1a\n"),
      'image/webp' => strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP',
      'image/avif' => self::isAvif($bytes),
      default => FALSE,
    };
  }

  private static function isAvif(string $bytes): bool {
    if (strlen($bytes) < 16 || substr($bytes, 4, 4) !== 'ftyp') {
      return FALSE;
    }
    $brands = substr($bytes, 8, min(64, strlen($bytes) - 8));
    return str_contains($brands, 'avif') || str_contains($brands, 'avis');
  }

}
