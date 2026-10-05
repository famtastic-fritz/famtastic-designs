<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Site\Settings;

/** Reviewed source inputs only; serving uses immutable database snapshots. */
final class AcquisitionSampleArtifacts {
  public static function path(string $relative): string {
    if (!preg_match('#^marketing/campaigns/acquisition-199/(?:templates/[a-z0-9_]+\.html|generic-review/beauty-template\.html|assets/[a-z0-9_-]+\.(?:png|jpg)|messages\.json)$#D', $relative)) throw new \InvalidArgumentException('acquisition_artifact_path_invalid');
    $bundle = class_exists(Settings::class) ? (string) Settings::get('famtastic_acquisition_bundle_root', '') : '';
    if ($bundle === '') {
      $sourceRoot = realpath(dirname(__DIR__, 7) . '/marketing/campaigns/acquisition-199');
      $path = realpath(dirname(__DIR__, 7) . '/' . $relative);
      if (!$sourceRoot || !$path || !str_starts_with($path, $sourceRoot . '/') || is_link(dirname(__DIR__, 7) . '/' . $relative)) throw new \RuntimeException('acquisition_source_artifact_unavailable');
      return $path;
    }
    $private = realpath((string) Settings::get('file_private_path', ''));
    $root = realpath($bundle);
    $expected = (string) Settings::get('famtastic_acquisition_bundle_sha256', '');
    if (!$private || !$root || !str_starts_with($root . '/', $private . '/') || !preg_match('/^[a-f0-9]{64}$/D', $expected) || is_link($bundle)) throw new \RuntimeException('acquisition_private_bundle_required');
    $manifestPath = $root . '/manifest.json';
    if (!is_file($manifestPath) || is_link($manifestPath) || !hash_equals($expected, (string) hash_file('sha256', $manifestPath))) throw new \RuntimeException('acquisition_bundle_manifest_changed');
    $manifest = json_decode((string) file_get_contents($manifestPath), TRUE, 32, JSON_THROW_ON_ERROR);
    $path = realpath($root . '/' . $relative);
    if (($manifest['schema'] ?? '') !== 'famtastic.acquisition-source-bundle.v1' || !$path || !str_starts_with($path, $root . '/') || is_link($root . '/' . $relative) || !isset($manifest['files'][$relative]) || !hash_equals((string) $manifest['files'][$relative], (string) hash_file('sha256', $path))) throw new \RuntimeException('acquisition_bundle_artifact_changed');
    return $path;
  }
}
