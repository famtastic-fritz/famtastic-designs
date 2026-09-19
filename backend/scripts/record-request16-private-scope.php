<?php

declare(strict_types=1);

// Owner-approved offer record only. No order, payment, email or build.
if (PHP_SAPI !== 'cli') throw new RuntimeException('CLI only');
require_once __DIR__ . '/../web/modules/custom/famtastic_pipeline/src/Service/OfflinePrepaymentService.php';
use Drupal\famtastic_pipeline\Service\OfflinePrepaymentService;

$mode = getenv('FAMTASTIC_REUNION_SCOPE_MODE') ?: 'dry-run';
if (!in_array($mode, ['dry-run', 'apply'], TRUE)) throw new RuntimeException('Unknown mode');
if ($mode === 'apply' && getenv('FAMTASTIC_REUNION_SCOPE_CONFIRM') !== 'request16:customer14:USD199:offer-only') throw new RuntimeException('Exact confirmation required');
$publicId = '8000fc68-aae3-4f40-a3de-2251bd09076a';
$offerId = OfflinePrepaymentService::offerId('reunion-private-scope:' . $publicId);
$scope = [
  'version' => 'class-of-2000-private-scope-v1', 'sku' => 'PRIVATE-REUNION16-199',
  'title' => 'Class of 2000 — private alumni reunion website scope',
  'amount_minor' => 19900, 'currency' => 'USD', 'billing' => 'one_time',
  'source' => 'Fritz Medine explicit owner instructions; not customer-supplied answers',
  'included' => [
    'Three directions: Hi-Tide Legacy, Miami After Dark, Class of 2000: Then & Now',
    'Selected-direction staging and agreed launch build',
    'Event information, RSVP, tickets/sponsorship and alumni access',
    'Dinner preferences, memories, memorial/time capsule, committee administration and help',
    'Reuse verified components without Class of 1996 records, credentials or private assets',
    'Reasonable launch work and costs to the agreed current-reunion functionality',
    'Private project communication assigned to Fritz; no guaranteed instant replies',
  ],
  'awaiting_customer_confirmation' => ['dates', 'venue', 'ticket prices', 'photos', 'committee details', 'domain choice'],
  'payment_policy' => [
    'exception' => 'Offer payment after direction selection toward confirmed domain/hosting provisioning and final live integrations',
    'proofs_or_staging_require_domain_purchase' => FALSE,
    'selection_is_acceptance_or_launch' => FALSE,
    'ordinary_satisfaction_before_payment_policy_changed' => FALSE,
  ],
  'cost_boundary' => 'Existing services and standard-domain options first; unusual costs and paid subscriptions need explicit approval. Not unlimited future work.',
  'renewal_terms' => NULL, 'recurring_authorized' => FALSE,
  'legacy_product_terms_inherited' => FALSE,
  'checkout_activation' => 'not_enabled_by_offer_record; exact private scope checkout must be separately validated after selection',
];
$hash = hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
$db = Drupal::database();
$outer = $db->startTransaction();
try {
  $request = $db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $publicId)->forUpdate()->execute()->fetchAssoc();
  $customer = $db->select('famtastic_customer', 'c')->fields('c')->condition('id', 14)->execute()->fetchAssoc();
  OfflinePrepaymentService::assertIdentity($request ?: [], $customer ?: [], 14, 'mbshclassof2000@gmail.com');
  if ((int) $request['organization_id'] !== 14 || (int) $request['prospect_id'] !== 298 || !empty($request['commerce_order_id'])) throw new RuntimeException('Reunion binding changed');
  $beforeBrief = hash('sha256', (string) $request['intake_data']);
  $eventKey = 'private-scope:request:16:class-of-2000-v1';
  $existing = $db->select('famtastic_private_offer', 'o')->fields('o')->condition('public_id', $offerId)->execute()->fetchAssoc();
  $existingEvent = $db->select('famtastic_event', 'e')->fields('e', ['payload'])->condition('event_key', $eventKey)->execute()->fetchField();
  if ($existing) {
    $event = json_decode((string) $existingEvent, TRUE, 512, JSON_THROW_ON_ERROR);
    if ((int) $existing['website_request_id'] !== 16 || (int) $existing['customer_id'] !== 14 || (int) $existing['offered_amount_minor'] !== 19900
      || $existing['sku'] !== $scope['sku'] || ($event['scope_hash'] ?? '') !== $hash) throw new RuntimeException('Existing reunion offer conflicts');
  }
  else {
    if ($existingEvent || $db->select('famtastic_private_offer', 'o')->condition('website_request_id', 16)->countQuery()->execute()->fetchField()) throw new RuntimeException('Existing reunion scope requires reconciliation');
    $db->insert('famtastic_private_offer')->fields([
      'public_id' => $offerId, 'website_request_id' => 16, 'organization_id' => 14, 'customer_id' => 14,
      'sku' => $scope['sku'], 'list_amount_minor' => 19900, 'offered_amount_minor' => 19900, 'currency' => 'usd',
      'reason' => 'Private $199 alumni reunion scope approved by Fritz. One-time; no inherited legacy renewal or recurring authorization. Payment may be offered after direction selection; domain purchase is not required for proofs/staging.',
      'status' => 'active', 'expires_at' => NULL, 'created_by_uid' => 0, 'commerce_order_id' => NULL,
      'accepted_at' => NULL, 'created' => time(), 'changed' => time(),
    ])->execute();
    Drupal::service('famtastic_pipeline.operational_ledger')->recordEvent($eventKey, 'commerce.private_scope_offered', [
      'request_id' => 16, 'customer_id' => 14, 'organization_id' => 14, 'offer_public_id' => $offerId,
      'scope' => $scope, 'scope_hash' => $hash, 'authority' => 'Fritz Medine explicit approval',
      'automation_identity' => 'codex:request17-commerce', 'source_brief_sha256' => $beforeBrief,
      'original_brief_changed' => FALSE, 'order_created' => FALSE, 'payment_created' => FALSE,
    ], 298);
  }
  $thread = (new OfflinePrepaymentService())->ensureProjectConversation($publicId, 14, 'mbshclassof2000@gmail.com');
  if ($thread['public_id'] !== '7c998b28-f920-4783-a3d4-58ee3782f3fe') throw new RuntimeException('Reunion conversation must reuse thread20');
  $afterBrief = (string) $db->select('famtastic_project_request', 'r')->fields('r', ['intake_data'])->condition('id', 16)->execute()->fetchField();
  if (!hash_equals($beforeBrief, hash('sha256', $afterBrief))) throw new RuntimeException('Original brief changed');
  $receipt = ['mode' => $mode, 'existing' => (bool) $existing, 'request_id' => 16, 'customer_id' => 14,
    'offer_public_id' => $offerId, 'sku' => $scope['sku'], 'amount' => '199.00', 'currency' => 'USD',
    'scope_hash' => $hash, 'audit_event_key' => $eventKey, 'renewal_terms' => NULL,
    'recurring_authorized' => FALSE, 'order_created' => FALSE, 'payment_created' => FALSE,
    'email_queued' => FALSE, 'brief_unchanged' => TRUE, 'conversation' => $thread];
  if ($mode === 'dry-run') $outer->rollBack(); else unset($outer);
  echo json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
catch (Throwable $error) { if (isset($outer)) $outer->rollBack(); throw $error; }
