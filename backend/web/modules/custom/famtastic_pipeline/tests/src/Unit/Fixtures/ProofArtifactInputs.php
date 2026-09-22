<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit\Fixtures;

/** Synthetic file-validation inputs only, deliberately NOT customer deliverables. */
final class ProofArtifactInputs {
  public const DIRECTIONS = ['a' => 'Safe', 'b' => 'Wild', 'c' => 'OMG'];
  public static function asset(string $path = 'hero.png', int $size = 24): array {
    $bytes = "\x89PNG\r\n\x1a\n" . str_repeat('x', $size - 8);
    return ['asset_id' => 'hero', 'relative_path' => $path, 'media_type' => 'image/png', 'base64' => base64_encode($bytes), 'sha256' => hash('sha256', $bytes)];
  }
  public static function variants(): array {
    return array_map(static fn(string $d): array => ['direction_id' => $d,
      'html' => '<html><body><p>Synthetic ' . $d . ' preparation fixture. NOT deliverable.</p></body></html>',
      'design_dna' => ['source' => 'synthetic_preparation_fixture', 'direction_name' => self::DIRECTIONS[$d]],
      'assets' => [self::asset()], 'thumbnail_base64' => base64_encode("\xff\xd8\xfffixture"), 'thumbnail_media_type' => 'image/jpeg'], array_keys(self::DIRECTIONS));
  }
  public static function input(): array {
    return ['schema' => 'famtastic.managed-proof-artifacts.v1', 'event_id' => 'synthetic-preparation', 'campaign_id' => 'pc-synthetic-preparation', 'job_id' => 'normal-job', 'variants' => self::variants()];
  }
  public static function wire(mixed $value): string {
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
  }
}
