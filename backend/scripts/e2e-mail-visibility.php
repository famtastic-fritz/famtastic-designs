<?php

declare(strict_types=1);

/**
 * Local-only e2e for AUTONOMOUS_CUSTOMER_SERVICE Phase A4/A5/A6.
 *
 * Asserts the replies metric renders inbound messages (A4), proof selection
 * and revision requests enqueue owner plus customer outbox rows that deliver
 * through the existing pipeline using the memory transport (A5), and the
 * operations attention banner appears only when dead-letter/retry/queue-age
 * conditions demand it (A6). Every synthetic artifact is cleaned up,
 * including leftovers from earlier failed runs, so repeat runs are idempotent.
 */

use Drupal\user\Entity\User;

const MARKER = 'mail-visibility';

$runId = (string) (getenv('FAMTASTIC_SYNTHETIC_RUN_ID') ?: 'local');
$email = MARKER . '@example.invalid';
$requestPublicId = substr(md5(MARKER . ':r1'), 0, 8) . '-' . substr(md5(MARKER . ':r2'), 0, 4) . '-4' . substr(md5(MARKER . ':r3'), 0, 3) . '-a' . substr(md5(MARKER . ':r4'), 0, 3) . '-' . str_pad(substr(md5(MARKER . ':r5'), 0, 12), 12, '0');
$inboundHashes = [
  hash('sha256', '<e2e-' . MARKER . '-matched@example.invalid>'),
  hash('sha256', '<e2e-' . MARKER . '-unmatched@example.invalid>'),
];
$threadPublicId = substr(md5(MARKER . ':thread'), 0, 16);
$campaignKey = 'e2e-' . MARKER . '-proof';
$now = \Drupal::time()->getRequestTime();
$db = \Drupal::database();

/** Removes every synthetic artifact this script can create. */
$cleanup = static function () use ($db, $email, $requestPublicId, $inboundHashes, $campaignKey): void {
  $requestId = (int) $db->select('famtastic_project_request', 'r')
    ->fields('r', ['id'])
    ->condition('public_id', $requestPublicId)
    ->execute()
    ->fetchField();
  $db->delete('famtastic_notification_outbox')
    ->condition('notification_key', 'e2e-' . MARKER . ':%', 'LIKE')
    ->execute();
  if ($requestId > 0) {
    $db->delete('famtastic_notification_outbox')
      ->condition('notification_key', 'website-request:' . $requestId . ':%', 'LIKE')
      ->execute();
  }
  $db->delete('famtastic_inbound_message')
    ->condition('message_id_hash', $inboundHashes, 'IN')
    ->execute();

  $customerId = (int) $db->select('famtastic_customer', 'c')
    ->fields('c', ['id'])
    ->condition('email', $email)
    ->execute()
    ->fetchField();
  if ($customerId > 0) {
    $organizationIds = array_map('intval', $db->select('famtastic_membership', 'm')
      ->fields('m', ['organization_id'])
      ->condition('m.customer_id', $customerId)
      ->execute()
      ->fetchCol());
    if ($organizationIds !== []) {
      $db->delete('famtastic_portal_activity')->condition('organization_id', $organizationIds, 'IN')->execute();
      $db->delete('famtastic_customer_resource')->condition('organization_id', $organizationIds, 'IN')->execute();
      $db->delete('famtastic_organization')->condition('id', $organizationIds, 'IN')->execute();
    }
    $db->delete('famtastic_membership')->condition('customer_id', $customerId)->execute();
    $db->delete('famtastic_customer')->condition('id', $customerId)->execute();
  }

  $campaignStorage = \Drupal::entityTypeManager()->getStorage('proof_campaign');
  $variantStorage = \Drupal::entityTypeManager()->getStorage('proof_variant');
  $campaignIds = $campaignStorage->getQuery()
    ->accessCheck(FALSE)
    ->condition('campaign_id', $campaignKey)
    ->execute();
  foreach ($campaignStorage->loadMultiple($campaignIds) as $campaign) {
    $variantIds = $variantStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('campaign_id', (int) $campaign->id())
      ->execute();
    if ($variantIds !== []) {
      $variantStorage->delete($variantStorage->loadMultiple($variantIds));
    }
  }
  if ($campaignIds !== []) {
    $campaignStorage->delete($campaignStorage->loadMultiple($campaignIds));
  }

  $prospectStorage = \Drupal::entityTypeManager()->getStorage('famtastic_prospect');
  $prospectIds = $prospectStorage->getQuery()
    ->accessCheck(FALSE)
    ->condition('public_email', $email)
    ->execute();
  if ($prospectIds !== []) {
    $prospectStorage->delete($prospectStorage->loadMultiple($prospectIds));
  }

  if ($requestId > 0) {
    $db->delete('famtastic_project_request')->condition('id', $requestId)->execute();
  }

  foreach (\Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail' => $email]) as $user) {
    $user->delete();
  }
};

/** Renders one controller page through the theme root. */
$render = static fn(array $build): string => (string) \Drupal::service('renderer')->renderRoot($build);

$cleanup();
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(\Drupal::entityTypeManager()->getStorage('user')->load(1));

/**
 * Pre-existing attention-state outbox rows are parked as superseded so the
 * banner-absent branch is provable even on a dirty dev database; they are
 * restored verbatim during cleanup.
 */
$parkedStatuses = [];
try {
  foreach ($db->select('famtastic_notification_outbox', 'n')
    ->fields('n', ['id', 'status'])
    ->condition('status', ['queued', 'retry', 'dead_letter'], 'IN')
    ->execute()
    ->fetchAll(\PDO::FETCH_ASSOC) as $existingRow) {
    $parkedStatuses[(int) $existingRow['id']] = (string) $existingRow['status'];
  }
  if ($parkedStatuses !== []) {
    $db->update('famtastic_notification_outbox')
      ->fields(['status' => 'superseded'])
      ->condition('id', array_keys($parkedStatuses), 'IN')
      ->execute();
  }

  // --- Synthetic fixture graph -------------------------------------------
  $prospect = \Drupal::entityTypeManager()->getStorage('famtastic_prospect')->create([
    'business_name' => 'Mail Visibility Business',
    'campaign' => 'public_quote',
    'status' => 'acknowledged',
    'public_email' => $email,
  ]);
  $prospect->save();

  $user = User::create(['name' => $email, 'mail' => $email, 'status' => 1]);
  $user->setPassword('Synthetic-Mail-Visibility-Pass!');
  $user->save();
  $portal = \Drupal::service('famtastic_pipeline.customer_portal');
  $customer = $portal->createCustomer($user, [
    'name' => 'Mail Visibility Customer',
    'business_name' => 'Mail Visibility Business',
    'source' => 'synthetic',
  ]);
  $organization = $portal->organizations((int) $customer['id'])[0];

  $campaign = \Drupal::entityTypeManager()->getStorage('proof_campaign')->create([
    'campaign_id' => $campaignKey,
    'prospect_id' => (int) $prospect->id(),
    'business_name' => 'Mail Visibility Business',
    'generation_status' => 'ready',
    'status' => 'active',
  ]);
  $campaign->save();
  \Drupal::entityTypeManager()->getStorage('proof_variant')->create([
    'campaign_id' => (int) $campaign->id(),
    'direction_id' => 'a',
    'direction_name' => 'Safe',
    'preview_url' => '/e2e-mail-visibility/proof-a',
  ])->save();

  $db->insert('famtastic_project_request')->fields([
    'public_id' => $requestPublicId,
    'organization_id' => (int) $organization['id'],
    'customer_id' => (int) $customer['id'],
    'prospect_id' => (int) $prospect->id(),
    'status' => 'submitted',
    'project_name' => 'Mail Visibility Website',
    'project_type' => 'new_website',
    'domain_choice' => 'undecided',
    'intake_data' => '{}',
    'submitted_at' => $now,
    'proof_campaign_id' => (int) $campaign->id(),
    'proof_review_status' => 'notified',
    'created' => $now,
    'changed' => $now,
  ])->execute();
  $requestId = (int) $db->select('famtastic_project_request', 'r')
    ->fields('r', ['id'])
    ->condition('public_id', $requestPublicId)
    ->execute()
    ->fetchField();

  // --- A4: replies metric visibility --------------------------------------
  $db->insert('famtastic_inbound_message')->fields([
    'message_id_hash' => $inboundHashes[0],
    'thread_public_id' => $threadPublicId,
    'sender_hash' => hash('sha256', $email),
    'subject' => 'Re: Synthetic mailbox visibility check',
    'body' => 'Matched synthetic reply body for the operations replies list.',
    'attachment_manifest' => '[]',
    'status' => 'matched',
    'rejection_reason' => '',
    'received_at' => $now,
    'created' => $now,
  ])->execute();
  $db->insert('famtastic_inbound_message')->fields([
    'message_id_hash' => $inboundHashes[1],
    'thread_public_id' => '',
    'sender_hash' => hash('sha256', 'stranger@example.invalid'),
    'subject' => 'Unmatched synthetic reply',
    'body' => 'Unmatched synthetic reply body.',
    'attachment_manifest' => '[]',
    'status' => 'unmatched',
    'rejection_reason' => 'thread_not_found',
    'received_at' => $now - 60,
    'created' => $now,
  ])->execute();

  $routeMatch = \Drupal::service('router')->match('/admin/famtastic/metric/replies');
  $controller = \Drupal::classResolver()->getInstanceFromDefinition(\Drupal\famtastic_pipeline\Controller\OperationsController::class);
  $repliesPage = $render($controller->metric('replies'));
  $checks = [
    'route_replies_matches' => ($routeMatch['_route'] ?? '') === 'famtastic_pipeline.operations_metric',
    'replies_shows_sender_subject_match_received' => str_contains($repliesPage, 'no reply can be silently lost')
      && str_contains($repliesPage, 'Match status')
      && str_contains($repliesPage, 'Re: Synthetic mailbox visibility check')
      && str_contains($repliesPage, 'Mail Visibility Customer · ' . $email)
      && str_contains($repliesPage, '>matched<')
      && str_contains($repliesPage, '>unmatched<')
      && str_contains($repliesPage, 'thread not found')
      && str_contains($repliesPage, 'Unknown sender (')
      && str_contains($repliesPage, 'Received'),
  ];

  // --- A5: proof decision notifications ------------------------------------
  $portal->decideWebsiteRequestProof((int) $customer['id'], $requestPublicId, ['action' => 'select', 'direction' => 'a']);
  $portal->decideWebsiteRequestProof((int) $customer['id'], $requestPublicId, ['action' => 'revision', 'notes' => 'Please soften the hero colors (synthetic revision).']);

  /** Loads one synthetic outbox row by exact notification key suffix. */
  $outboxRow = static function (string $suffix) use ($db, $requestId): ?array {
    return $db->select('famtastic_notification_outbox', 'n')
      ->fields('n')
      ->condition('notification_key', 'website-request:' . $requestId . ':' . $suffix)
      ->execute()
      ->fetchAssoc() ?: NULL;
  };
  /** Loads synthetic outbox rows by notification key prefix. */
  $outboxRows = static function (string $pattern) use ($db, $requestId): array {
    return $db->select('famtastic_notification_outbox', 'n')
      ->fields('n')
      ->condition('notification_key', 'website-request:' . $requestId . ':' . $pattern, 'LIKE')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  };
  $adminEmail = trim((string) (\Drupal::configFactory()->get('famtastic_pipeline.settings')->get('notification_to_email') ?: 'fitzgerald.medine@gmail.com'));
  $ownerSelectRow = $outboxRow('owner-proof-selected:a');
  $customerSelectAckRow = $outboxRow('customer-proof-selected:a');
  $revisionOwnerRows = $outboxRows('proof-revision:%');
  $revisionAckRows = $outboxRows('customer-revision-ack:%');

  $checks += [
    'select_produced_owner_row' => $ownerSelectRow !== NULL
      && $ownerSelectRow['status'] === 'queued'
      && $ownerSelectRow['category'] === 'operational'
      && mb_strtolower((string) $ownerSelectRow['recipient']) === mb_strtolower($adminEmail)
      && str_contains((string) $ownerSelectRow['subject'], 'Customer selected proof A'),
    'select_produced_customer_ack_row' => $customerSelectAckRow !== NULL
      && $customerSelectAckRow['status'] === 'queued'
      && $customerSelectAckRow['category'] === 'transactional'
      && mb_strtolower((string) $customerSelectAckRow['recipient']) === $email
      && str_contains((string) $customerSelectAckRow['subject'], 'We received your website direction choice')
      && str_contains((string) $customerSelectAckRow['body'], 'FAMtastic Concierge'),
    'revision_produced_owner_and_ack_rows' => count($revisionOwnerRows) === 1
      && reset($revisionOwnerRows)['status'] === 'queued'
      && count($revisionAckRows) === 1
      && reset($revisionAckRows)['status'] === 'queued'
      && mb_strtolower((string) reset($revisionAckRows)['recipient']) === $email,
  ];

  // --- A6 (zero branch): banner must stay hidden while delivery is healthy --
  $bannerAbsent = !str_contains($render($controller->dashboard()), 'famtastic-ops__attention-banner')
    && !str_contains($render($controller->metric('notifications')), 'famtastic-ops__attention-banner')
    && !str_contains($repliesPage, 'famtastic-ops__attention-banner');
  $checks['banner_absent_when_healthy'] = $bannerAbsent;

  // --- A5 delivery: existing pipeline dispatch through memory transport -----
  $delivery = \Drupal::service('famtastic_pipeline.lifecycle_operations')->dispatchNotifications(25);
  /** Re-reads one synthetic outbox row after dispatch. */
  $sentRow = static function (?array $row) use ($db): array {
    if ($row === NULL) {
      return [];
    }
    return $db->select('famtastic_notification_outbox', 'n')
      ->fields('n')
      ->condition('id', (int) $row['id'])
      ->execute()
      ->fetchAssoc() ?: [];
  };
  $delivered = [
    $sentRow($ownerSelectRow),
    $sentRow($customerSelectAckRow),
    $sentRow($revisionOwnerRows[0] ?? NULL),
    $sentRow($revisionAckRows[0] ?? NULL),
  ];
  $allSent = count(array_filter($delivered, static fn(array $row): bool =>
    ($row['status'] ?? '') === 'sent'
    && (int) ($row['attempts'] ?? 0) >= 1
    && str_starts_with((string) ($row['provider_message_id'] ?? ''), '<famtastic-test-'))) === 4;
  $capturePath = trim((string) getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE'));
  $capturedSubjects = [];
  if ($capturePath !== '' && is_file($capturePath)) {
    foreach (file($capturePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
      $record = json_decode((string) $line, TRUE);
      if (is_array($record)) {
        $capturedSubjects[] = (string) ($record['subject'] ?? '');
      }
    }
  }
  $expectedSubjects = [
    (string) ($ownerSelectRow['subject'] ?? 'owner-select-missing'),
    (string) ($customerSelectAckRow['subject'] ?? 'ack-select-missing'),
    (string) ($revisionOwnerRows[0]['subject'] ?? 'owner-revision-missing'),
    (string) ($revisionAckRows[0]['subject'] ?? 'ack-revision-missing'),
  ];
  $subjectsSeen = static fn(string $needle): bool => in_array($needle, $capturedSubjects, TRUE);
  $checks += [
    'dispatch_sent_all_four_rows' => $delivery['sent'] >= 4 && $allSent,
    'memory_capture_receipts_complete' => count($capturedSubjects) >= 4
      && $subjectsSeen($expectedSubjects[0])
      && $subjectsSeen($expectedSubjects[1])
      && $subjectsSeen($expectedSubjects[2])
      && $subjectsSeen($expectedSubjects[3]),
  ];

  // --- A6 (non-zero branches): stale queue age, then dead letter ------------
  $fixtureBase = [
    'category' => 'operational',
    'recipient' => 'e2e-mail-visibility@example.invalid',
    'subject' => 'Synthetic banner fixture',
    'body' => 'Synthetic banner fixture.',
    'status' => 'queued',
    'attempts' => 0,
    'max_attempts' => 1,
    'available_at' => $now,
    'created' => $now - 3600,
    'changed' => $now - 3600,
  ];
  $db->merge('famtastic_notification_outbox')->key('notification_key', 'e2e-' . MARKER . ':stale')->insertFields($fixtureBase + [
    'notification_key' => 'e2e-' . MARKER . ':stale',
  ])->execute();
  $staleDashboard = $render($controller->dashboard());
  $checks['banner_present_on_stale_queue_age'] = str_contains($staleDashboard, 'famtastic-ops__attention-banner')
    && str_contains($staleDashboard, 'Needs attention')
    && str_contains($staleDashboard, 'waiting 60 minutes');

  $db->update('famtastic_notification_outbox')
    ->fields(['status' => 'dead_letter', 'last_error' => 'synthetic dead letter'])
    ->condition('notification_key', 'e2e-' . MARKER . ':stale')
    ->execute();
  $deadNotificationsPage = $render($controller->metric('notifications'));
  $checks['banner_present_on_dead_letter'] = str_contains($deadNotificationsPage, 'famtastic-ops__attention-banner')
    && str_contains($deadNotificationsPage, 'dead-lettered');

  // --- Evidence --------------------------------------------------------------
  if (in_array(FALSE, $checks, TRUE)) {
    throw new RuntimeException('Mail visibility acceptance failed: ' . json_encode($checks));
  }
  $evidenceDir = (string) getenv('FAMTASTIC_MAIL_VISIBILITY_EVIDENCE_DIR');
  if ($evidenceDir === '' || !is_dir($evidenceDir)) {
    throw new RuntimeException('Evidence directory unavailable');
  }
  $evidence = [
    'schema' => 'famtastic.mail-visibility.v1',
    'status' => 'passed',
    'run_id' => $runId,
    'checks' => $checks,
    'records' => [
      'website_request_public_id' => $requestPublicId,
      'notification_keys' => array_map(static fn(?array $row): string => (string) ($row['notification_key'] ?? ''), [$ownerSelectRow, $customerSelectAckRow]),
      'receipts' => $capturePath,
    ],
    'generated_at' => gmdate(DATE_ATOM),
  ];
  file_put_contents($evidenceDir . '/evidence.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
  echo "PASS: replies metric renders inbound rows; proof select/revision produced both owner and customer outbox rows that delivered; attention banner honors zero/stale/dead-letter states.\n";
  echo 'Evidence: ' . $evidenceDir . "/evidence.json\n";
}
catch (\Throwable $error) {
  throw $error;
}
finally {
  try {
    $cleanup();
    foreach ($parkedStatuses as $rowId => $originalStatus) {
      $db->update('famtastic_notification_outbox')
        ->fields(['status' => $originalStatus])
        ->condition('id', $rowId)
        ->execute();
    }
  }
  finally {
    $switcher->switchBack();
  }
}
