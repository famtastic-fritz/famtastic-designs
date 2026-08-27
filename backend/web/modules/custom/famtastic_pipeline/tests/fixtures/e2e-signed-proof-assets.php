<?php

declare(strict_types=1);

use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Entity\Prospect;
use Drupal\famtastic_pipeline\Service\BuildTelemetryService;
use Drupal\famtastic_pipeline\Service\ProofAssetContract;
use Drupal\famtastic_pipeline\Service\ProofCampaignService;
use Drupal\famtastic_pipeline\Service\PublicPreviewDeliveryService;

/**
 * Local-only state setup for scripts/e2e-signed-proof-assets.sh.
 *
 * It creates synthetic records only. The shell harness exercises the HTTP
 * signed reader, tamper detection, and revoke behavior with this state.
 */

$statePath = (string) getenv('FAMTASTIC_E2E_STATE');
if ($statePath === '') {
  throw new RuntimeException('FAMTASTIC_E2E_STATE is required.');
}

$run = (string) (getenv('FAMTASTIC_E2E_RUN') ?: bin2hex(random_bytes(5)));
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', TRUE);
if ($png === FALSE) {
  throw new RuntimeException('The local PNG fixture is unavailable.');
}

/** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entities */
$entities = \Drupal::entityTypeManager();
/** @var ProofCampaignService $proofs */
$proofs = \Drupal::service('famtastic_pipeline.proof_campaign_service');
/** @var PublicPreviewDeliveryService $previews */
$previews = \Drupal::service('famtastic_pipeline.public_preview_deliveries');
/** @var BuildTelemetryService $telemetry */
$telemetry = \Drupal::service('famtastic_pipeline.build_telemetry');

$assertThrows = static function (callable $callback, string $label): void {
  try {
    $callback();
  }
  catch (InvalidArgumentException) {
    return;
  }
  throw new RuntimeException('Expected proof asset rejection: ' . $label);
};

$asset = static function (string $id, string $path) use ($png): array {
  return [
    'asset_id' => $id,
    'relative_path' => $path,
    'media_type' => 'image/png',
    'base64' => base64_encode($png),
    'sha256' => hash('sha256', $png),
  ];
};

if (ProofAssetContract::artifactPath('pc-signed-asset-direction-fixture', 'f', 'nested/hero.png') !== 'web/proofs/pc-signed-asset-direction-fixture/f/assets/nested/hero.png') {
  throw new RuntimeException('The signed proof asset contract must support configured directions a through f.');
}

// Unit-level wire validation covers all fail-closed input branches before the
// synthetic callback below verifies that those bytes never create artifacts.
$assertThrows(static fn () => ProofAssetContract::normalizeCallbackAssets('not-a-list'), 'non-list');
$assertThrows(static fn () => ProofAssetContract::normalizeCallbackAssets([array_merge($asset('hero', 'hero.png'), ['relative_path' => '../escape.png'])]), 'path traversal');
$assertThrows(static fn () => ProofAssetContract::normalizeCallbackAssets([array_merge($asset('hero', 'hero.png'), ['sha256' => str_repeat('0', 64)])]), 'hash mismatch');
$assertThrows(static fn () => ProofAssetContract::normalizeCallbackAssets([array_merge($asset('hero', 'hero.png'), ['media_type' => 'image/jpeg'])]), 'mime mismatch');
$assertThrows(static fn () => ProofAssetContract::normalizeCallbackAssets([array_merge($asset('hero', 'hero.jpg'), ['media_type' => 'image/jpeg'])]), 'magic mismatch');

$variants = static function (bool $withAssets, string $suffix) use ($asset, $png): array {
  $result = [];
  foreach (['a', 'b', 'c'] as $direction) {
    $assets = $withAssets ? [$asset('hero-' . $direction, 'hero-' . $direction . '.png')] : [];
    $result[] = [
      'direction_id' => $direction,
      'html' => '<!doctype html><html><head><meta charset="utf-8"><title>Signed asset ' . $direction . '</title></head><body><main><h1>Direction ' . $direction . '</h1>' . ($withAssets ? '<img src="assets/hero-' . $direction . '.png" alt="Fixture hero">' : '') . '</main></body></html>',
      'design_dna' => [
        'source' => 'gemini_flash_lite_image',
        'direction_name' => ['a' => 'Safe', 'b' => 'Medium FAMtastic', 'c' => 'Ultra FAMtastic'][$direction],
        'test_run' => $suffix,
      ],
      'thumbnail_media_type' => 'image/png',
      'thumbnail_base64' => base64_encode($png),
      'assets' => $assets,
    ];
  }
  return $result;
};

$recordDna = static function (string $buildId, Prospect $prospect, object $campaign, ?string $sourceLane) use ($entities, $telemetry): array {
  $variantIds = $entities->getStorage('proof_variant')->getQuery()->accessCheck(FALSE)
    ->condition('campaign_id', (int) $campaign->id())->sort('direction_id')->execute();
  $variants = $entities->getStorage('proof_variant')->loadMultiple($variantIds);
  $artifacts = [];
  foreach ($variants as $variant) {
    $htmlPath = (string) $variant->get('artifact_path')->value;
    $htmlAbsolute = dirname(\Drupal::root()) . '/' . ltrim($htmlPath, '/');
    $artifacts[] = [
      'role' => 'proof_html',
      'path' => $htmlPath,
      'sha256' => hash_file('sha256', $htmlAbsolute),
      'rights_status' => 'fixture',
    ];
    $dna = json_decode((string) $variant->get('design_dna')->value, TRUE, 64, JSON_THROW_ON_ERROR);
    foreach ((array) ($dna['asset_manifest'] ?? []) as $asset) {
      $artifacts[] = [
        'role' => 'proof_asset',
        'path' => (string) $asset['artifact_path'],
        'sha256' => (string) $asset['sha256'],
        'rights_status' => 'fixture',
      ];
    }
  }
  $run = [
    'campaign_id' => (string) $campaign->get('campaign_id')->value,
    'prospect_id' => (int) $prospect->id(),
    'proof_campaign_id' => (int) $campaign->id(),
    'started_at' => gmdate(DATE_ATOM),
    'completed_at' => gmdate(DATE_ATOM),
  ];
  if ($sourceLane !== NULL) {
    $run['source_lane'] = $sourceLane;
  }
  $dna = [
    'schema' => 'famtastic.build-dna.v1',
    'build_id' => $buildId,
    'created_at' => gmdate(DATE_ATOM),
    'run' => $run,
    'repository' => ['revision' => str_repeat('a', 40)],
    'recipe' => ['routine' => 'website_proof.generate.v1'],
    'stages' => [[
      'stage_id' => 'fixture-provider',
      'execution' => [
        'provider' => ['id' => 'fixture-provider'],
        'model' => ['id' => 'fixture-model'],
      ],
      'result' => ['status' => 'passed'],
    ]],
    'artifacts' => $artifacts,
  ];
  $telemetry->recordBuildDna($dna);
  $row = \Drupal::database()->select('famtastic_build_run', 'b')->fields('b', ['artifact_checksum'])
    ->condition('build_key', 'build-dna:' . $buildId)->range(0, 1)->execute()->fetchAssoc();
  if (!$row) {
    throw new RuntimeException('Fixture Build DNA did not register.');
  }
  return ['id' => $buildId, 'hash' => (string) $row['artifact_checksum']];
};

$create = static function (string $label, bool $withAssets, ?string $sourceLane) use ($run, $entities, $proofs, $previews, $variants, $recordDna): array {
  /** @var Prospect $prospect */
  $prospect = $entities->getStorage('famtastic_prospect')->create([
    'business_name' => 'Signed Asset ' . $label . ' ' . $run,
    'business_category' => 'Fixture studio',
    'public_email' => strtolower($label . '-' . $run . '@example.test'),
    'campaign' => 'e2e-signed-proof-assets',
    'source' => 'local-fixture',
  ]);
  $prospect->save();
  $delivery = $previews->createForPublicLead((int) $prospect->id());
  $created = $proofs->createForProspect($prospect, ['public_preview_delivery_id' => (int) $delivery['id']]);
  $campaign = $created['campaign'];
  $job = (string) $campaign->get('studio_job_id')->value;
  if (!str_starts_with($job, 'local-')) {
    throw new RuntimeException('Fixture did not create a local callback campaign.');
  }

  if ($withAssets) {
    $bad = $variants(TRUE, $label . '-bad');
    $bad[0]['assets'][0]['relative_path'] = '../escape.png';
    $badRejected = FALSE;
    try {
      $proofs->acceptCallback('bad-assets-' . $label . '-' . $run, (string) $campaign->get('campaign_id')->value, $job, $bad);
    }
    catch (InvalidArgumentException) {
      $badRejected = TRUE;
    }
    if (!$badRejected) {
      throw new RuntimeException('Unsafe callback asset path was accepted.');
    }
  }

  $proofs->acceptCallback('good-assets-' . $label . '-' . $run, (string) $campaign->get('campaign_id')->value, $job, $variants($withAssets, $label));
  $build = $recordDna('signed-assets-' . $label . '-' . $run, $prospect, $campaign, $sourceLane);
  return compact('prospect', 'delivery', 'campaign', 'build', 'job');
};

$rich = $create('cold', TRUE, 'verified_cold');
// The quality lane fails closed when any direction lacks frozen visual proof.
$coldEmpty = $create('cold-empty', FALSE, 'verified_cold');
$coldEmptyRejected = FALSE;
try {
  $previews->stage((int) $coldEmpty['delivery']['id'], (int) $coldEmpty['campaign']->id(), $coldEmpty['build']['id'], $coldEmpty['build']['hash']);
}
catch (RuntimeException) {
  $coldEmptyRejected = TRUE;
}
if (!$coldEmptyRejected) {
  throw new RuntimeException('Verified cold staging accepted an assetless proof set.');
}

// Existing assetless rooms must remain readable until explicitly regenerated.
$legacy = $create('legacy', FALSE, NULL);

$states = ['rich' => $rich, 'legacy' => $legacy];
foreach ($states as $key => $state) {
  $row = $previews->stage((int) $state['delivery']['id'], (int) $state['campaign']->id(), $state['build']['id'], $state['build']['hash']);
  $row = $previews->approveAndHold((int) $row['id'], 1);
  $publicId = (string) $row['public_id'];
  $signature = hash_hmac('sha256', 'public-preview-share-v1|' . $publicId . '|' . (int) $row['share_version'], Settings::getHashSalt());
  $states[$key] = [
    'delivery_id' => (int) $row['id'],
    'public_id' => $publicId,
    'signature' => $signature,
    'campaign_id' => (string) $state['campaign']->get('campaign_id')->value,
    'job_id' => (string) $state['job'],
  ];
  if ($key === 'rich') {
    $assetPath = \Drupal::root() . '/proofs/' . $states[$key]['campaign_id'] . '/a/assets/hero-a.png';
    if (!is_file($assetPath) || !str_contains((string) file_get_contents(dirname($assetPath) . '/.htaccess'), 'Require all denied')) {
      throw new RuntimeException('Proof image was not stored below protected assets.');
    }
    $states[$key]['asset_path'] = $assetPath;
    $states[$key]['asset_sha256'] = hash_file('sha256', $assetPath);
    $states[$key]['proof_sha256'] = hash_file('sha256', \Drupal::root() . '/proofs/' . $states[$key]['campaign_id'] . '/a/index.html');
  }
}
$rich = $states['rich'];
$legacy = $states['legacy'];

file_put_contents($statePath, json_encode([
  'classification' => 'local_fixture_only',
  'rich' => $rich,
  'legacy' => $legacy,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
