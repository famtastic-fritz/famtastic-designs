<?php

declare(strict_types=1);

use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Entity\Prospect;
use Drupal\famtastic_pipeline\Service\PublicPreviewDeliveryService;

/**
 * Local-only state setup for scripts/e2e-preview-registration-isolation.sh.
 *
 * Both prospects intentionally contain discovery notes. The public-preview
 * signup must not turn those notes into a request before email verification;
 * the ordinary registration remains the regression control.
 */

$statePath = (string) getenv('FAMTASTIC_PREVIEW_SIGNUP_ISOLATION_STATE');
if ($statePath === '' || !is_dir(dirname($statePath)) || !is_writable(dirname($statePath))) {
  throw new RuntimeException('A writable FAMTASTIC_PREVIEW_SIGNUP_ISOLATION_STATE is required.');
}

$run = preg_replace('/[^a-z0-9-]/', '', strtolower((string) (getenv('FAMTASTIC_PREVIEW_SIGNUP_ISOLATION_RUN') ?: bin2hex(random_bytes(5)))));
if ($run === '') {
  throw new RuntimeException('The local fixture run identifier is invalid.');
}

/** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entities */
$entities = \Drupal::entityTypeManager();
/** @var PublicPreviewDeliveryService $previews */
$previews = \Drupal::service('famtastic_pipeline.public_preview_deliveries');
$now = \Drupal::time()->getRequestTime();

$createProspect = static function (string $label, string $email) use ($entities, $run): Prospect {
  /** @var Prospect $prospect */
  $prospect = $entities->getStorage('famtastic_prospect')->create([
    'business_name' => $label . ' ' . $run,
    'business_category' => 'Fixture beauty studio',
    'public_email' => $email,
    'campaign' => 'e2e-preview-registration-isolation',
    'source' => 'local-fixture',
    'discovery_notes' => json_encode([
      'source' => 'e2e-preview-registration-isolation',
      'answers' => [
        'businessName' => $label . ' ' . $run,
        'industry' => 'Beauty services',
        'location' => 'Fixture City',
        'pages' => 3,
        'aiFeatures' => ['automation'],
      ],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
  ]);
  $prospect->save();
  return $prospect;
};

$previewEmail = 'preview-' . $run . '@example.test';
$ordinaryEmail = 'ordinary-' . $run . '@example.test';
$previewProspect = $createProspect('Preview isolation studio', $previewEmail);
$ordinaryProspect = $createProspect('Ordinary registration studio', $ordinaryEmail);
$delivery = $previews->createForPublicLead((int) $previewProspect->id());
$publicId = (string) $delivery['public_id'];
$continuation = $publicId . '.' . hash_hmac('sha256', 'public-preview-continuation-v1|' . $publicId, Settings::getHashSalt());

file_put_contents($statePath, json_encode([
  'classification' => 'local_fixture_only',
  'started_at' => $now,
  'preview' => [
    'prospect_id' => (int) $previewProspect->id(),
    'delivery_id' => (int) $delivery['id'],
    'public_id' => $publicId,
    'email' => $previewEmail,
    'continuation' => $continuation,
  ],
  'ordinary' => [
    'prospect_id' => (int) $ordinaryProspect->id(),
    'email' => $ordinaryEmail,
  ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
