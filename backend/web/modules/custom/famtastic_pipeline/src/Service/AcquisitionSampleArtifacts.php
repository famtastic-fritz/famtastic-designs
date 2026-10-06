<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Site\Settings;

/** Reviewed source inputs only; serving uses immutable database snapshots. */
final class AcquisitionSampleArtifacts {
  /** Resolve the separately frozen nine-industry bundle without touching beauty settings. */
  public static function industryPath(string $relative, ?string $expectedHash = NULL): string {
    $valid = $relative === 'docs/research/acquisition-199/TEMPLATE-APPROVAL-20261006.json'
      || preg_match('#^marketing/campaigns/acquisition-199/(?:industry-previews/(?:[a-z0-9]+(?:-[a-z0-9]+)*/(?:email\.html|email\.txt|email-images-blocked\.html|lab\.html|lab\.css|lab\.js|receipt\.json|[a-z0-9_-]+\.svg|assets/[a-z0-9_.-]+)|assets/(?:connect-qr|famtastic-designs-logo-v1)\.png|generic-review/assets/(?:metropolis-regular|metropolis-bold|lora-bold|lora-italic)\.woff2)|assets/(?:connect-qr|famtastic-designs-logo-v1)\.png|generic-review/assets/(?:connect-qr|famtastic-designs-logo-v1)\.png|generic-review/assets/(?:metropolis-regular|metropolis-bold|lora-bold|lora-italic)\.woff2)$#D', $relative) === 1;
    if (!$valid || ($expectedHash !== NULL && !preg_match('/^[a-f0-9]{64}$/D', $expectedHash))) throw new \InvalidArgumentException('industry_artifact_path_invalid');
    $bundle = class_exists(Settings::class) ? (string) Settings::get('famtastic_acquisition_industry_bundle_root', '') : '';
    if ($bundle === '') {
      $root = dirname(__DIR__, 7);
      $path = realpath($root . '/' . $relative);
      if (!$path || !str_starts_with($path, $root . '/') || is_link($root . '/' . $relative) || !is_file($path)) throw new \RuntimeException('industry_source_artifact_unavailable');
    }
    else {
      $private = realpath((string) Settings::get('file_private_path', ''));
      $root = realpath($bundle);
      $manifestHash = (string) Settings::get('famtastic_acquisition_industry_bundle_sha256', '');
      if (!$private || !$root || !str_starts_with($root . '/', $private . '/') || !preg_match('/^[a-f0-9]{64}$/D', $manifestHash) || is_link($bundle)) throw new \RuntimeException('industry_private_bundle_required');
      $manifestPath = $root . '/manifest.json';
      if (!is_file($manifestPath) || is_link($manifestPath) || !hash_equals($manifestHash, (string) hash_file('sha256', $manifestPath))) throw new \RuntimeException('industry_bundle_manifest_changed');
      $manifest = json_decode((string) file_get_contents($manifestPath), TRUE, 32, JSON_THROW_ON_ERROR);
      $path = realpath($root . '/' . $relative);
      $manifestEntry = (string) ($manifest['files'][$relative] ?? '');
      if (($manifest['schema'] ?? '') !== 'famtastic.acquisition-industry-bundle.v1' || !$path || !str_starts_with($path, $root . '/') || is_link($root . '/' . $relative) || !is_file($path) || !preg_match('/^[a-f0-9]{64}$/D', $manifestEntry) || !hash_equals($manifestEntry, (string) hash_file('sha256', $path))) throw new \RuntimeException('industry_bundle_artifact_changed');
    }
    $actualHash = (string) hash_file('sha256', $path);
    if ($expectedHash !== NULL && !hash_equals($expectedHash, $actualHash)) throw new \RuntimeException('industry_artifact_hash_mismatch');
    return $path;
  }

  public static function path(string $relative): string {
    if ($relative !== 'docs/research/acquisition-199/CREATIVE-APPROVAL.json' && !preg_match('#^marketing/campaigns/acquisition-199/(?:templates/[a-z0-9_]+\.html|generic-review/(?:beauty-(?:template|email|lab)\.html|beauty-email\.txt|beauty-lab\.css|beauty-practice\.js|assets/(?:hair-studio\.jpg|connect-qr\.png|famtastic-designs-logo-v1\.png))|assets/[a-z0-9_-]+\.(?:png|jpg)|messages\.json)$#D', $relative)) throw new \InvalidArgumentException('acquisition_artifact_path_invalid');
    $bundle = class_exists(Settings::class) ? (string) Settings::get('famtastic_acquisition_bundle_root', '') : '';
    if ($bundle === '') {
      $sourceRoot = realpath(dirname(__DIR__, 7) . ($relative === 'docs/research/acquisition-199/CREATIVE-APPROVAL.json' ? '/docs/research/acquisition-199' : '/marketing/campaigns/acquisition-199'));
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
