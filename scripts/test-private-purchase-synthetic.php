<?php

declare(strict_types=1);

/** Fresh native offline proof; never bootstrap this script on a shared site. */
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_payment\Event\FilterPaymentGatewaysEvent;
use Drupal\commerce_price\Price;
use Drupal\commerce_store\Entity\Store;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Form\PrivatePurchaseForm;
use Drupal\famtastic_pipeline\Service\OfflinePrepaymentService;
use Drupal\famtastic_pipeline\Service\PrivatePurchaseService;
use Drupal\famtastic_pipeline\Service\SelectedSourceIntent;
use Drupal\famtastic_private_probe\ProbeBoundary;
use Drupal\famtastic_private_probe\SyntheticPrivatePurchaseAuthority;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

$sandbox = ProbeBoundary::runtime();
if (realpath(__DIR__) !== "$sandbox/scripts") throw new RuntimeException('synthetic_probe_script_location_refused');
$phase = getenv('PRIVATE_SYNTHETIC_PHASE') ?: 'test';
if (!in_array($phase, ['test', 'verify'], TRUE)) throw new RuntimeException('synthetic_probe_phase_refused');
$evidenceDirectory = realpath(getenv('SELECTED_DRUPAL_EVIDENCE') ?: '') ?: '';
if (!preg_match('#/\.artifacts/selected-staging-drupal/[^/]+$#D', $evidenceDirectory)) throw new RuntimeException('synthetic_probe_evidence_location_refused');
$evidence = $evidenceDirectory . '/private-purchase-synthetic.json';
$bindingFile = ProbeBoundary::bindingPath();
$db = \Drupal::database();
$entities = \Drupal::entityTypeManager();
$storage = $entities->getStorage('commerce_order');

// Hash the actually copied application, container declaration and fixture code.
$sourceFiles = ['famtastic_pipeline.services.yml', 'famtastic_pipeline.module',
  'src/Service/PrivatePurchaseAuthorityInterface.php', 'src/Service/ApprovedPrivatePurchaseAuthority.php',
  'src/Service/PrivatePurchaseService.php', 'src/Service/SelectedSourceIntent.php',
  'src/Service/OfflinePrepaymentService.php', 'src/Service/CustomerPortalService.php',
  'src/Service/OperationalLedger.php', 'src/Form/PrivatePurchaseForm.php',
  'src/EventSubscriber/PrivateScopeCheckoutGuard.php'];
$sourceHashes = [];
foreach ($sourceFiles as $file) $sourceHashes['famtastic_pipeline/' . $file] = hash_file('sha256', \Drupal::root() . '/modules/custom/famtastic_pipeline/' . $file);
foreach (ProbeBoundary::FILES as $file) $sourceHashes['famtastic_private_probe/' . $file] = hash_file('sha256', \Drupal::root() . '/modules/custom/famtastic_private_probe/' . $file);
$sourceHashes['scripts/test-private-purchase-synthetic.php'] = hash_file('sha256', __FILE__);
$sourceHashes['scripts/test-selected-staging-drupal.sh'] = hash_file('sha256', "$sandbox/scripts/test-selected-staging-drupal.sh");

$report = ['schema' => 'famtastic.private-purchase-synthetic-native-proof.v1', 'status' => 'running',
  'classification' => 'unverified: execution in progress', 'source_sha' => getenv('SELECTED_DRUPAL_SOURCE_SHA'),
  'tested_source_sha256' => $sourceHashes, 'checks' => [], 'records' => [], 'phases' => [],
  'limits' => ['Fresh disposable SQLite only; no concurrency/MySQL proof.',
    'Actual native entities, Form API build and three guard entry points; no HTTP submission, session, browser or CSRF validation proof.',
    'Key-free Stripe Payment Element configuration only; no gateway payment/provider method, intent, charge, callback, payment, refund or provider proof.',
    'Synthetic test-module authority only; no human approval, production allowlist expansion or checkout activation.',
    'Direct private-form/native boundary only, not portal-link or catalog-exclusion parity.',
    'Placement guard is called without applying a transition; no completed order, receipt or fulfillment claim.']];
if ($phase === 'verify') {
  if (!is_file($evidence)) throw new RuntimeException('synthetic_probe_seed_evidence_missing');
  $report = json_decode(file_get_contents($evidence), TRUE, 512, JSON_THROW_ON_ERROR);
  if (($report['schema'] ?? '') !== 'famtastic.private-purchase-synthetic-native-proof.v1'
    || ($report['status'] ?? '') !== 'seed_passed_awaiting_fresh_process') throw new RuntimeException('synthetic_probe_seed_evidence_refused');
  $report['status'] = 'verifying';
}
elseif (file_exists($evidence) || file_exists($bindingFile)) throw new RuntimeException('synthetic_probe_seed_already_exists');
$GLOBALS['syntheticPrivateReport'] =& $report;
register_shutdown_function(static function () use (&$report, $evidence, $phase): void {
  if (!in_array($report['status'], ['seed_passed_awaiting_fresh_process', 'passed'], TRUE)) $report['status'] = 'failed';
  $report['phases'][$phase] = $report['status'];
  $report['assertion_count'] = count($report['checks']);
  $report['updated_at'] = gmdate(DATE_ATOM);
  $fd = fopen($evidence, 'wb');
  if ($fd === FALSE) throw new RuntimeException('synthetic_probe_evidence_write_failed');
  try {
    chmod($evidence, 0600);
    $bytes = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (fwrite($fd, $bytes) !== strlen($bytes) || !fflush($fd) || !fsync($fd)) throw new RuntimeException('synthetic_probe_evidence_write_failed');
  }
  finally { fclose($fd); }
  print 'Evidence: ' . $evidence . "\n";
});

function ppsCheck(bool $pass, string $key): void {
  if (array_key_exists($key, $GLOBALS['syntheticPrivateReport']['checks'])) throw new RuntimeException('synthetic_probe_duplicate_assertion');
  $GLOBALS['syntheticPrivateReport']['checks'][$key] = $pass;
  if (!$pass) throw new RuntimeException('synthetic_probe_assertion_failed:' . $key);
  print 'PASS: ' . $key . "\n";
}

function ppsReject(callable $action, string $key, string $expected = 'private_'): void {
  try { $action(); }
  catch (Throwable $error) {
    // Only a deliberate contract rejection counts; never accept arbitrary errors.
    ppsCheck(str_contains($error->getMessage(), $expected), $key);
    return;
  }
  ppsCheck(FALSE, $key);
}

function ppsCounts(): array {
  $result = [];
  foreach (['commerce_order', 'commerce_order_item', 'commerce_payment', 'famtastic_private_offer',
    'famtastic_customer_resource', 'famtastic_event', 'famtastic_job', 'famtastic_notification_outbox',
    'famtastic_commerce_fulfillment', 'famtastic_entitlement'] as $table) {
    $result[$table] = (int) \Drupal::database()->select($table, 'x')->countQuery()->execute()->fetchField();
  }
  $result['captured_mail'] = count(\Drupal::state()->get('system.test_mail_collector', []));
  $capture = getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_CAPTURE');
  clearstatcache(TRUE, $capture);
  $result['transactional_capture_bytes'] = is_file($capture) ? filesize($capture) : 0;
  return $result;
}

function ppsRequest(): array {
  return \Drupal::database()->select('famtastic_project_request', 'r')->fields('r')->condition('id', 901)->execute()->fetchAssoc();
}

function ppsInput(PrivatePurchaseService $service, array $binding): array {
  return ['accept_terms' => TRUE, 'terms_version' => $binding['scope']['version'],
    'scope_hash' => $binding['authority']['scope_hash'], 'domain_choice' => 'undecided',
    'selection_snapshot' => $service->selectionSnapshot(ppsRequest())];
}

/** Call the real subscriber's three entry points, never dispatch/place the order. */
function ppsGuards(OrderInterface $order, bool $allow, string $label): void {
  $guard = \Drupal::service('famtastic_pipeline.private_scope_checkout_guard');
  $request = new RequestEvent(\Drupal::service('http_kernel'), Request::create('/checkout/' . $order->id()), HttpKernelInterface::MAIN_REQUEST);
  $gateways = new FilterPaymentGatewaysEvent(['synthetic_private_stripe' => PaymentGateway::load('synthetic_private_stripe')], $order);
  $workflow = $order->getState()->getWorkflow();
  $placement = new WorkflowTransitionEvent($workflow->getTransition('place'), $workflow, $order, 'state');
  if ($allow) {
    $guard->request($request);
    ppsCheck(TRUE, $label . '_request');
  }
  else ppsReject(fn() => $guard->request($request), $label . '_request', 'private checkout is unavailable');
  $guard->gateways($gateways);
  ppsCheck(count($gateways->getPaymentGateways()) === ($allow ? 1 : 0), $label . '_gateways');
  if ($allow) {
    $guard->place($placement);
    ppsCheck(TRUE, $label . '_placement_guard_only');
  }
  else ppsReject(fn() => $guard->place($placement), $label . '_placement_guard_only', 'private scope must be reconciled');
}

new Settings(array_replace(Settings::getAll(), ['famtastic_payment_mode' => 'test', 'famtastic_private_reunion_checkout_enabled' => TRUE]));
if ($phase === 'verify') {
  ppsCheck($report['tested_source_sha256'] === $sourceHashes, 'fresh_process_exact_executed_sources');
  ppsCheck(hash_file('sha256', $bindingFile) === $report['binding_sha256'], 'fresh_process_unchanged_private_binding');
  $binding = SyntheticPrivatePurchaseAuthority::binding();
  $service = \Drupal::service('famtastic_pipeline.private_purchase');
  $owner = User::load($report['records']['owner_uid']);
  $before = ppsCounts();
  $order = $service->startReunion($owner, $binding['authority']['public_id'], ppsInput($service, $binding));
  ppsCheck((int) $order->id() === $report['records']['order_id'], 'fresh_process_reuses_exact_native_order');
  ppsCheck($before === $report['records']['final_counts'] && ppsCounts() === $before, 'fresh_process_no_additional_records_or_mail');
  ppsCheck($order->getState()->getId() === 'draft' && !$order->isPaid()
    && $before['commerce_payment'] === 0 && $before['captured_mail'] === 0 && $before['transactional_capture_bytes'] === 0, 'fresh_process_still_unpaid_without_receipt');
  $service->assertReunionOrder($order);
  ppsCheck(TRUE, 'fresh_process_native_order_authority_valid');
  $report['status'] = 'passed';
  $report['classification'] = 'locally proven: synthetic authority and unpaid native private-purchase boundary only';
  return;
}

ppsCheck(TRUE, 'exact_copied_runtime_sqlite_memory_mail_disabled_php_transports');
ppsCheck(!\Drupal::getContainer()->initialized('famtastic_pipeline.private_purchase')
  && !\Drupal::getContainer()->initialized('famtastic_pipeline.private_purchase_authority'), 'module_install_did_not_initialize_authority');
foreach (['famtastic_customer', 'famtastic_organization', 'famtastic_membership', 'famtastic_project_request',
  'famtastic_request_asset', 'famtastic_private_offer', 'famtastic_customer_resource', 'famtastic_event',
  'famtastic_job', 'famtastic_notification_outbox', 'famtastic_commerce_fulfillment', 'famtastic_entitlement',
  'commerce_order', 'commerce_order_item', 'commerce_payment'] as $table) {
  if ((int) $db->select($table, 'x')->countQuery()->execute()->fetchField() !== 0) throw new RuntimeException('synthetic_probe_nonempty_runtime');
}
foreach (['famtastic_prospect', 'famtastic_project', 'proof_campaign', 'proof_variant', 'commerce_store', 'commerce_payment_gateway'] as $type) {
  if ($entities->getStorage($type)->getQuery()->accessCheck(FALSE)->count()->execute()) throw new RuntimeException('synthetic_probe_nonempty_entities');
}
ppsCheck(!$entities->getStorage('user')->getQuery()->accessCheck(FALSE)->condition('uid', 1, '>')->count()->execute(), 'seed_only_empty_fresh_business_runtime');

$uuid = \Drupal::service('uuid');
$run = $uuid->generate();
$publicId = $uuid->generate();
$scope = ['version' => 'synthetic-private-v1-' . $run, 'sku' => 'PRIVATE-SYNTHETIC-199', 'amount' => '199.00', 'currency' => 'USD',
  'title' => 'Offline synthetic observatory study ' . $run,
  'included' => ['One synthetic observatory information page', 'One fictional equipment list'],
  'boundaries' => ['No booking, ecommerce, hosting, publication or real business deliverable'],
  'cost_boundary' => 'Offline proof only; no external purchase authorized'];
if (!OrderItemType::load('default')) OrderItemType::create(['id' => 'default', 'label' => 'Default', 'purchasableEntityType' => 'commerce_product_variation', 'orderType' => 'default'])->save();
\Drupal::service('commerce_price.currency_importer')->import('USD');
$store = Store::create(['type' => 'online', 'uid' => 1, 'name' => 'Synthetic observatory fixture', 'mail' => "store+$run@probe.test",
  'address' => ['country_code' => 'US', 'address_line1' => '1 Synthetic Lane', 'locality' => 'Fixture', 'administrative_area' => 'FL', 'postal_code' => '00000'],
  'default_currency' => 'USD', 'timezone' => 'UTC', 'status' => TRUE]);
$store->save();
ppsCheck((int) $store->id() === 1, 'fresh_store_preserves_fixed_store_one');
PaymentGateway::create(['id' => 'synthetic_private_stripe', 'label' => 'Offline never-invoked gateway', 'plugin' => 'stripe_payment_element', 'status' => TRUE,
  'configuration' => ['mode' => 'test', 'publishable_key' => '', 'secret_key' => '', 'webhook_signing_secret' => '']])->save();

// Reserve disjoint test-only ranges in empty tables, then use real user hooks.
foreach (['famtastic_customer' => 1000, 'famtastic_organization' => 2000] as $table => $sequence) {
  $exists = $db->select('sqlite_sequence', 's')->condition('name', $table)->countQuery()->execute()->fetchField();
  if ($exists) $db->update('sqlite_sequence')->fields(['seq' => $sequence])->condition('name', $table)->execute();
  else $db->insert('sqlite_sequence')->fields(['name' => $table, 'seq' => $sequence])->execute();
}
$users = [];
foreach (['owner', 'foreign'] as $role) {
  $email = "$role+$run@probe.test";
  $user = User::create(['name' => $email, 'mail' => $email, 'status' => 1, 'pass' => bin2hex(random_bytes(32))]);
  $user->save();
  $users[$role] = $user;
  $customer = \Drupal::service('famtastic_pipeline.customer_portal')->customerForUid((int) $user->id());
  ppsCheck($customer !== NULL && $customer['email'] === $email && (int) $customer['id'] >= 1001, 'native_user_customer_hook_' . $role);
  $db->update('famtastic_customer')->fields(['verified_at' => time()])->condition('id', $customer['id'])->execute();
}
$owner = $users['owner'];
$foreign = $users['foreign'];
$customer = \Drupal::service('famtastic_pipeline.customer_portal')->customerForUid((int) $owner->id());
$membership = $db->select('famtastic_membership', 'm')->fields('m')->condition('customer_id', $customer['id'])->execute()->fetchAssoc();
ppsCheck($membership && (int) $membership['organization_id'] >= 2001 && (int) $membership['organization_id'] !== (int) $customer['id']
  && $membership['role'] === 'owner' && $membership['status'] === 'active', 'native_hook_distinct_customer_organization_owner_binding');
$prospect = $entities->getStorage('famtastic_prospect')->create(['business_name' => 'Synthetic observatory', 'email' => $owner->getEmail()]);
$prospect->save();
$selectedAt = time();
$campaign = $entities->getStorage('proof_campaign')->create(['campaign_id' => 'synthetic-' . $run, 'prospect_id' => $prospect->id(),
  'business_name' => 'Synthetic observatory', 'generation_status' => 'ready', 'selected_variant' => 'a', 'selected_at' => $selectedAt]);
$campaign->save();
$artifactPath = 'private/synthetic-selected-proof-' . $run . '.json';
$artifactFile = "$sandbox/backend/$artifactPath";
$artifact = json_encode(['schema' => 'famtastic.synthetic-selected-proof.v1', 'run_id' => $run, 'scope' => $scope, 'external_work' => FALSE], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
$fd = fopen($artifactFile, 'xb');
if ($fd === FALSE) throw new RuntimeException('synthetic_probe_artifact_exists');
try { chmod($artifactFile, 0600); if (fwrite($fd, $artifact) !== strlen($artifact) || !fflush($fd) || !fsync($fd)) throw new RuntimeException('synthetic_probe_artifact_write_failed'); }
finally { fclose($fd); }
$dna = ['synthetic' => TRUE, 'run_id' => $run];
$variant = $entities->getStorage('proof_variant')->create(['campaign_id' => $campaign->id(), 'direction_id' => 'a', 'direction_name' => 'Synthetic A',
  'artifact_path' => $artifactPath, 'design_dna' => json_encode($dna, JSON_THROW_ON_ERROR)]);
$variant->save();
$project = $entities->getStorage('famtastic_project')->create(['label' => 'Synthetic observatory project', 'prospect_ref' => $prospect->id()]);
$project->save();
$db->insert('famtastic_project_request')->fields(['id' => 901, 'public_id' => $publicId, 'customer_id' => (int) $customer['id'],
  'organization_id' => (int) $membership['organization_id'], 'project_name' => 'Synthetic observatory request', 'project_type' => 'business_website',
  'intake_data' => json_encode(['page_count' => 1, 'authored_content' => ['pages' => [['title' => 'Synthetic observatory']]]]),
  'status' => 'submitted', 'proof_review_status' => 'selected', 'selected_proof_direction' => 'a', 'proof_campaign_id' => $campaign->id(),
  'project_id' => $project->id(), 'prospect_id' => $prospect->id(), 'selected_proof_at' => $selectedAt])->execute();
$intent = SelectedSourceIntent::create(ppsRequest(), (string) $project->id(), (int) $variant->id(), 'a', 1, gmdate(DATE_ATOM, $selectedAt),
  [['role' => 'selected_preview', 'path' => $artifactPath, 'sha256' => hash_file('sha256', $artifactFile), 'bytes' => filesize($artifactFile)]], $dna, [], NULL);
$project->set('studio_json', json_encode(['selected_source_intent' => $intent], JSON_THROW_ON_ERROR))->save();
$authority = ['request_id' => 901, 'public_id' => $publicId, 'customer_id' => (int) $customer['id'],
  'organization_id' => (int) $membership['organization_id'], 'prospect_id' => (int) $prospect->id(), 'email' => $owner->getEmail(),
  'sku' => 'PRIVATE-SYNTHETIC-199', 'scope_hash' => hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
  'policy' => "synthetic-private-native-v1:$run", 'event_key' => "synthetic-private-scope:$run:offered", 'authority' => SyntheticPrivatePurchaseAuthority::DECLARATION];
$offerId = OfflinePrepaymentService::offerId('reunion-private-scope:' . $publicId);
$db->insert('famtastic_private_offer')->fields(['public_id' => $offerId, 'website_request_id' => 901, 'customer_id' => $authority['customer_id'],
  'organization_id' => $authority['organization_id'], 'sku' => $authority['sku'], 'list_amount_minor' => 19900, 'offered_amount_minor' => 19900,
  'currency' => 'usd', 'created_by_uid' => 0])->execute();
\Drupal::service('famtastic_pipeline.operational_ledger')->recordEvent($authority['event_key'], 'commerce.private_scope_offered',
  ['request_id' => 901, 'customer_id' => $authority['customer_id'], 'organization_id' => $authority['organization_id'],
    'scope' => $scope, 'scope_hash' => $authority['scope_hash'], 'offer_public_id' => $offerId, 'authority' => $authority['authority']], $authority['prospect_id']);
$binding = ['schema' => 'famtastic.synthetic-private-authority.v1', 'run_id' => $run, 'authority' => $authority, 'scope' => $scope];
ppsCheck(!\Drupal::getContainer()->initialized('famtastic_pipeline.private_purchase')
  && !\Drupal::getContainer()->initialized('famtastic_pipeline.private_purchase_authority'), 'binding_written_before_first_authority_access');
$fd = fopen($bindingFile, 'xb');
if ($fd === FALSE) throw new RuntimeException('synthetic_probe_binding_exists');
try {
  chmod($bindingFile, 0600);
  $bytes = json_encode($binding, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
  if (fwrite($fd, $bytes) !== strlen($bytes) || !fflush($fd) || !fsync($fd)) throw new RuntimeException('synthetic_probe_binding_write_failed');
}
finally { fclose($fd); }
$report['binding_sha256'] = hash_file('sha256', $bindingFile);
$service = \Drupal::service('famtastic_pipeline.private_purchase');
ppsCheck(\Drupal::service('famtastic_pipeline.private_purchase_authority') instanceof SyntheticPrivatePurchaseAuthority
  && $service->reunionRequestId() === 901 && $service->reunionPublicId() === $publicId, 'container_injects_exact_synthetic_authority');
ppsReject(fn() => (new PrivatePurchaseService())->context($owner, $publicId), 'default_production_service_rejects_synthetic_identity');
ppsReject(fn() => $service->context($owner, PrivatePurchaseService::REUNION), 'synthetic_service_rejects_production_public_id');
ppsReject(fn() => PrivatePurchaseService::selection(ppsRequest()), 'static_selection_wrapper_remains_production_default');
$baseline = ppsCounts();
ppsCheck($baseline['captured_mail'] === 0 && $baseline['transactional_capture_bytes'] === 0, 'seed_has_no_captured_receipt_or_mail');
$context = $service->context($owner, $publicId);
ppsCheck($context['order'] === NULL && $context['scope_hash'] === $authority['scope_hash'] && ppsCounts() === $baseline, 'scoped_context_read_only_no_native_order');
$scopeEvent = json_decode($db->select('famtastic_event', 'e')->fields('e', ['payload'])->condition('event_key', $authority['event_key'])->execute()->fetchField(), TRUE, 512, JSON_THROW_ON_ERROR);
ppsReject(fn() => PrivatePurchaseService::assertReunionScope($context['request'], $context['offer'], $scopeEvent), 'static_scope_wrapper_remains_production_default');
ppsReject(fn() => $service->context($foreign, $publicId), 'foreign_customer_rejected', 'prepayment_account_mismatch');
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo($owner);
try {
  $form = \Drupal::formBuilder()->getForm(PrivatePurchaseForm::class, $publicId);
  ppsCheck(isset($form['scope_snapshot'], $form['form_token'], $form['actions']['submit']) && $form['#method'] === 'post', 'actual_owner_native_form_build_has_scope_and_csrf_controls');
  ppsCheck(ppsCounts() === $baseline, 'form_build_creates_no_financial_or_notification_records');
}
finally { $switcher->switchBack(); }
$input = ppsInput($service, $binding);
ppsCheck($input['selection_snapshot']['policy'] === $authority['policy']
  && $input['selection_snapshot']['intent_id'] === 'selected-source:request:901:revision:1', 'real_campaign_variant_artifact_selection_binding');

$db->query("CREATE TRIGGER synthetic_private_fail BEFORE INSERT ON famtastic_event WHEN NEW.event_type = 'commerce.private_scope_checkout_started' BEGIN SELECT RAISE(ABORT, 'synthetic_private_late_failure'); END", [], ['allow_delimiter_in_query' => TRUE]);
try {
  ppsReject(fn() => $service->startReunion($owner, $publicId, $input), 'late_native_write_failure_rejected', 'synthetic_private_late_failure');
  ppsCheck(ppsCounts() === $baseline, 'rollback_restores_order_item_offer_resource_event_counts');
  $storage->resetCache();
  $rolledBack = $service->context($owner, $publicId);
  ppsCheck($rolledBack['order'] === NULL && $rolledBack['offer']['status'] === 'active' && empty($rolledBack['offer']['commerce_order_id']), 'rollback_restores_active_unbound_offer');
}
finally { $db->query('DROP TRIGGER synthetic_private_fail'); }
$storage->resetCache();
$entities->getStorage('commerce_order_item')->resetCache();
$order = $service->startReunion($owner, $publicId, $input);
ppsCheck($order instanceof \Drupal\commerce_order\Entity\Order && $order->getState()->getId() === 'draft'
  && !$order->isPaid() && $order->getTotalPrice()->equals(new Price('199.00', 'USD')), 'one_real_native_unpaid_draft_199_order');
ppsCheck($order->getItems()[0]->isUnitPriceOverridden() && !$order->getItems()[0]->getPurchasedEntity(), 'native_custom_price_not_catalog_product');
$counts = ppsCounts();
ppsCheck($counts['commerce_order'] === 1 && $counts['commerce_order_item'] === 1 && $counts['commerce_payment'] === 0, 'one_native_order_item_zero_payments');
ppsCheck($service->startReunion($owner, $publicId, $input)->id() === $order->id() && ppsCounts() === $counts, 'same_input_replay_reuses_order_without_new_records');
$resource = $db->select('famtastic_customer_resource', 'r')->fields('r')->condition('resource_type', 'commerce_order')->condition('resource_id', $order->id())->execute()->fetchAssoc();
ppsCheck((int) $resource['organization_id'] === $authority['organization_id'] && (int) $resource['organization_id'] !== $authority['customer_id'], 'durable_order_resource_owned_by_organization_not_customer_id');
$checkoutEvent = $db->select('famtastic_event', 'e')->fields('e')->condition('event_key', 'private-scope:request:901:checkout')->execute()->fetchAssoc();
$checkoutData = json_decode($checkoutEvent['payload'], TRUE, 512, JSON_THROW_ON_ERROR);
ppsCheck((int) $checkoutEvent['prospect_id'] === $authority['prospect_id'] && $checkoutData['request_id'] === 901
  && $checkoutData['customer_id'] === $authority['customer_id'] && $checkoutData['scope_hash'] === $authority['scope_hash'], 'durable_checkout_audit_bound_only_to_synthetic_identity');
$beforeRead = $db->select('commerce_order', 'o')->fields('o')->condition('order_id', $order->id())->execute()->fetchAssoc();
$storage->resetCache();
$service->context($owner, $publicId);
ppsCheck($beforeRead === $db->select('commerce_order', 'o')->fields('o')->condition('order_id', $order->id())->execute()->fetchAssoc(), 'context_does_not_refresh_or_save_native_order');

$order->set('payment_gateway', 'synthetic_private_stripe')->save();
$gateway = PaymentGateway::load('synthetic_private_stripe');
$switcher->switchTo($owner);
try {
  ppsGuards($order, TRUE, 'valid_native_boundary');
  $gateway->disable()->save();
  try { ppsGuards($order, FALSE, 'disabled_saved_gateway_denied'); }
  finally { $gateway->enable()->save(); }
  $metadata = $order->getData(PrivatePurchaseService::KEY);
  $order->setData(PrivatePurchaseService::KEY, NULL)->save();
  try { ppsGuards($order, FALSE, 'missing_metadata_durable_offer_lookup_denied'); }
  finally { $order->setData(PrivatePurchaseService::KEY, $metadata)->save(); }
  $db->delete('famtastic_membership')->condition('id', $membership['id'])->execute();
  try {
    ppsReject(fn() => $service->context($owner, $publicId), 'missing_membership_context_denied');
    ppsGuards($order, FALSE, 'missing_membership_native_boundary_denied');
  }
  finally { $db->insert('famtastic_membership')->fields($membership)->execute(); }
  $originalIntake = ppsRequest()['intake_data'];
  $db->update('famtastic_project_request')->fields(['intake_data' => '{"page_count":20}'])->condition('id', 901)->execute();
  try {
    ppsReject(fn() => $service->startReunion($owner, $publicId, $input), 'changed_request_rejects_stale_selection');
    ppsGuards($order, FALSE, 'changed_request_native_boundary_denied');
  }
  finally { $db->update('famtastic_project_request')->fields(['intake_data' => $originalIntake])->condition('id', 901)->execute(); }
  new Settings(array_replace(Settings::getAll(), ['famtastic_private_reunion_checkout_enabled' => FALSE]));
  try { ppsGuards($order, FALSE, 'disabled_checkout_native_boundary_denied'); }
  finally { new Settings(array_replace(Settings::getAll(), ['famtastic_private_reunion_checkout_enabled' => TRUE])); }
}
finally { $switcher->switchBack(); }

$originalScopeEvent = $db->select('famtastic_event', 'e')->fields('e', ['payload'])->condition('event_key', $authority['event_key'])->execute()->fetchField();
$changedScopeEvent = json_decode($originalScopeEvent, TRUE, 512, JSON_THROW_ON_ERROR);
$changedScopeEvent['scope']['title'] = 'Altered synthetic scope';
$db->update('famtastic_event')->fields(['payload' => json_encode($changedScopeEvent, JSON_THROW_ON_ERROR)])->condition('event_key', $authority['event_key'])->execute();
try { ppsReject(fn() => $service->context($owner, $publicId), 'changed_scope_event_rejected_against_frozen_hash'); }
finally { $db->update('famtastic_event')->fields(['payload' => $originalScopeEvent])->condition('event_key', $authority['event_key'])->execute(); }
$storage->resetCache();
$order = $storage->loadUnchanged($order->id());
$service->assertReunionOrder($order);
ppsCheck($order->getState()->getId() === 'draft' && !$order->isPaid(), 'guards_never_place_or_pay_order');
ppsCheck(ppsCounts() === $counts, 'all_negative_cases_preserve_durable_counts');
ppsCheck(ppsRequest()['commerce_order_id'] === NULL && ppsRequest()['staging_review_status'] === 'not_started'
  && $order->getData(PrivatePurchaseService::KEY)['client_acceptance'] === NULL
  && $order->getData(PrivatePurchaseService::KEY)['launch_authorized'] === FALSE, 'private_order_does_not_imply_staging_acceptance_or_launch');
$savedGateway = \Drupal::service('config.storage')->read('commerce_payment.commerce_payment_gateway.synthetic_private_stripe');
ppsCheck(is_array($savedGateway) && ($savedGateway['plugin'] ?? '') === 'stripe_payment_element'
  && ($savedGateway['configuration']['mode'] ?? '') === 'test' && empty($savedGateway['configuration']['access_token'])
  && empty($savedGateway['configuration']['secret_key']) && empty($savedGateway['configuration']['publishable_key'])
  && empty($savedGateway['configuration']['webhook_signing_secret']) && empty($order->getData('stripe_intent')), 'key_free_gateway_no_provider_intent');
ppsCheck(ppsCounts()['commerce_payment'] === 0 && ppsCounts()['captured_mail'] === 0 && ppsCounts()['transactional_capture_bytes'] === 0, 'no_native_payment_or_captured_receipt');
foreach (['famtastic_job', 'famtastic_notification_outbox', 'famtastic_commerce_fulfillment', 'famtastic_entitlement'] as $table) {
  ppsCheck(ppsCounts()[$table] === 0, 'no_side_effect_' . $table);
}
$report['records'] = ['run_id' => $run, 'request_id' => 901, 'request_public_id' => $publicId, 'owner_uid' => (int) $owner->id(),
  'customer_id' => $authority['customer_id'], 'organization_id' => $authority['organization_id'], 'prospect_id' => $authority['prospect_id'],
  'order_id' => (int) $order->id(), 'scope_sha256' => $authority['scope_hash'], 'artifact_sha256' => hash_file('sha256', $artifactFile),
  'final_counts' => ppsCounts()];
$report['status'] = 'seed_passed_awaiting_fresh_process';
$report['classification'] = 'locally proven: first-process checks only; fresh-process durability pending';
