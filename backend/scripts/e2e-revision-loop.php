<?php

declare(strict_types=1);

/**
 * Local-only e2e for LEAD_TO_LAUNCH step 9 — revision requests & re-proof loop.
 *
 * Proves the complete chain through real code paths: initial proof lands as
 * version 1 in immutable history -> customer requests a revision (token API) ->
 * included allowance exhaustion gates with revision_addon_required ->
 * revision add-on purchase through the real revision-checkout route on the
 * stub gateway -> payment confirmed through the same fulfillment path the
 * Stripe webhook uses -> revision_limit increments -> owner alerted plus
 * customer receipt queued and delivered (memory transport) -> re-proof lands
 * as version 2 while version 1 stays byte-identical. Every synthetic artifact
 * is cleaned up, including leftovers from earlier failed runs.
 */

use Drupal\user\Entity\User;
use Drupal\famtastic_pipeline\Controller\PipelineController;
use Symfony\Component\HttpFoundation\Request;

const MARKER = 'revision-loop';

$runId = (string) (getenv('FAMTASTIC_SYNTHETIC_RUN_ID') ?: time());
$email = MARKER . '-' . $runId . '@example.invalid';
$campaignKeyV1 = 'e2e-' . MARKER . '-v1-' . $runId;
$campaignKeyV2 = 'e2e-' . MARKER . '-v2-' . $runId;
$note = 'Swap the hero photo and soften the colors (synthetic revision).';
$now = \Drupal::time()->getRequestTime();
$db = \Drupal::database();
$entities = \Drupal::entityTypeManager();

/** Deletes every synthetic artifact this script can create. */
$cleanup = static function () use ($db, $entities, $email, $campaignKeyV1, $campaignKeyV2): void {
  $prospectIds = $entities->getStorage('famtastic_prospect')->getQuery()
    ->accessCheck(FALSE)->condition('public_email', $email)->execute();
  foreach ($prospectIds as $prospectId) {
    $db->delete('famtastic_event')->condition('prospect_id', (int) $prospectId)->execute();
    $db->delete('famtastic_job')->condition('prospect_id', (int) $prospectId)->execute();
    $db->delete('famtastic_consent')->condition('prospect_id', (int) $prospectId)->execute();
  }
  $requestIds = $db->select('famtastic_project_request', 'r')->fields('r', ['id'])
    ->condition('customer_id', $db->select('famtastic_customer', 'c')->fields('c', ['id'])
      ->condition('email', $email)->execute()->fetchField() ?: 0)
    ->execute()->fetchCol();
  foreach ($requestIds as $requestId) {
    $db->delete('famtastic_notification_outbox')
      ->condition('notification_key', 'website-request:' . (int) $requestId . ':%', 'LIKE')->execute();
  }
  $projectIds = $prospectIds ? $entities->getStorage('famtastic_project')->getQuery()
    ->accessCheck(FALSE)->condition('prospect_ref', array_map('intval', $prospectIds), 'IN')->execute() : [];
  foreach ($projectIds as $projectId) {
    $db->delete('famtastic_proof_version')->condition('project_id', (int) $projectId)->execute();
  }
  if ($prospectIds) {
    $orderIds = $entities->getStorage('famtastic_order')->getQuery()
      ->accessCheck(FALSE)->condition('prospect_ref', array_map('intval', $prospectIds), 'IN')->execute();
    if ($orderIds) {
      $entities->getStorage('famtastic_order')->delete($entities->getStorage('famtastic_order')->loadMultiple($orderIds));
    }
    $entities->getStorage('famtastic_project')->delete($entities->getStorage('famtastic_project')->loadMultiple($projectIds));
  }
  $campaignStorage = $entities->getStorage('proof_campaign');
  $variantStorage = $entities->getStorage('proof_variant');
  foreach ([$campaignKeyV1, $campaignKeyV2] as $campaignKey) {
    $campaignIds = $campaignStorage->getQuery()->accessCheck(FALSE)->condition('campaign_id', $campaignKey)->execute();
    foreach ($campaignStorage->loadMultiple($campaignIds) as $campaign) {
      $variantIds = $variantStorage->getQuery()->accessCheck(FALSE)
        ->condition('campaign_id', (int) $campaign->id())->execute();
      if ($variantIds !== []) {
        $variantStorage->delete($variantStorage->loadMultiple($variantIds));
      }
    }
    if ($campaignIds !== []) {
      $campaignStorage->delete($campaignStorage->loadMultiple($campaignIds));
    }
  }
  if ($prospectIds) {
    $entities->getStorage('famtastic_prospect')->delete($entities->getStorage('famtastic_prospect')->loadMultiple($prospectIds));
  }
  if ($requestIds !== []) {
    $db->delete('famtastic_project_request')->condition('id', array_map('intval', $requestIds), 'IN')->execute();
  }
  $customerId = (int) $db->select('famtastic_customer', 'c')->fields('c', ['id'])
    ->condition('email', $email)->execute()->fetchField();
  if ($customerId > 0) {
    $organizationIds = array_map('intval', $db->select('famtastic_membership', 'm')
      ->fields('m', ['organization_id'])->condition('m.customer_id', $customerId)->execute()->fetchCol());
    if ($organizationIds !== []) {
      $db->delete('famtastic_portal_activity')->condition('organization_id', $organizationIds, 'IN')->execute();
      $db->delete('famtastic_customer_resource')->condition('organization_id', $organizationIds, 'IN')->execute();
      $db->delete('famtastic_organization')->condition('id', $organizationIds, 'IN')->execute();
    }
    $db->delete('famtastic_membership')->condition('customer_id', $customerId)->execute();
    $db->delete('famtastic_customer')->condition('id', $customerId)->execute();
  }
  $db->delete('famtastic_notification_outbox')->condition('notification_key', 'revision_addon:%', 'LIKE')->execute();
  foreach ($entities->getStorage('user')->loadByProperties(['mail' => $email]) as $user) {
    $user->delete();
  }
};

$cleanup();
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo($entities->getStorage('user')->load(1));
$outboxRow = NULL;

try {
  // --- Synthetic fixture graph ---------------------------------------------
  $portal = \Drupal::service('famtastic_pipeline.customer_portal');
  $ledger = \Drupal::service('famtastic_pipeline.operational_ledger');
  $fulfillment = \Drupal::service('famtastic_pipeline.fulfillment');
  $tokenManager = \Drupal::service('famtastic_pipeline.token_manager');

  $rawToken = $tokenManager->generate()['raw'];
  $tokenHash = $tokenManager->hash($rawToken);
  $prospect = $entities->getStorage('famtastic_prospect')->create([
    'business_name' => 'Revision Loop Business',
    'public_email' => $email,
    'contact_name' => 'Revision Loop Customer',
    'contact_method' => 'email',
    'contact_value' => $email,
    'campaign' => 'public_quote',
    'status' => 'proof_ready',
    'owner_uid' => 1,
    'token_hash' => $tokenHash,
    'token_expires' => $now + 86400,
    'token_revoked' => FALSE,
  ]);
  $prospect->save();

  $user = User::create(['name' => $email, 'mail' => $email, 'status' => 1]);
  $user->setPassword('Synthetic-Revision-Loop-Pass!');
  $user->save();
  $customer = $portal->createCustomer($user, [
    'name' => 'Revision Loop Customer',
    'business_name' => 'Revision Loop Business',
    'source' => 'synthetic',
  ]);
  $organization = $portal->organizations((int) $customer['id'])[0];
  $portal->claimResource((int) $organization['id'], 'prospect', (int) $prospect->id());

  $project = $entities->getStorage('famtastic_project')->create([
    'prospect_ref' => (int) $prospect->id(),
    'delivery_status' => 'proof_delivered',
    'approval_status' => 'pending',
    'revision_limit' => 1,
    'revision_count' => 0,
    'studio_json' => '{"synthetic":"revision-loop-v1"}',
  ]);
  $project->save();
  $portal->claimResource((int) $organization['id'], 'project', (int) $project->id());

  /** Creates one ready three-direction proof campaign. */
  $makeCampaign = static function (string $campaignKey, string $urlPrefix) use ($entities, $prospect): array {
    $campaign = $entities->getStorage('proof_campaign')->create([
      'campaign_id' => $campaignKey,
      'prospect_id' => (int) $prospect->id(),
      'business_name' => 'Revision Loop Business',
      'generation_status' => 'ready',
      'status' => 'active',
    ]);
    $campaign->save();
    $variants = [];
    foreach ([['a', 'Safe'], ['b', 'Wild'], ['c', 'OMG']] as [$direction, $name]) {
      $variants[] = $entities->getStorage('proof_variant')->create([
        'campaign_id' => (int) $campaign->id(),
        'direction_id' => $direction,
        'direction_name' => $name,
        'preview_url' => $urlPrefix . '/' . $direction,
      ]);
      end($variants)->save();
    }
    return [$campaign, $variants];
  };

  $requestPublicId = substr(md5(MARKER . $runId . ':r1'), 0, 8) . '-' . substr(md5(MARKER . $runId . ':r2'), 0, 4) . '-4' . substr(md5(MARKER . $runId . ':r3'), 0, 3) . '-a' . substr(md5(MARKER . $runId . ':r4'), 0, 3) . '-' . str_pad(substr(md5(MARKER . $runId . ':r5'), 0, 12), 12, '0');
  $db->insert('famtastic_project_request')->fields([
    'public_id' => $requestPublicId,
    'organization_id' => (int) $organization['id'],
    'customer_id' => (int) $customer['id'],
    'prospect_id' => (int) $prospect->id(),
    'project_id' => (int) $project->id(),
    'status' => 'converted',
    'project_name' => 'Revision Loop Website',
    'project_type' => 'new_website',
    'domain_choice' => 'undecided',
    'intake_data' => '{}',
    'submitted_at' => $now,
    'created' => $now,
    'changed' => $now,
  ])->execute();
  $requestId = (int) $db->select('famtastic_project_request', 'r')->fields('r', ['id'])
    ->condition('public_id', $requestPublicId)->execute()->fetchField();

  // --- Step 9a: the first delivered proof set becomes version 1 -------------
  [$campaignV1, $variantsV1] = $makeCampaign($campaignKeyV1, '/e2e/' . MARKER . '/v1');
  $portal->attachWebsiteRequestProof($requestId, $campaignV1, $variantsV1);
  $versionRows = static fn (): array => array_values($db->select('famtastic_proof_version', 'v')->fields('v')
    ->condition('project_id', (int) $project->id())->orderBy('version')->execute()->fetchAll(\PDO::FETCH_ASSOC));
  $v1 = $versionRows()[0] ?? [];

  $checks = [
    'initial_version_recorded' => count($versionRows()) === 1
      && (int) ($v1['version'] ?? 0) === 1
      && ($v1['source'] ?? '') === 'initial'
      && ($v1['campaign_key'] ?? '') === $campaignKeyV1
      && (int) ($v1['revision_number'] ?? 1) === 0
      && preg_match('/^[0-9a-f]{64}$/', (string) ($v1['artifact_checksum'] ?? '')) === 1
      && (int) ($v1['recorded_at'] ?? 0) > 0,
  ];
  $v1Snapshot = ['checksum' => (string) ($v1['artifact_checksum'] ?? ''), 'recorded_at' => (int) ($v1['recorded_at'] ?? 0)];

  // --- Step 9b: customer requests a revision through the token API ----------
  $pipeline = \Drupal::classResolver()->getInstanceFromDefinition(PipelineController::class);
  $apiRequest = static function (string $body, string $token): Request {
    $request = Request::create('/api/pipeline/approval', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
    $request->headers->set('X-Prospect-Token', $token);
    return $request;
  };
  $response = $pipeline->approval($apiRequest((string) json_encode(['action' => 'request_revision', 'note' => $note]), $rawToken));
  $payload = json_decode((string) $response->getContent(), TRUE);
  $checks += [
    'revision_request_accepted' => $response->getStatusCode() === 200
      && ($payload['ok'] ?? FALSE) === TRUE
      && ($payload['approval_status'] ?? '') === 'revision_requested'
      && (int) ($payload['revision_count'] ?? 0) === 1,
    'prior_version_preserved_after_request' => count($versionRows()) === 1
      && ($versionRows()[0]['artifact_checksum'] ?? '') === $v1Snapshot['checksum']
      && (int) $versionRows()[0]['recorded_at'] === $v1Snapshot['recorded_at'],
    'ledger_revision_event_recorded' => (int) $db->select('famtastic_event', 'e')
      ->condition('event_type', 'project.revision_requested')
      ->condition('project_id', (int) $project->id())->countQuery()->execute()->fetchField() === 1,
  ];

  // --- Step 9c: exhausted allowance gates with the paid add-on requirement --
  $gated = $pipeline->approval($apiRequest((string) json_encode(['action' => 'request_revision', 'note' => 'Second synthetic round.']), $rawToken));
  $gatedPayload = json_decode((string) $gated->getContent(), TRUE);
  $offerAmount = (int) ($ledger->activeOffer('revision_addon_75')['amount_minor'] ?? 0);
  $checks['exhausted_allowance_gated_402'] = $gated->getStatusCode() === 402
    && ($gatedPayload['error'] ?? '') === 'revision_addon_required'
    && str_contains((string) ($gatedPayload['message'] ?? ''), '$75.00');

  // --- Step 9d: purchase one additional revision via the stub gateway -------
  $terms = $ledger->activeTerms();
  $checkoutBody = (string) json_encode(['terms_accepted' => TRUE, 'terms_checksum' => (string) ($terms['checksum'] ?? '')]);
  $checkoutRequest = static function (string $bodyJson) use ($rawToken): Request {
    $request = Request::create('/api/pipeline/revision-checkout', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $bodyJson);
    $request->headers->set('X-Prospect-Token', $rawToken);
    return $request;
  };
  $checkout = $pipeline->revisionCheckout($checkoutRequest($checkoutBody));
  $checkoutPayload = json_decode((string) $checkout->getContent(), TRUE);
  $orderId = 0;
  if (($checkoutPayload['ok'] ?? FALSE) === TRUE) {
    $orderRow = $db->select('famtastic_order', 'o')->fields('o')
      ->condition('prospect_ref', (int) $prospect->id())
      ->condition('package', 'revision_addon_75')
      ->orderBy('id', 'DESC')->range(0, 1)->execute()->fetchAssoc();
    $orderId = (int) ($orderRow['id'] ?? 0);
    $outboxRow = static fn (string $key): ?array => $db->select('famtastic_notification_outbox', 'n')->fields('n')
      ->condition('notification_key', $key)->execute()->fetchAssoc() ?: NULL;
  }
  $adminEmail = trim((string) (\Drupal::configFactory()->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fitzgerald.medine@gmail.com'));
  $ownerAlertKey = 'revision_addon:' . $orderId . ':staff-sale';
  $receiptKey = 'revision_addon:' . $orderId . ':customer-receipt';
  $checks += [
    'addon_checkout_stub_gateway' => ($checkoutPayload['ok'] ?? FALSE) === TRUE
      && ($checkoutPayload['gateway_mode'] ?? '') === 'stub'
      && str_starts_with((string) ($checkoutPayload['session_id'] ?? ''), 'cs_test_stub_')
      && str_contains((string) ($checkoutPayload['url'] ?? ''), '/p/' . $rawToken . '/proof?revision_addon=success'),
    'addon_order_pending_with_consent' => $orderId > 0
      && (int) ($checkoutPayload['amount'] ?? 0) === $offerAmount && $offerAmount > 0
      && ($db->select('famtastic_order', 'o')->fields('o', ['payment_status'])
        ->condition('id', $orderId)->execute()->fetchField()) === 'pending'
      && (int) $db->select('famtastic_consent', 'c')->condition('prospect_id', (int) $prospect->id())
        ->condition('consent_type', 'revision_addon_terms')->countQuery()->execute()->fetchField() >= 1,
    'duplicate_checkout_blocked' => $pipeline->revisionCheckout($checkoutRequest($checkoutBody))->getStatusCode() === 409,
  ];

  // --- Step 9e: payment confirmed through the webhook fulfillment path ------
  $paid = $fulfillment->markPaidBySession(
    (string) ($checkoutPayload['session_id'] ?? ''),
    'pi_test_stub_' . $orderId,
    'evt_e2e_revision_' . $runId,
  );
  $limitAfter = (int) $project->id() > 0 ? (int) $db->select('famtastic_project', 'p')->fields('p', ['revision_limit'])
    ->condition('id', (int) $project->id())->execute()->fetchField() : 0;
  $fulfillmentEvent = $db->select('famtastic_event', 'e')->fields('e', ['payload'])
    ->condition('event_type', 'revision_addon.fulfilled')
    ->condition('order_id', $orderId)->execute()->fetchAssoc();
  $fulfillmentPayload = $fulfillmentEvent ? json_decode((string) $fulfillmentEvent['payload'], TRUE) : [];
  $ownerAlert = $outboxRow ? $outboxRow($ownerAlertKey) : NULL;
  $receipt = $outboxRow ? $outboxRow($receiptKey) : NULL;
  $checks += [
    'fulfillment_paid_once_through_webhook_path' => ($paid['found'] ?? FALSE) === TRUE
      && ($paid['newly_processed'] ?? FALSE) === TRUE
      && ($paid['paid'] ?? FALSE) === TRUE,
    'revision_limit_incremented' => $limitAfter === 2
      && ((int) ($fulfillmentPayload['old_revision_limit'] ?? 0)) === 1
      && ((int) ($fulfillmentPayload['new_revision_limit'] ?? 0)) === 2,
    'owner_notified_with_receipt' => $ownerAlert !== NULL
      && $ownerAlert['status'] === 'queued'
      && $ownerAlert['category'] === 'operational'
      && mb_strtolower((string) $ownerAlert['recipient']) === mb_strtolower($adminEmail)
      && str_contains((string) $ownerAlert['subject'], 'Additional revision purchased')
      && $receipt !== NULL
      && $receipt['status'] === 'queued'
      && $receipt['category'] === 'transactional'
      && mb_strtolower((string) $receipt['recipient']) === $email,
  ];

  // --- Step 9f: notifications deliver through the memory transport ----------
  // A dirty dev database can hold unrelated queued rows; keep dispatching
  // until both synthetic rows drain (bounded), like e2e-mail-visibility parks.
  $sentOwnerAlert = [];
  for ($pass = 0; $pass < 6; $pass++) {
    \Drupal::service('famtastic_pipeline.lifecycle_operations')->dispatchNotifications(50);
    $sentOwnerAlert = $outboxRow($ownerAlertKey) ?? [];
    if (($sentOwnerAlert['status'] ?? '') === 'sent') {
      break;
    }
  }
  $sentReceipt = $outboxRow($receiptKey);
  $checks['notifications_delivered_memory_transport'] = ($sentOwnerAlert['status'] ?? '') === 'sent'
    && ($sentReceipt['status'] ?? '') === 'sent'
    && str_starts_with((string) ($sentOwnerAlert['provider_message_id'] ?? ''), '<famtastic-test-');

  // --- Step 9g: the re-proof lands as version 2, version 1 preserved --------
  [$campaignV2, $variantsV2] = $makeCampaign($campaignKeyV2, '/e2e/' . MARKER . '/v2');
  $portal->attachWebsiteRequestProof($requestId, $campaignV2, $variantsV2);
  $rowsAfterReproof = $versionRows();
  $v2 = $rowsAfterReproof[1] ?? [];
  $projectAfter = $db->select('famtastic_project', 'p')->fields('p')
    ->condition('id', (int) $project->id())->execute()->fetchAssoc();
  $checks += [
    'reproof_tracked_as_new_version' => count($rowsAfterReproof) === 2
      && (int) ($v2['version'] ?? 0) === 2
      && ($v2['source'] ?? '') === 'revision'
      && (int) ($v2['revision_number'] ?? 0) === 1
      && ($v2['campaign_key'] ?? '') === $campaignKeyV2
      && str_contains((string) ($v2['revision_notes'] ?? ''), 'soften the colors'),
    'version_one_byte_identical_after_reproof' => ($rowsAfterReproof[0]['artifact_checksum'] ?? '') === $v1Snapshot['checksum']
      && (int) $rowsAfterReproof[0]['recorded_at'] === $v1Snapshot['recorded_at']
      && ($rowsAfterReproof[0]['campaign_key'] ?? '') === $campaignKeyV1,
    'project_ready_for_next_review' => ($projectAfter['delivery_status'] ?? '') === 'proof_ready'
      && ($projectAfter['approval_status'] ?? '') === 'pending'
      && ($projectAfter['proof_url'] ?? '') === '/e2e/' . MARKER . '/v2/a',
  ];

  // --- Evidence ---------------------------------------------------------------
  if (in_array(FALSE, $checks, TRUE)) {
    throw new RuntimeException('Revision loop acceptance failed: ' . json_encode($checks));
  }
  $evidenceDir = (string) getenv('FAMTASTIC_REVISION_LOOP_EVIDENCE_DIR');
  if ($evidenceDir === '' || (!is_dir($evidenceDir) && !mkdir($evidenceDir, 0770, TRUE))) {
    throw new RuntimeException('Evidence directory unavailable');
  }
  $evidence = [
    'schema' => 'famtastic.revision-loop.v1',
    'status' => 'passed',
    'run_id' => $runId,
    'gateway_mode' => 'stub',
    'checks' => $checks,
    'records' => [
      'prospect_id' => (int) $prospect->id(),
      'project_id' => (int) $project->id(),
      'add_on_order_id' => $orderId,
      'website_request_public_id' => $requestPublicId,
      'proof_versions' => array_map(static fn (array $row): array => [
        'version' => (int) $row['version'],
        'source' => (string) $row['source'],
        'revision_number' => (int) $row['revision_number'],
        'campaign_key' => (string) $row['campaign_key'],
        'artifact_checksum' => (string) $row['artifact_checksum'],
      ], $rowsAfterReproof),
    ],
    'notifications' => [
      'owner_alert' => $ownerAlertKey,
      'customer_receipt' => $receiptKey,
    ],
    'generated_at' => gmdate(DATE_ATOM),
  ];
  file_put_contents($evidenceDir . '/evidence.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
  echo "PASS: revision requested -> add-on purchased (stub) -> revision_limit incremented -> owner notified; proof versions 1 and 2 stored with prior preserved.\n";
  echo 'Evidence: ' . $evidenceDir . "/evidence.json\n";
}
finally {
  try {
    $cleanup();
  }
  finally {
    $switcher->switchBack();
  }
}
