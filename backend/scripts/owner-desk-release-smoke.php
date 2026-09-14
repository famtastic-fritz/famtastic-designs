<?php

declare(strict_types=1);

/**
 * Explicitly invoked release QA, via drush php:script. Never a cron/HTTP route.
 *
 * OWNER_DESK_SMOKE_MODE: all (default), binding, transaction, lock-hold,
 * lock-probe. The main release operator reviews and runs this script.
 * All appointment/request/outbox writes are under one outer transaction which
 * is ALWAYS rolled back. Auto-increment sequence gaps may remain on MySQL.
 * Lock modes touch ONLY the reserved synthetic lock and hold for five seconds.
 * No provider transport, user creation, session save, live-site write or mail
 * dispatcher is invoked. Do not execute other jobs in this PHP process.
 */

use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Session\UserSession;
use Drupal\famtastic_pipeline\Controller\CustomerBookingOwnerController;
use Symfony\Component\HttpFoundation\Request;

$mode = getenv('OWNER_DESK_SMOKE_MODE') ?: 'all';
if (!in_array($mode, ['all', 'binding', 'transaction', 'lock-hold', 'lock-probe'], TRUE)) {
  throw new \InvalidArgumentException('Unsupported smoke mode.');
}
$db = \Drupal::database();
$site = 'owner-desk-release-smoke';
$lockName = 'famtastic:appointment:' . $site;
$checks = [];
$check = static function (bool $condition, string $name) use (&$checks): void {
  if (!$condition) {
    throw new \RuntimeException('Smoke assertion failed: ' . $name);
  }
  $checks[$name] = TRUE;
};
$emit = static function (array $extra = []) use (&$checks, $mode): void {
  print json_encode(['schema' => 'famtastic.owner-desk-release-smoke.v1', 'mode' => $mode, 'checks' => $checks, 'provider_calls' => FALSE] + $extra, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
};

if (str_starts_with($mode, 'lock-')) {
  $check(!$db->inTransaction(), 'lock_probe_outside_transaction');
  $lock = \Drupal::service('lock.persistent');
  $acquired = $lock->acquire($lockName, 8.0);
  if ($mode === 'lock-probe') {
    if ($acquired) {
      $lock->release($lockName);
    }
    $check(!$acquired, 'second_process_refused_held_synthetic_lock');
    $emit(['limitation' => 'Proves process lock exclusion only, not simultaneous business transactions.']);
    return;
  }
  $check($acquired, 'synthetic_lock_acquired');
  try {
    print "READY: synthetic lock held for five seconds\n";
    flush();
    sleep(5);
  }
  finally {
    $lock->release($lockName);
  }
  $check($lock->lockMayBeAvailable($lockName), 'synthetic_lock_released');
  $emit();
  return;
}

$portal = \Drupal::service('famtastic_pipeline.customer_portal');
$owners = \Drupal::service('famtastic_pipeline.booking_site_owners');
$appointments = \Drupal::service('famtastic_pipeline.booking_appointments');
if (in_array($mode, ['all', 'binding'], TRUE)) {
  $liveSite = 'site-dffd4cb9c3aa47fd';
  $customer = $portal->customerForUid(11);
  $check($customer !== NULL && (int) $customer['id'] === 11 && !empty($customer['verified_at']), 'expected_verified_owner_uid_customer');
  $binding = $owners->requireCustomerOwner(11, $liveSite);
  $check((int) $binding['customer_id'] === 11 && (int) $binding['organization_id'] === 11, 'expected_live_site_customer_organization_binding');
  $check($owners->forCustomer(3, $liveSite) === NULL, 'fritz_customer_three_denied_by_service');
  $fritz = $portal->customerForId(3);
  $check($fritz !== NULL && (int) $fritz['uid'] > 0, 'fritz_account_resolved_read_only');
  foreach ([11 => 200, (int) $fritz['uid'] => 404] as $uid => $expectedStatus) {
    // Isolated in-memory account proxy: no global account or stored session is
    // switched and no user entity is created/updated.
    $account = new AccountProxy(new \Symfony\Component\EventDispatcher\EventDispatcher());
    $account->setAccount(new UserSession(['uid' => $uid]));
    $controller = new CustomerBookingOwnerController($account, $portal, $owners,
      \Drupal::service('famtastic_pipeline.booking_requests'),
      \Drupal::service('famtastic_pipeline.booking_availability'), $appointments);
    $response = $controller->appointments(Request::create('/release-smoke-read-only', 'GET'), $liveSite);
    $check($response->getStatusCode() === $expectedStatus, $uid === 11 ? 'owner_controller_read_allowed' : 'fritz_controller_read_denied');
    // Deliberately discard payload; never print customer records.
    unset($response, $controller, $account);
  }
}

if (in_array($mode, ['all', 'transaction'], TRUE)) {
  $check(!$db->inTransaction(), 'no_ambient_transaction');
  $tables = ['famtastic_booking_request', 'famtastic_booking_appointment', 'famtastic_booking_appointment_event', 'famtastic_notification_outbox', 'semaphore'];
  foreach ($tables as $table) {
    $check($db->schema()->tableExists($table), 'table_exists_' . $table);
  }
  if ($db->driver() === 'mysql') {
    foreach ($tables as $table) {
      $engine = $db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :name', [':name' => $db->getPrefix() . $table])->fetchField();
      $check(strcasecmp((string) $engine, 'InnoDB') === 0, 'transactional_engine_' . $table);
    }
    $isolationRow = $db->query("SHOW VARIABLES LIKE 'transaction_isolation'")->fetchAssoc();
    if (!$isolationRow) {
      $isolationRow = $db->query("SHOW VARIABLES LIKE 'tx_isolation'")->fetchAssoc();
    }
    $isolation = strtoupper((string) ($isolationRow['Value'] ?? ''));
    $check($isolation !== '', 'transaction_isolation_identified');
    $check($isolation !== 'READ-UNCOMMITTED', 'outbox_cannot_be_dirty_read');
  }
  else {
    $check($db->driver() === 'sqlite', 'supported_transaction_driver');
  }
  $recipient = 'owner-desk-release-smoke@example.invalid';
  $counts = static function () use ($db, $site, $recipient, $lockName): array {
    $result = [];
    foreach (['famtastic_booking_request', 'famtastic_booking_appointment', 'famtastic_booking_appointment_event'] as $table) {
      $result[$table] = (int) $db->select($table, 't')->condition('site_key', $site)->countQuery()->execute()->fetchField();
    }
    $result['famtastic_notification_outbox'] = (int) $db->select('famtastic_notification_outbox', 't')->condition('recipient', $recipient)->countQuery()->execute()->fetchField();
    $result['semaphore'] = (int) $db->select('semaphore', 't')->condition('name', $lockName)->countQuery()->execute()->fetchField();
    return $result;
  };
  $before = $counts();
  $check(array_sum($before) === 0, 'reserved_synthetic_scope_empty');
  $transaction = $db->startTransaction('owner_desk_release_smoke');
  // Defence against a fatal exit before finally. This callback only rolls back
  // this exact owned outer transaction; it never commits or deletes records.
  register_shutdown_function(static function () use (&$transaction): void {
    if ($transaction !== NULL) {
      $transaction->rollBack();
      $transaction = NULL;
    }
  });
  try {
    $now = \Drupal::time()->getRequestTime();
    $ids = [];
    for ($i = 0; $i < 2; $i++) {
      $ids[] = (int) $db->insert('famtastic_booking_request')->fields([
        'public_id' => \Drupal::service('uuid')->generate(), 'site_key' => $site, 'service_key' => 'release-smoke',
        'customer_name' => 'Synthetic release QA', 'email' => $recipient, 'email_hash' => hash('sha256', $recipient),
        'phone' => '', 'requested_window' => 'Synthetic transaction only', 'message' => '', 'consent' => 1,
        'status' => 'new', 'source' => 'release-smoke-rollback', 'created' => $now, 'changed' => $now,
      ])->execute();
    }
    $base = ['action' => 'confirm', 'request_id' => $ids[0], 'starts_at' => $now + 86400, 'ends_at' => $now + 90000,
      'timezone' => 'America/New_York', 'idempotency_key' => 'release-smoke-confirm'];
    // Actor 0 is deliberately synthetic; this service call is not an owner
    // authorization claim. Actual owner authorization was read-only above.
    $confirmed = $appointments->ownerCommand($site, $base, 0);
    $check($confirmed['status'] === 'confirmed', 'synthetic_confirmation_persisted_inside_transaction');
    $check($appointments->ownerCommand($site, $base, 0) === $confirmed, 'idempotent_receipt');
    $conflict = FALSE;
    try {
      $appointments->ownerCommand($site, array_replace($base, ['request_id' => $ids[1], 'idempotency_key' => 'release-smoke-conflict']), 0);
    }
    catch (\RuntimeException $error) {
      $conflict = $error->getMessage() === 'appointment_slot_conflict';
    }
    $check($conflict, 'overlap_refused');
    $proposed = $appointments->ownerCommand($site, ['action' => 'reschedule', 'appointment_id' => $confirmed['id'],
      'expected_revision' => $confirmed['revision'], 'starts_at' => $now + 172800, 'ends_at' => $now + 176400,
      'timezone' => 'America/New_York', 'idempotency_key' => 'release-smoke-proposal'], 0);
    $body = (string) $db->select('famtastic_notification_outbox', 'n')->fields('n', ['body'])
      ->condition('notification_key', 'booking-appointment:' . $confirmed['public_id'] . ':' . $proposed['revision'])->execute()->fetchField();
    preg_match('/#token=([A-Za-z0-9_-]+)/', $body, $token);
    $check(!empty($token[1]), 'proposal_token_queued_as_fragment');
    $accepted = $appointments->respondToProposal($confirmed['public_id'], $token[1], 'accept');
    $check($accepted['status'] === 'confirmed' && $accepted['starts_at'] === $now + 172800, 'proposed_time_accepted');
    $cancelled = $appointments->ownerCommand($site, ['action' => 'cancel', 'appointment_id' => $confirmed['id'],
      'expected_revision' => $accepted['revision'], 'idempotency_key' => 'release-smoke-cancel'], 0);
    $check($cancelled['status'] === 'cancelled', 'cancellation_persisted_inside_transaction');
    $check($db->inTransaction(), 'outer_transaction_still_open');
    $check((int) $db->select('famtastic_notification_outbox', 'n')->condition('recipient', $recipient)
      ->condition('status', ['queued', 'superseded'], 'NOT IN')->countQuery()->execute()->fetchField() === 0, 'no_notification_dispatched');
    unset($token, $body);
  }
  finally {
    $transaction->rollBack();
    $transaction = NULL;
  }
  $check(!$db->inTransaction(), 'outer_transaction_rolled_back');
  $check($counts() === $before, 'synthetic_request_appointment_event_outbox_lock_counts_unchanged');
}
$emit(['status' => 'passed', 'rollback_only' => TRUE, 'live_site_mutations' => FALSE]);
