<?php
declare(strict_types=1);

/** Real Commerce records in the existing isolated harness, never production. */
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_price\Price;
use Drupal\commerce_store\Entity\Store;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\OfflinePrepaymentService as Offline;
use Drupal\famtastic_pipeline\Service\PrivatePurchaseService as Purchase;
use Drupal\famtastic_pipeline\Service\SelectedSourceIntent;
use Drupal\famtastic_pipeline\EventSubscriber\PrivateScopeCheckoutGuard;
use Drupal\user\Entity\User;

$sandbox = realpath(getenv('SELECTED_DRUPAL_SANDBOX') ?: '') ?: '';
$db = \Drupal::database();
$options = $db->getConnectionOptions();
if (!preg_match('#/famtastic-selected-drupal\.[A-Za-z0-9]{6}$#', $sandbox)
  || realpath(__DIR__) !== "$sandbox/scripts" || realpath(\Drupal::root()) !== "$sandbox/backend/web"
  || $options['driver'] !== 'sqlite' || realpath($options['database']) !== "$sandbox/backend/web/sites/default/files/.ht.sqlite"
  || getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') !== 'memory'
  || \Drupal::config('system.mail')->get('interface.default') !== 'test_mail_collector'
  || ini_get('allow_url_fopen') !== '0' || function_exists('curl_exec') || function_exists('stream_socket_client') || function_exists('mail')) {
  throw new RuntimeException('Private purchase harness requires isolated database, captured mail and disabled network.');
}
$phase = getenv('PRIVATE_PURCHASE_PHASE') ?: 'test';
$evidence = getenv('SELECTED_DRUPAL_EVIDENCE') . ($phase === 'http-seed' ? '/private-purchase-http-seed.json' : '/private-purchase.json');
$report = ['schema' => 'famtastic.private-purchase-native-proof.v1', 'status' => 'running',
  'source_sha' => getenv('SELECTED_DRUPAL_SOURCE_SHA'), 'classification' => 'locally proven',
  'php_version' => PHP_VERSION, 'drupal_version' => \Drupal::VERSION, 'checks' => [],
  'limits' => ['Disposable SQLite, not concurrent production MySQL.', 'Native entities/services, not browser or authenticated HTTP/CSRF proof.',
    'Configured test-only Stripe gateway is never invoked. No Stripe, webhook, 3DS or external payment proof.',
    'Synthetic manual receipt is not the production order21/payment5.'], 'records' => []];
foreach (['Service/PrivatePurchaseService.php', 'Service/PrivatePurchaseAuthorityInterface.php', 'Service/ApprovedPrivatePurchaseAuthority.php', 'Form/PrivatePurchaseForm.php', 'EventSubscriber/PrivateScopeCheckoutGuard.php', 'Service/OfflinePrepaymentService.php'] as $file) {
  $report['tested_source_sha256'][$file] = hash_file('sha256', \Drupal::root() . '/modules/custom/famtastic_pipeline/src/' . $file);
}
$report['tested_source_sha256']['famtastic_pipeline.services.yml'] = hash_file('sha256', \Drupal::root() . '/modules/custom/famtastic_pipeline/famtastic_pipeline.services.yml');
$report['harness_sha256'] = hash_file('sha256', __FILE__);
if (getenv('PRIVATE_PURCHASE_PHASE') === 'verify') $report = json_decode(file_get_contents($evidence), TRUE, 512, JSON_THROW_ON_ERROR);
$GLOBALS['privatePurchaseReport'] =& $report;
register_shutdown_function(static function () use (&$report, $evidence): void {
  if ($report['status'] !== 'passed') $report['status'] = 'failed';
  file_put_contents($evidence, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
});
function ppCheck(bool $pass, string $key): void {
  $GLOBALS['privatePurchaseReport']['checks'][$key] = $pass;
  if (!$pass) throw new RuntimeException('FAIL: ' . $key);
  print "PASS: $key\n";
}
function ppReject(callable $action, string $key, string $expected = 'private_'): void {
  try { $action(); } catch (Throwable $error) {
    if (!str_contains($error->getMessage(), $expected)) throw new RuntimeException('Unexpected rejection for ' . $key . ': ' . $error->getMessage(), 0, $error);
    ppCheck(TRUE, $key); return;
  }
  ppCheck(FALSE, $key);
}
function ppCounts(): array {
  $counts = [];
  foreach (['commerce_order', 'commerce_order_item', 'commerce_payment', 'famtastic_job', 'famtastic_notification_outbox', 'famtastic_commerce_fulfillment', 'famtastic_entitlement'] as $table) {
    $counts[$table] = (int) \Drupal::database()->select($table, 'x')->countQuery()->execute()->fetchField();
  }
  return $counts;
}
function ppRequest(): array {
  return \Drupal::database()->select('famtastic_project_request', 'r')->fields('r')->condition('id', 16)->execute()->fetchAssoc();
}
function ppInput(array $scope): array {
  return ['accept_terms' => TRUE, 'terms_version' => $scope['version'], 'scope_hash' => Purchase::REUNION_HASH,
    'domain_choice' => 'undecided', 'selection_snapshot' => Purchase::selection(ppRequest())];
}
new Settings(array_replace(Settings::getAll(), ['famtastic_payment_mode' => 'test', 'famtastic_private_reunion_checkout_enabled' => TRUE]));
$service = new Purchase();
$storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
if (getenv('PRIVATE_PURCHASE_PHASE') === 'verify') {
  $owner = User::load($report['records']['owner_uid']);
  $scope = $service->context($owner, Purchase::REUNION)['scope'];
  $order = $service->startReunion($owner, Purchase::REUNION, ppInput($scope));
  ppCheck((int) $order->id() === $report['records']['reunion_order'], 'new_process_reuses_same_native_order');
  ppCheck(ppCounts() === $report['records']['final_counts'], 'new_process_does_not_create_payment_or_fulfillment');
  $stock = User::load($report['records']['stock_uid']);
  $context = $service->context($stock, Purchase::STOCK);
  ppCheck($context['data']['completion']['state'] === 'consumed' && $context['receipt']['outstanding'] === '0.00', 'new_process_reloads_paid_completion_without_charge');
  $report['status'] = 'passed';
  return;
}
ppCheck(TRUE, 'isolated_installed_drupal_native_commerce_captured_mail_no_network');
if (!OrderItemType::load('default')) OrderItemType::create(['id' => 'default', 'label' => 'Default', 'purchasableEntityType' => 'commerce_product_variation', 'orderType' => 'default'])->save();
\Drupal::service('commerce_price.currency_importer')->import('USD');
$store = Store::create(['type' => 'online', 'uid' => 1, 'name' => 'Disposable fixture', 'mail' => 'store@example.test',
  'address' => ['country_code' => 'US', 'address_line1' => '1 Test Street', 'locality' => 'Test', 'administrative_area' => 'FL', 'postal_code' => '34986'],
  'default_currency' => 'USD', 'timezone' => 'America/New_York', 'status' => TRUE]);
$store->save();
ppCheck((int) $store->id() === 1, 'fresh_store_exact_binding');
// Real installed gateway configuration; fake keys and all transports disabled.
PaymentGateway::create(['id' => 'private_fixture_stripe', 'label' => 'Never invoked fixture', 'plugin' => 'stripe', 'status' => TRUE,
  'configuration' => ['mode' => 'test', 'publishable_key' => 'pk_test_disposable_not_a_key', 'secret_key' => 'sk_test_disposable_not_a_key']])->save();
$users = []; $passwords = [];
// Reserve fixture identities before native user hooks allocate customer/org rows.
// This is only the fresh disposable SQLite DB, never a production sequence.
foreach (['famtastic_customer', 'famtastic_organization'] as $table) {
  $db->delete('sqlite_sequence')->condition('name', $table)->execute();
  $db->insert('sqlite_sequence')->fields(['name' => $table, 'seq' => 13])->execute();
}
foreach ([14 => ['email' => 'mbshclassof2000@gmail.com', 'request' => 16, 'public_id' => Purchase::REUNION],
  15 => ['email' => 'sprospere@yahoo.com', 'request' => 17, 'public_id' => Purchase::STOCK]] as $id => $fixture) {
  $passwords[$id] = $phase === 'http-seed' ? 'Disposable-local-' . $id . '-only!' : bin2hex(random_bytes(20));
  // Match the actual portal writer: customer usernames are their email address.
  $user = User::create(['name' => $fixture['email'], 'mail' => $fixture['email'], 'status' => 1, 'pass' => $passwords[$id]]);
  $user->save(); $users[$id] = $user;
  $customer = \Drupal::service('famtastic_pipeline.customer_portal')->customerForUid((int) $user->id());
  ppCheck((int) $customer['id'] === $id && $customer['email'] === $fixture['email']
    && (int) $db->select('famtastic_membership', 'm')->fields('m', ['organization_id'])->condition('customer_id', $id)->execute()->fetchField() === $id, 'native_user_hook_fixture_identity_' . $id);
  $db->update('famtastic_customer')->fields(['verified_at' => 1])->condition('id', $id)->execute();
  $db->insert('famtastic_project_request')->fields(['id' => $fixture['request'], 'public_id' => $fixture['public_id'], 'customer_id' => $id,
    'organization_id' => $id, 'project_name' => 'Disposable native purchase', 'project_type' => 'business_website',
    'intake_data' => '{}', 'status' => 'submitted', 'proof_review_status' => 'selected', 'selected_proof_direction' => 'a', 'proof_campaign_id' => $id === 14 ? 57 : 56])->execute();
}
$owner = $users[14]; $stock = $users[15];
$entities = \Drupal::entityTypeManager();
$prospect = $entities->getStorage('famtastic_prospect')->create(['business_name' => 'Synthetic reunion', 'email' => $owner->getEmail()]);
$prospect->save();
$campaign = $entities->getStorage('proof_campaign')->create(['id' => 57, 'campaign_id' => 'private-native-fixture', 'prospect_id' => $prospect->id(),
  'business_name' => 'Synthetic reunion', 'generation_status' => 'ready', 'selected_variant' => 'a', 'selected_at' => 123]);
$campaign->save();
$artifactPath = 'web/modules/custom/famtastic_pipeline/tests/fixtures/reunion-private-scope.json';
$artifactFile = dirname(\Drupal::root()) . '/' . $artifactPath;
$variant = $entities->getStorage('proof_variant')->create(['campaign_id' => 57, 'direction_id' => 'a', 'direction_name' => 'Synthetic A',
  'artifact_path' => $artifactPath, 'design_dna' => '{"fixture":true}']);
$variant->save();
$project = $entities->getStorage('famtastic_project')->create(['label' => 'Synthetic reunion', 'prospect_ref' => $prospect->id()]);
$project->save();
$db->update('famtastic_project_request')->fields(['project_id' => $project->id(), 'prospect_id' => $prospect->id(), 'selected_proof_at' => 123])->condition('id', 16)->execute();
$intent = SelectedSourceIntent::create(ppRequest(), (string) $project->id(), (int) $variant->id(), 'a', 1, gmdate(DATE_ATOM, 123),
  [['role' => 'selected_preview', 'path' => $artifactPath, 'sha256' => hash_file('sha256', $artifactFile), 'bytes' => filesize($artifactFile)]], ['fixture' => TRUE], [], NULL);
$project->set('studio_json', json_encode(['selected_source_intent' => $intent], JSON_THROW_ON_ERROR))->save();
$scope = json_decode(file_get_contents(\Drupal::root() . '/modules/custom/famtastic_pipeline/tests/fixtures/reunion-private-scope.json'), TRUE, 512, JSON_THROW_ON_ERROR);
$offerId = Offline::offerId('reunion-private-scope:' . Purchase::REUNION);
$db->insert('famtastic_private_offer')->fields(['public_id' => $offerId, 'website_request_id' => 16, 'customer_id' => 14, 'organization_id' => 14,
  'sku' => 'PRIVATE-REUNION16-199', 'list_amount_minor' => 19900, 'offered_amount_minor' => 19900, 'currency' => 'usd', 'created_by_uid' => 0])->execute();
\Drupal::service('famtastic_pipeline.operational_ledger')->recordEvent('private-scope:request:16:class-of-2000-v1', 'commerce.private_scope_offered',
  ['request_id' => 16, 'customer_id' => 14, 'organization_id' => 14, 'scope' => $scope, 'scope_hash' => Purchase::REUNION_HASH,
    'offer_public_id' => $offerId, 'authority' => 'Fritz Medine explicit approval']);
$baseline = ppCounts();
if ($phase === 'http-seed') {
  // Separate fresh HTTP fixture, not a replay/reset of a real customer's order.
  $offline = new Offline();
  $receipt = $offline->record(Purchase::STOCK, 15, $stock->getEmail(), Offline::stockandshipScope(), 'Disposable HTTP fixture; never production.');
  $completion = $offline->issueCompletionCode($receipt['order_id'], User::load(1));
  $fixture = ['accounts' => [], 'requests' => ['reunion' => Purchase::REUNION, 'stock' => Purchase::STOCK],
    'stock_order' => $receipt['order_id'], 'completion_code' => $completion, 'original_intake' => ppRequest()['intake_data']];
  foreach ($users as $id => $user) $fixture['accounts'][$id === 14 ? 'reunion' : 'stock'] = ['email' => $user->getEmail(), 'password' => $passwords[$id], 'uid' => (int) $user->id()];
  file_put_contents($sandbox . '/private-http-fixture.json', json_encode($fixture, JSON_THROW_ON_ERROR));
  chmod($sandbox . '/private-http-fixture.json', 0600);
  touch($sandbox . '/private-http-checkout-enabled');
  $settingsFile = \Drupal::root() . '/sites/default/settings.php';
  chmod($settingsFile, 0600);
  file_put_contents($settingsFile, "\n\$settings['trusted_host_patterns'][] = '^127\\.0\\.0\\.1$';\n"
    . "\$settings['famtastic_payment_mode'] = 'test';\n"
    . "\$settings['famtastic_private_reunion_checkout_enabled'] = is_file(" . var_export($sandbox . '/private-http-checkout-enabled', TRUE) . ");\n"
    . "\$config['system.theme']['default'] = 'famtastic_customer';\n"
    . "\$config['system.performance']['css']['preprocess'] = FALSE;\n"
    . "\$config['system.performance']['js']['preprocess'] = FALSE;\n", FILE_APPEND);
  $report['status'] = 'passed';
  $report['limits'][] = 'Fixture seed only; HTTP assertions are recorded separately.';
  return;
}
$context = $service->context($owner, Purchase::REUNION);
ppCheck(ppCounts() === $baseline && $context['order'] === NULL, 'read_context_creates_no_order_or_payment');
ppReject(fn() => $service->context($stock, Purchase::REUNION), 'other_customer_context_rejected', 'prepayment_account_mismatch');
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo($owner);
try {
  $nativeForm = \Drupal::formBuilder()->getForm(\Drupal\famtastic_pipeline\Form\PrivatePurchaseForm::class, Purchase::REUNION);
  ppCheck(isset($nativeForm['scope_snapshot'], $nativeForm['form_token']) && $nativeForm['#method'] === 'post', 'native_form_api_get_builds_signed_scope_and_csrf_controls');
  ppCheck(ppCounts() === $baseline, 'native_form_api_get_creates_no_purchase_or_notification');
}
finally { $switcher->switchBack(); }
$input = ppInput($scope);
// Fail after native item/order/offer writes and prove transaction rollback.
$db->query("CREATE TRIGGER private_purchase_fail BEFORE INSERT ON famtastic_event WHEN NEW.event_type = 'commerce.private_scope_checkout_started' BEGIN SELECT RAISE(ABORT, 'injected private purchase failure'); END", [], ['allow_delimiter_in_query' => TRUE]);
ppReject(fn() => $service->startReunion($owner, Purchase::REUNION, $input), 'late_event_failure_rejects_native_purchase', 'injected private purchase failure');
ppCheck(ppCounts() === $baseline, 'late_failure_rolls_back_real_order_item_and_side_effects');
ppCheck($service->context($owner, Purchase::REUNION)['offer']['status'] === 'active', 'late_failure_restores_private_offer');
$db->query('DROP TRIGGER private_purchase_fail');
$storage->resetCache();
$order = $service->startReunion($owner, Purchase::REUNION, $input);
ppCheck($order instanceof \Drupal\commerce_order\Entity\Order && !$order->isPaid() && $order->getTotalPrice()->equals(new Price('199.00', 'USD')), 'native_custom_price_order_unpaid_199');
ppCheck($order->getItems()[0]->isUnitPriceOverridden() && !$order->getItems()[0]->getPurchasedEntity(), 'custom_price_override_not_catalog_product');
$counts = ppCounts();
ppCheck($counts['commerce_order'] === 1 && $counts['commerce_order_item'] === 1 && $counts['commerce_payment'] === 0, 'one_order_one_item_zero_payments');
ppCheck($service->startReunion($owner, Purchase::REUNION, $input)->id() === $order->id() && ppCounts() === $counts, 'same_post_reuses_existing_purchase');
$beforeRead = $db->select('commerce_order', 'o')->fields('o')->condition('order_id', $order->id())->execute()->fetchAssoc();
$storage->resetCache(); $service->context($owner, Purchase::REUNION);
ppCheck($beforeRead === $db->select('commerce_order', 'o')->fields('o')->condition('order_id', $order->id())->execute()->fetchAssoc(), 'financial_get_does_not_refresh_or_save_native_order');
$order->set('payment_gateway', 'private_fixture_stripe')->save();
$gateway = PaymentGateway::load('private_fixture_stripe');
$guard = new PrivateScopeCheckoutGuard();
$requestEvent = new \Symfony\Component\HttpKernel\Event\RequestEvent(\Drupal::service('http_kernel'),
  \Symfony\Component\HttpFoundation\Request::create('/checkout/' . $order->id()), \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST);
$switcher->switchTo($owner);
try {
  $guard->request($requestEvent);
  ppCheck(TRUE, 'native_saved_enabled_gateway_allows_checkout_guard_without_invocation');
  $gateway->disable()->save();
  ppReject(fn() => $guard->request($requestEvent), 'native_disabled_saved_gateway_blocks_resumed_checkout', 'private checkout is unavailable');
  $gateway->enable()->save();
  $order->set('payment_gateway', NULL)->save();
  $guard->request($requestEvent);
  ppCheck(TRUE, 'native_unassigned_gateway_allows_initial_checkout_guard');
}
finally { $switcher->switchBack(); }
ppCheck(ppRequest()['commerce_order_id'] === NULL && ppRequest()['staging_review_status'] === 'not_started', 'private_payment_exception_preserves_staging_and_no_acceptance');
$originalIntake = ppRequest()['intake_data'];
$db->update('famtastic_project_request')->fields(['intake_data' => json_encode(['page_count' => 20, 'authored_content' => ['pages' => [['title' => 'Changed']]]])])->condition('id', 16)->execute();
ppReject(fn() => $service->startReunion($owner, Purchase::REUNION, $input), 'same_direction_changed_scope_rejects_stale_form');
ppReject(fn() => $service->assertReunionOrder($order), 'same_direction_changed_scope_rejects_checkout_resume');
$db->update('famtastic_project_request')->fields(['intake_data' => $originalIntake])->condition('id', 16)->execute();
new Settings(array_replace(Settings::getAll(), ['famtastic_private_reunion_checkout_enabled' => FALSE]));
ppReject(fn() => $service->startReunion($owner, Purchase::REUNION, $input), 'disabled_feature_cannot_resume_payment');
new Settings(array_replace(Settings::getAll(), ['famtastic_private_reunion_checkout_enabled' => TRUE]));
$offline = new Offline();
$receipt = $offline->record(Purchase::STOCK, 15, $stock->getEmail(), Offline::stockandshipScope(), 'Synthetic harness only; not a production payment.');
$repeat = $offline->record(Purchase::STOCK, 15, $stock->getEmail(), Offline::stockandshipScope(), 'Synthetic harness only; not a production payment.');
ppCheck($receipt['order_id'] === $repeat['order_id'] && $receipt['payment_id'] === $repeat['payment_id'], 'native_manual_receipt_replay_has_same_order_payment');
ppCheck($receipt['received'] === '200.00' && $receipt['outstanding'] === '0.00' && $receipt['received_at'] === NULL && $receipt['bank_transaction_reference'] === NULL, 'native_manual_payment_preserves_amount_and_unknown_bank_evidence');
$code = $offline->issueCompletionCode($receipt['order_id'], User::load(1));
$data = $storage->loadUnchanged($receipt['order_id'])->getData(Offline::KEY);
$details = ['accept_terms' => TRUE, 'terms_version' => $data['scope']['version'], 'scope_hash' => $data['scope_hash'], 'domain_choice' => 'undecided'];
$beforeComplete = ppCounts();
ppReject(fn() => $offline->complete($receipt['order_id'], $owner, $code, $details), 'completion_rejects_wrong_account', 'completion_account_mismatch');
$offline->complete($receipt['order_id'], $stock, $code, $details);
ppReject(fn() => $offline->complete($receipt['order_id'], $stock, $code, $details), 'completion_code_cannot_replay', 'completion_code_invalid_or_used');
ppCheck(ppCounts() === $beforeComplete, 'completion_creates_no_order_payment_job_notification_or_entitlement');
$completed = $service->context($stock, Purchase::STOCK);
ppCheck($completed['data']['completion']['state'] === 'consumed' && $completed['data']['client_acceptance'] === NULL && $completed['receipt']['launch_authorized'] === FALSE, 'native_completion_is_not_acceptance_or_launch');
foreach (['famtastic_job', 'famtastic_notification_outbox', 'famtastic_commerce_fulfillment', 'famtastic_entitlement'] as $table) ppCheck(ppCounts()[$table] === $baseline[$table], 'no_side_effect_' . $table);
$report['records'] = ['reunion_order' => (int) $order->id(), 'owner_uid' => (int) $owner->id(), 'stock_uid' => (int) $stock->id(), 'final_counts' => ppCounts()];
$report['status'] = 'passed';
