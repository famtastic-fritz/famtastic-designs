<?php

declare(strict_types=1);

use Drupal\famtastic_pipeline\Service\ColdProofBuildHandoffService;
use Drupal\famtastic_pipeline\Service\ColdProofCampaignSeedValidator;
use Drupal\famtastic_pipeline\Service\ColdProofCommercialMessageService;
use Drupal\famtastic_pipeline\Service\ColdProofIngressService;
use Drupal\famtastic_pipeline\Service\PublicPreviewDeliveryService;
use Symfony\Component\HttpFoundation\Request;
use Drupal\famtastic_pipeline\Controller\EmailEventController;

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
      // Deliberately include contact/credential-shaped text. The public
      // builder packet must retain the source-backed fact shape without
      // exposing copied listing contacts or accidental secret material.
      'corroborated_fact' => 'The fixture directory identifies this business as a studio in Port Saint Lucie. Contact copied-listing@example.test or 772-555-0199. token=fixture-secret-token-value.',
      'proof_teaser' => 'A review-only concept can use only the source-supported business facts; email copied-teaser@example.test and api_key=fixture-secret-value-1234567890 are not public proof content.',
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
  $publicBrief = (array) ($binding['public_brief'] ?? []);
  $publicEvidence = (array) ($publicBrief['verified_source'] ?? []);
  $publicText = json_encode($publicEvidence, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  foreach (['copied-listing@example.test', 'copied-teaser@example.test', '772-555-0199', 'fixture-secret-token-value', 'fixture-secret-value-1234567890'] as $sensitive) {
    if (str_contains($publicText, $sensitive)) {
      throw new RuntimeException('Verified-cold public builder brief retained sensitive copied source content.');
    }
  }
  if (!str_contains($publicText, '[redacted email]') || !str_contains($publicText, '[redacted phone]') || !str_contains($publicText, '[redacted secret]')) {
    throw new RuntimeException('Verified-cold public builder brief did not retain the expected redaction markers.');
  }
  // A corrupt cold message must not escape into the legacy prospect-token
  // click route. This uses a local synthetic row only and does not stage,
  // approve, send, or expose a proof room.
  $invalidTracking = str_repeat('d', 48);
  $database = \Drupal::database();
  $prospects = \Drupal::entityTypeManager()->getStorage('famtastic_prospect');
  $sourceProspect = $prospects->load((int) $lead['prospect_id']);
  if (!$sourceProspect) throw new RuntimeException('Fixture cold prospect is unavailable for click-route regression.');
  $now = \Drupal::time()->getRequestTime();
  $database->insert('famtastic_email_message')->fields([
    'message_key' => 'fixture-invalid-cold-click:' . $run,
    'prospect_id' => (int) $lead['prospect_id'],
    'campaign_id' => (int) $database->select('famtastic_campaign', 'c')->fields('c', ['id'])
      ->condition('campaign_key', (string) $sourceProspect->get('campaign')->value)->range(0, 1)->execute()->fetchField(),
    'recipient_hash' => hash('sha256', 'fixture-invalid-cold-click:' . $run),
    'recipient_address' => 'invalid-click-' . $run . '@example.test',
    'from_address' => 'fixture@example.test',
    'template_key' => 'verified_cold_preview',
    'template_version' => 1,
    'subject' => 'Fixture invalid cold click',
    'body_snapshot' => 'No send fixture.',
    'proof_url' => 'https://not-famtastic.example.test/not-a-signed-room',
    'status' => 'staged',
    'tracking_key' => $invalidTracking,
    'unsubscribe_key' => str_repeat('e', 48),
    'created' => $now,
    'changed' => $now,
  ])->execute();
  $click = (new EmailEventController(
    \Drupal::service('famtastic_pipeline.campaign_messages'),
    \Drupal::service('famtastic_pipeline.token_manager'),
  ))->click($invalidTracking);
  if ($click->getStatusCode() !== 404 || !str_contains((string) $click->getContent(), 'invalid_cold_preview_destination')) {
    throw new RuntimeException('Malformed verified-cold click destination fell through instead of failing closed.');
  }
  // A real-looking SMTP configuration is the production default, so prove it
  // cannot claim an owner-held cold message without both explicit real-send
  // gates. This synthetic fixture only creates and revokes local rows; the
  // mailer must never be reached.
  putenv('FAMTASTIC_OUTREACH_POSTAL_ADDRESS=1729 NW St. Lucie West Blvd #1181, Port Saint Lucie, FL 34986');
  putenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT=smtp');
  putenv('FAMTASTIC_ALLOW_REAL_OUTREACH');
  putenv('FAMTASTIC_ALLOW_VERIFIED_COLD_REAL_OUTREACH');
  $dispatchCampaignKey = 'fixture-dispatch-' . $run;
  $dispatchCampaignId = \Drupal::service('famtastic_pipeline.lead_ingestion')->ensureCampaignForChannel($dispatchCampaignKey, 'Local gate fixture', 'public_preview');
  $database->update('famtastic_campaign')->fields(['status' => 'approved', 'changed' => $now])
    ->condition('id', $dispatchCampaignId)->execute();
  $dispatchProspect = $prospects->create([
    'business_name' => 'Dispatch Gate Fixture ' . $run,
    'business_category' => 'Fixture studio',
    'public_email' => 'dispatch-gate-' . $run . '@example.test',
    'campaign' => $dispatchCampaignKey,
    'source' => 'local-fixture',
  ]);
  $dispatchProspect->save();
  /** @var PublicPreviewDeliveryService $previews */
  $previews = \Drupal::service('famtastic_pipeline.public_preview_deliveries');
  $dispatchDelivery = $previews->createForPublicLead((int) $dispatchProspect->id(), 0, NULL, NULL, 'verified_cold');
  $dispatchDeliveryId = (int) $dispatchDelivery['id'];
  /** @var ColdProofCommercialMessageService $commercial */
  $commercial = \Drupal::service('famtastic_pipeline.cold_proof_commercial_messages');
  $dispatchMessage = $commercial->stage(
    $dispatchDelivery,
    $dispatchProspect,
    'Fixture cold dispatch gate',
    'View the fixture concept room: https://famtastic.local/proofs/fixture',
    'https://famtastic.local/proofs/fixture',
  );
  $database->update('famtastic_preview_delivery')->fields([
    'state' => 'email_staged',
    'commercial_message_id' => (int) $dispatchMessage['id'],
    'subject_snapshot' => (string) $dispatchMessage['subject'],
    'text_snapshot' => (string) $dispatchMessage['body_snapshot'],
    'changed' => $now,
  ])->condition('id', $dispatchDeliveryId)->execute();
  $previews->approveAndHold($dispatchDeliveryId, 1);
  $dispatchDenied = FALSE;
  try {
    $previews->dispatchApproved([$dispatchDeliveryId]);
  }
  catch (RuntimeException $error) {
    $dispatchDenied = str_contains($error->getMessage(), 'FAMTASTIC_ALLOW_REAL_OUTREACH=true');
  }
  $afterDeniedDelivery = $database->select('famtastic_preview_delivery', 'p')->fields('p', ['state', 'email_outbox_id'])
    ->condition('id', $dispatchDeliveryId)->range(0, 1)->execute()->fetchAssoc();
  $afterDeniedOutbox = $database->select('famtastic_notification_outbox', 'n')->fields('n', ['status'])
    ->condition('id', (int) ($afterDeniedDelivery['email_outbox_id'] ?? 0))->range(0, 1)->execute()->fetchAssoc();
  $afterDeniedMessage = $database->select('famtastic_email_message', 'm')->fields('m', ['status'])
    ->condition('id', (int) $dispatchMessage['id'])->range(0, 1)->execute()->fetchAssoc();
  if (
    !$dispatchDenied
    || !$afterDeniedDelivery || (string) $afterDeniedDelivery['state'] !== 'email_approved'
    || !$afterDeniedOutbox || (string) $afterDeniedOutbox['status'] !== 'held'
    || !$afterDeniedMessage || (string) $afterDeniedMessage['status'] !== 'held'
  ) {
    throw new RuntimeException('Verified-cold transport denial changed a held delivery or did not produce the clear owner gate error.');
  }
  $previews->revoke($dispatchDeliveryId, 1);
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
  // The generic service method must remain closed even when a future caller
  // presents the exact immutable event/job/campaign tuple. This is separate
  // from the generic Drush command guard: it proves no alternate in-process
  // caller can create variants, Build DNA, or a review-ready delivery without
  // the dedicated transaction that receives a finalized Build DNA manifest.
  $genericServiceVariantsBefore = (int) $database->select('proof_variant', 'v')
    ->condition('campaign_id', (int) $lead['proof_campaign_id'])->countQuery()->execute()->fetchField();
  $genericServiceBuildsBefore = (int) $database->select('famtastic_build_run', 'b')
    ->condition('proof_campaign_id', (int) $lead['proof_campaign_id'])->countQuery()->execute()->fetchField();
  $genericServiceDeliveryBefore = (string) $database->select('famtastic_preview_delivery', 'p')->fields('p', ['state'])
    ->condition('id', $deliveryId)->range(0, 1)->execute()->fetchField();
  $exactGenericServiceRejected = FALSE;
  try {
    $proofs->acceptCallback(
      (string) $binding['callback_event_id'],
      (string) $binding['campaign_id'],
      (string) $binding['job_id'],
      [],
    );
  }
  catch (InvalidArgumentException $error) {
    $exactGenericServiceRejected = str_contains($error->getMessage(), 'private Build DNA importer');
  }
  $genericServiceVariantsAfter = (int) $database->select('proof_variant', 'v')
    ->condition('campaign_id', (int) $lead['proof_campaign_id'])->countQuery()->execute()->fetchField();
  $genericServiceBuildsAfter = (int) $database->select('famtastic_build_run', 'b')
    ->condition('proof_campaign_id', (int) $lead['proof_campaign_id'])->countQuery()->execute()->fetchField();
  $genericServiceDeliveryAfter = (string) $database->select('famtastic_preview_delivery', 'p')->fields('p', ['state'])
    ->condition('id', $deliveryId)->range(0, 1)->execute()->fetchField();
  if (
    !$exactGenericServiceRejected
    || $genericServiceVariantsAfter !== $genericServiceVariantsBefore
    || $genericServiceBuildsAfter !== $genericServiceBuildsBefore
    || $genericServiceDeliveryAfter !== $genericServiceDeliveryBefore
  ) {
    throw new RuntimeException('Generic verified-cold service callback bypassed the Build DNA transaction or mutated campaign state.');
  }
  // A campaign can be staged for inspection while draft, but its commercial
  // message cannot be held. This regression proves the failed owner approval
  // leaves no held outbox and no email_approved delivery state behind.
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
  $draftDelivery = $previews->createForPublicLead((int) $draftProspect->id(), 0, NULL, NULL, 'verified_cold');
  $draftDeliveryId = (int) $draftDelivery['id'];
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
  // A GET to the visible commercial unsubscribe URL is only a confirmation
  // page. It must not mutate the local cold message when a mail gateway or
  // security scanner prefetches it. The RFC 8058 POST then suppresses that
  // exact cold message only. This fixture never invokes SMTP.
  $unsubscribeKey = (string) ($message['unsubscribe_key'] ?? '');
  if (preg_match('/^[a-f0-9]{48}$/', $unsubscribeKey) !== 1) {
    throw new RuntimeException('Cold unsubscribe fixture did not receive an opaque key.');
  }
  $unsubscribePath = '/web/api/pipeline/email/unsubscribe/confirm/' . $unsubscribeKey;
  $unsubscribeController = new EmailEventController(
    \Drupal::service('famtastic_pipeline.campaign_messages'),
    \Drupal::service('famtastic_pipeline.token_manager'),
  );
  $confirmation = $unsubscribeController->verifiedColdUnsubscribe(Request::create($unsubscribePath, 'GET'), $unsubscribeKey);
  $afterGet = $database->select('famtastic_email_message', 'm')->fields('m', ['status'])
    ->condition('id', (int) $message['id'])->range(0, 1)->execute()->fetchAssoc();
  if (
    $confirmation->getStatusCode() !== 200
    || !str_contains((string) $confirmation->getContent(), 'Confirm unsubscribe')
    || !str_contains((string) $confirmation->getContent(), 'action="/web/api/pipeline/email/unsubscribe/confirm/')
    || !$afterGet
    || (string) $afterGet['status'] !== 'staged'
  ) {
    throw new RuntimeException('Verified-cold unsubscribe GET did not remain a non-mutating confirmation page.');
  }
  // A copied cold key can be transformed back into the historical GET route.
  // That legacy endpoint must reject it before it records consent or changes
  // the cold message; only the confirmation POST below may do either.
  $coldConsentBeforeLegacyGet = (int) $database->select('famtastic_consent', 'c')
    ->condition('prospect_id', (int) $draftProspect->id())->countQuery()->execute()->fetchField();
  $legacyColdResponse = $unsubscribeController->unsubscribe($unsubscribeKey);
  $afterLegacyColdGet = $database->select('famtastic_email_message', 'm')->fields('m', ['status'])
    ->condition('id', (int) $message['id'])->range(0, 1)->execute()->fetchAssoc();
  $coldConsentAfterLegacyGet = (int) $database->select('famtastic_consent', 'c')
    ->condition('prospect_id', (int) $draftProspect->id())->countQuery()->execute()->fetchField();
  if (
    $legacyColdResponse->getStatusCode() !== 404
    || !$afterLegacyColdGet || (string) $afterLegacyColdGet['status'] !== 'staged'
    || $coldConsentAfterLegacyGet !== $coldConsentBeforeLegacyGet
  ) {
    throw new RuntimeException('Legacy unsubscribe GET accepted a verified-cold key or changed its consent state.');
  }
  $confirmed = $unsubscribeController->verifiedColdUnsubscribe(
    Request::create($unsubscribePath, 'POST', ['List-Unsubscribe' => 'One-Click']),
    $unsubscribeKey,
  );
  $afterPost = $database->select('famtastic_email_message', 'm')->fields('m', ['status'])
    ->condition('id', (int) $message['id'])->range(0, 1)->execute()->fetchAssoc();
  if ($confirmed->getStatusCode() !== 200 || !$afterPost || (string) $afterPost['status'] !== 'unsubscribed') {
    throw new RuntimeException('Verified-cold one-click POST did not suppress its exact commercial message.');
  }
  // The cold confirmation URL cannot suppress an older campaign record even
  // if an opaque legacy key is supplied. That preserves the legacy endpoint
  // and confines the new POST capability to verified-cold mail only.
  $legacyKey = str_repeat('f', 48);
  $legacyMessageId = (int) $database->insert('famtastic_email_message')->fields([
    'message_key' => 'fixture-legacy-unsubscribe-isolation:' . $run,
    'prospect_id' => (int) $lead['prospect_id'],
    'campaign_id' => (int) $database->select('famtastic_campaign', 'c')->fields('c', ['id'])
      ->condition('campaign_key', (string) $sourceProspect->get('campaign')->value)->range(0, 1)->execute()->fetchField(),
    'recipient_hash' => hash('sha256', 'fixture-legacy-unsubscribe-isolation:' . $run),
    'recipient_address' => 'legacy-unsubscribe-' . $run . '@example.test',
    'from_address' => 'fixture@example.test',
    'template_key' => 'proof_ready',
    'template_version' => 1,
    'subject' => 'Legacy unsubscribe isolation fixture',
    'body_snapshot' => 'No send fixture.',
    'proof_url' => 'https://famtastic.local/legacy-fixture',
    'status' => 'staged',
    'tracking_key' => str_repeat('c', 48),
    'unsubscribe_key' => $legacyKey,
    'created' => $now,
    'changed' => $now,
  ])->execute();
  $legacyResponse = $unsubscribeController->verifiedColdUnsubscribe(
    Request::create('/web/api/pipeline/email/unsubscribe/confirm/' . $legacyKey, 'POST', ['List-Unsubscribe' => 'One-Click']),
    $legacyKey,
  );
  $legacyAfter = $database->select('famtastic_email_message', 'm')->fields('m', ['status'])
    ->condition('id', $legacyMessageId)->range(0, 1)->execute()->fetchAssoc();
  if ($legacyResponse->getStatusCode() !== 404 || !$legacyAfter || (string) $legacyAfter['status'] !== 'staged') {
    throw new RuntimeException('Verified-cold unsubscribe endpoint modified a legacy campaign message.');
  }
  // Historical non-cold mail retains its original GET unsubscribe behavior;
  // the cold route hardening must not make prior commercial links inert.
  $legacyGetResponse = $unsubscribeController->unsubscribe($legacyKey);
  $legacyAfterGet = $database->select('famtastic_email_message', 'm')->fields('m', ['status'])
    ->condition('id', $legacyMessageId)->range(0, 1)->execute()->fetchAssoc();
  if ($legacyGetResponse->getStatusCode() !== 200 || !$legacyAfterGet || (string) $legacyAfterGet['status'] !== 'unsubscribed') {
    throw new RuntimeException('Legacy non-cold unsubscribe GET no longer preserves its historical behavior.');
  }
  file_put_contents($statePath, json_encode([
    'lead' => $lead,
    'duplicate_reimport' => 'already_ingressed',
    'bundle' => $bundle,
    'public_brief_pii' => 'redacted',
    'cold_dispatch_gate' => 'denied_before_claim',
    'draft_owner_gate' => 'rejected_without_partial_hold',
    'cold_one_click_unsubscribe' => 'get_safe_post_suppressed',
    'cold_legacy_unsubscribe' => 'rejected_without_mutation',
    'cold_generic_service' => 'rejected_without_state_change',
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}
finally {
  @unlink($seedPath);
}
