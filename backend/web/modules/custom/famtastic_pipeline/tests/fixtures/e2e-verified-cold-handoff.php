<?php

declare(strict_types=1);

use Drupal\famtastic_pipeline\Service\ColdProofBuildHandoffService;
use Drupal\famtastic_pipeline\Service\ColdProofCampaignSeedValidator;
use Drupal\famtastic_pipeline\Service\ColdProofCommercialMessageService;
use Drupal\famtastic_pipeline\Service\ColdProofIngressService;
use Drupal\famtastic_pipeline\Service\PublicPreviewDeliveryService;

/**
 * Creates one local-only verified-cold ingress and exports its runner binding.
 *
 * This fixture never calls a provider, callback endpoint, mailer, owner gate,
 * public room, or payment flow. The shell harness supplies a disposable DB.
 */
$statePath = (string) getenv('FAMTASTIC_E2E_STATE');
if ($statePath === '') {
  throw new RuntimeException('FAMTASTIC_E2E_STATE is required.');
}
$run = (string) (getenv('FAMTASTIC_E2E_RUN') ?: bin2hex(random_bytes(5)));
$seedPath = tempnam(sys_get_temp_dir(), 'famtastic-cold-seed-');
if ($seedPath === FALSE) {
  throw new RuntimeException('Could not allocate a fixture seed.');
}
try {
  $seed = [
    'schema_version' => ColdProofCampaignSeedValidator::SCHEMA_VERSION,
    'cohort' => [
      'cohort_key' => 'fixture-cold-' . $run,
      'campaign_key' => 'fixture-cold-' . $run,
      'source_name' => 'Local verified-source fixture',
    ],
    'leads' => [[
      'source_record_id' => 'fixture-record-' . $run,
      'business_name' => 'Verified Cold Fixture ' . $run,
      'business_category' => 'Fixture studio',
      'email' => 'fixture-' . $run . '@example.test',
      'website_observation' => [
        'status' => 'confirmed_absent',
        'fact' => 'The local fixture directory entry checked 2026-08-27 has no public website field.',
      ],
      'public_source' => [
        'url' => 'https://directory.example.test/fixture-' . $run,
        'provenance' => 'local public-directory fixture',
        'timeframe' => 'checked 2026-08-27',
      ],
      'corroborated_fact' => 'The fixture directory identifies this business as a studio in Port Saint Lucie.',
      'proof_teaser' => 'A review-only concept can use only the source-supported business facts.',
    ]],
  ];
  file_put_contents($seedPath, json_encode($seed, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
  /** @var ColdProofIngressService $ingress */
  $ingress = \Drupal::service('famtastic_pipeline.cold_proof_ingress');
  $result = $ingress->importSeed($seedPath, FALSE);
  $lead = $result['leads'][0] ?? [];
  $deliveryId = (int) ($lead['preview_delivery_id'] ?? 0);
  if ($deliveryId < 1 || (int) ($lead['proof_campaign_id'] ?? 0) < 1 || (int) ($lead['proof_job_id'] ?? 0) < 1) {
    throw new RuntimeException('Cold ingress did not produce exact canonical delivery, campaign, and job IDs.');
  }
  // PDO returns this persisted cohort primary key as a string. Re-importing
  // the exact seed must safely match it as the existing ingress rather than
  // accepting an invalid scalar or creating a second proof job.
  $duplicate = $ingress->importSeed($seedPath, FALSE);
  $duplicateLead = $duplicate['leads'][0] ?? [];
  if (
    ($duplicateLead['action'] ?? '') !== 'already_ingressed'
    || (int) ($duplicateLead['preview_delivery_id'] ?? 0) !== $deliveryId
    || (int) ($duplicateLead['proof_campaign_id'] ?? 0) !== (int) $lead['proof_campaign_id']
    || (int) ($duplicateLead['proof_job_id'] ?? 0) !== (int) $lead['proof_job_id']
  ) {
    throw new RuntimeException('Re-import did not safely reuse the canonical persisted cohort ID and ingress identity.');
  }
  // The small coercion boundary must not turn malformed persisted scalar
  // values into a different canonical ID. Exercise it directly alongside the
  // real PDO-string re-import above.
  $persistedId = new ReflectionMethod(ColdProofIngressService::class, 'persistedId');
  if ($persistedId->invoke($ingress, '42', 'fixture ID') !== 42) {
    throw new RuntimeException('Canonical PDO numeric string was not accepted as an ID.');
  }
  foreach ([NULL, '', '0', '01', '+1', '1.0', '1 ', '9223372036854775808'] as $invalidId) {
    $rejected = FALSE;
    try {
      $persistedId->invoke($ingress, $invalidId, 'fixture invalid ID');
    }
    catch (RuntimeException) {
      $rejected = TRUE;
    }
    if (!$rejected) {
      throw new RuntimeException('Malformed persisted scalar ID was accepted.');
    }
  }
  /** @var ColdProofBuildHandoffService $handoffs */
  $handoffs = \Drupal::service('famtastic_pipeline.cold_proof_build_handoff');
  $bundle = $handoffs->export([$deliveryId]);
  $binding = $bundle['deliveries'][0] ?? [];
  if (
    ($binding['source_lane'] ?? '') !== 'verified_cold'
    || (int) ($binding['prospect_id'] ?? 0) !== (int) $lead['prospect_id']
    || (int) ($binding['proof_campaign_id'] ?? 0) !== (int) $lead['proof_campaign_id']
    || (int) ($binding['public_preview_delivery_id'] ?? 0) !== $deliveryId
    || ($binding['job']['job_type'] ?? '') !== 'public_preview.generate'
    || ($binding['build_dna_run']['source_lane'] ?? '') !== 'verified_cold'
    || !is_string($binding['job_id'] ?? NULL)
    || !str_starts_with((string) $binding['job_id'], 'cold-preview-')
    || !is_string($binding['callback_event_id'] ?? NULL)
    || !str_starts_with((string) $binding['callback_event_id'], 'cold-proof-callback-')
    || !is_string($binding['run_started_at'] ?? NULL)
    || strtotime((string) $binding['run_started_at']) === FALSE
    || ($binding['build_dna_run']['job_id'] ?? '') !== ($binding['job_id'] ?? '')
    || ($binding['build_dna_run']['callback_event_id'] ?? '') !== ($binding['callback_event_id'] ?? '')
    || ($binding['build_dna_run']['run_started_at'] ?? '') !== ($binding['run_started_at'] ?? '')
  ) {
    throw new RuntimeException('Cold handoff bundle is missing its exact runner identity contract.');
  }
  // No builder may invent a new callback event or substitute a generic job
  // after the job payload was frozen. These fail before any artifact write.
  $proofs = \Drupal::service('famtastic_pipeline.proof_campaign_service');
  foreach ([
    ['wrong-cold-proof-callback', (string) $binding['job_id']],
    [(string) $binding['callback_event_id'], 'cold-preview-wrong-job'],
  ] as [$eventId, $jobId]) {
    $rejected = FALSE;
    try {
      $proofs->acceptCallback($eventId, (string) $binding['campaign_id'], $jobId, []);
    }
    catch (InvalidArgumentException) {
      $rejected = TRUE;
    }
    if (!$rejected) {
      throw new RuntimeException('Verified-cold callback accepted a substituted job or event identity.');
    }
  }
  // A campaign can be staged for inspection while draft, but its commercial
  // message cannot be held. This regression proves the failed owner approval
  // leaves no held outbox and no email_approved delivery state behind.
  putenv('FAMTASTIC_OUTREACH_POSTAL_ADDRESS=1729 NW St. Lucie West Blvd #1181, Port Saint Lucie, FL 34986');
  $database = \Drupal::database();
  $prospects = \Drupal::entityTypeManager()->getStorage('famtastic_prospect');
  $sourceProspect = $prospects->load((int) $lead['prospect_id']);
  if (!$sourceProspect) throw new RuntimeException('Fixture cold prospect is unavailable for owner-gate regression.');
  $draftProspect = $prospects->create([
    'business_name' => 'Draft Gate Fixture ' . $run,
    'business_category' => 'Fixture studio',
    'public_email' => 'draft-gate-' . $run . '@example.test',
    // Reuse the one draft campaign created by the seed, not a made-up
    // approval. This second delivery keeps the canonical handoff fixture in
    // its pre-delivery state for command-export coverage.
    'campaign' => (string) $sourceProspect->get('campaign')->value,
    'source' => 'local-fixture',
  ]);
  $draftProspect->save();
  /** @var PublicPreviewDeliveryService $previews */
  $previews = \Drupal::service('famtastic_pipeline.public_preview_deliveries');
  $draftDelivery = $previews->createForPublicLead((int) $draftProspect->id(), 0, NULL, NULL, 'verified_cold');
  $draftDeliveryId = (int) $draftDelivery['id'];
  /** @var ColdProofCommercialMessageService $commercial */
  $commercial = \Drupal::service('famtastic_pipeline.cold_proof_commercial_messages');
  $message = $commercial->stage(
    $draftDelivery,
    $draftProspect,
    'Fixture draft-gated proof invitation',
    'View the fixture concept room: https://famtastic.local/proofs/fixture',
    'https://famtastic.local/proofs/fixture',
  );
  $database->update('famtastic_preview_delivery')->fields([
    'state' => 'email_staged',
    'commercial_message_id' => (int) $message['id'],
    'subject_snapshot' => 'Fixture draft-gated proof invitation',
    'text_snapshot' => 'Fixture draft-gated body',
    'changed' => \Drupal::time()->getRequestTime(),
  ])->condition('id', $draftDeliveryId)->execute();
  $draftGateRejected = FALSE;
  try {
    $previews->approveAndHold($draftDeliveryId, 1);
  }
  catch (RuntimeException) {
    $draftGateRejected = TRUE;
  }
  $afterDraftGate = $database->select('famtastic_preview_delivery', 'p')->fields('p', ['state', 'email_outbox_id'])
    ->condition('id', $draftDeliveryId)->range(0, 1)->execute()->fetchAssoc();
  $heldOutbox = $database->select('famtastic_notification_outbox', 'n')->fields('n', ['id'])
    ->condition('notification_key', 'preview-delivery:' . $draftDeliveryId . ':share:1')->range(0, 1)->execute()->fetchField();
  $afterMessage = $database->select('famtastic_email_message', 'm')->fields('m', ['status'])
    ->condition('id', (int) $message['id'])->range(0, 1)->execute()->fetchAssoc();
  if (
    !$draftGateRejected
    || !$afterDraftGate
    || (string) $afterDraftGate['state'] !== 'email_staged'
    || !empty($afterDraftGate['email_outbox_id'])
    || $heldOutbox
    || !$afterMessage
    || (string) $afterMessage['status'] !== 'staged'
  ) {
    throw new RuntimeException('A draft campaign approval attempt left a held outbox, approved delivery, or held commercial message.');
  }
  file_put_contents($statePath, json_encode([
    'lead' => $lead,
    'duplicate_reimport' => 'already_ingressed',
    'bundle' => $bundle,
    'draft_owner_gate' => 'rejected_without_partial_hold',
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}
finally {
  @unlink($seedPath);
}
