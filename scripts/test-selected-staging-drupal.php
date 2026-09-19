<?php

declare(strict_types=1);

/**
 * Run only through test-selected-staging-drupal.sh in a disposable installation.
 * Real Drupal classes/services/controllers/SQL, synthetic customer/source data.
 * Controller requests are in-process; this is not browser/session or hosting QA.
 */

use Drupal\famtastic_pipeline\Controller\CustomerPortalController;
use Drupal\famtastic_pipeline\Controller\SiteStudioCallbackController;
use Drupal\famtastic_pipeline\Service\StagingReceiptService;
use Drupal\Core\Session\AnonymousUserSession;
use Symfony\Component\HttpFoundation\Request;

$sandbox = realpath(getenv('SELECTED_DRUPAL_SANDBOX') ?: '') ?: '';
if (!preg_match('#/famtastic-selected-drupal\.[A-Za-z0-9]{6}$#', $sandbox)
  || realpath(__DIR__) !== $sandbox . '/scripts') {
  throw new RuntimeException('Refusing to run outside the private harness copy. Use test-selected-staging-drupal.sh.');
}
$phase = getenv('SELECTED_DRUPAL_PHASE') ?: 'test';
if ($phase === 'canonical-prepare') {
  // Child Drush invocations and the local HTTP server inherit the same PHP
  // network boundary. Incoming loopback HTTP is used only by the canonical
  // runner's real session/CSRF checks; outgoing Drupal HTTP remains blocked.
  mkdir($sandbox . '/bin', 0700);
  file_put_contents($sandbox . '/bin/php', "#!/usr/bin/env bash\nexec " . escapeshellarg(PHP_BINARY)
    . " -d memory_limit=512M -d zend.assertions=1 -d assert.exception=1 -d allow_url_fopen=0"
    . " -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,mail"
    . " -d sendmail_path=/usr/bin/false \"\$@\"\n");
  chmod($sandbox . '/bin/php', 0700);
  file_put_contents($sandbox . '/no-network.cjs', <<<'JS'
const port = Number(process.env.PORT);
const publicCms = process.env.SELECTED_CANONICAL_PUBLIC_CMS_READ === '1';
const deny = () => { throw new Error('SELECTED_CANONICAL_OFFLINE: only the exact loopback port and explicitly opted-in public CMS GET are allowed.'); };
const local = (host, targetPort) => ['127.0.0.1', 'localhost'].includes(host) && Number(targetPort) === port && port >= 28900 && port < 29200;
const allowed = (url, method = 'GET') => {
  const u = new URL(url);
  if (u.username || u.password) return false;
  if (u.protocol === 'http:' && local(u.hostname, u.port)) return true;
  return publicCms && method.toUpperCase() === 'GET' && u.origin === 'https://famtasticdesigns.com'
    && /^\/web\/jsonapi\/node\/(service_page|package_page|case_study|blog_post)$/.test(u.pathname);
};
const originalFetch = globalThis.fetch;
globalThis.fetch = (input, init = {}) => {
  const url = typeof input === 'string' || input instanceof URL ? String(input) : input.url;
  if (!allowed(url, init.method || input.method || 'GET')) return deny();
  // Do not follow an allowed public URL into an unreviewed destination.
  return originalFetch(input, { ...init, redirect: 'error' });
};
for (const name of ['node:http', 'node:https']) {
  const api = require(name);
  for (const key of ['request', 'get']) {
    const original = api[key];
    api[key] = function(input, ...args) {
      const o = typeof input === 'object' && !(input instanceof URL) ? input : {};
      const url = typeof input === 'string' || input instanceof URL ? String(input)
        : `${o.protocol || (name === 'node:https' ? 'https:' : 'http:')}//${o.hostname || o.host || 'localhost'}${o.port ? ':' + o.port : ''}${o.path || '/'}`;
      const options = typeof args[0] === 'object' ? args[0] : o;
      if (!allowed(url, options.method || 'GET')) return deny();
      return original.call(this, input, ...args);
    };
  }
}
const net = require('node:net');
const originalConnect = net.Socket.prototype.connect;
net.Socket.prototype.connect = function(...raw) {
  const args = Array.isArray(raw[0]) ? raw[0] : raw;
  const o = typeof args[0] === 'object' ? args[0] : { port: args[0], host: typeof args[1] === 'string' ? args[1] : 'localhost' };
  const host = o.host || o.hostname || 'localhost';
  if (o.path || !(local(host, o.port) || (publicCms && host === 'famtasticdesigns.com' && Number(o.port) === 443))) return deny();
  return originalConnect.apply(this, raw);
};
JS
  );
  // The canonical runner uses a local server host as well as the CLI URI.
  chmod($sandbox . '/backend/web/sites/default/settings.php', 0600);
  file_put_contents($sandbox . '/backend/web/sites/default/settings.php', "\n\$settings['trusted_host_patterns'][] = '^127\\.0\\.0\\.1$';\n", FILE_APPEND);
  exit(0);
}
if ($phase === 'canonical-report') {
  $directory = getenv('SELECTED_DRUPAL_EVIDENCE');
  $exitCode = (int) getenv('SELECTED_CANONICAL_EXIT');
  file_put_contents($directory . '/canonical.json', json_encode([
    'schema' => 'famtastic.selected-canonical-run.v1', 'source_sha' => getenv('SELECTED_DRUPAL_SOURCE_SHA'),
    'status' => $exitCode === 0 ? 'passed' : 'failed', 'exit_code' => $exitCode,
    'runner' => 'scripts/run-customer-proof-agent.sh', 'runtime' => 'fresh_disposable_sqlite',
    'credentials_copied' => FALSE, 'mail' => 'memory', 'outgoing_php_network' => 'disabled',
    'node_network' => 'exact_ephemeral_loopback_port_only',
    'public_cms_get_allowed' => getenv('SELECTED_CANONICAL_PUBLIC_CMS_READ') === '1',
    'log' => $directory . '/canonical.log',
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
  exit(0);
}
if ($phase === 'configure') {
  $settings = $sandbox . '/backend/web/sites/default/settings.php';
  if (file_exists($settings)) throw new RuntimeException('Refusing to overwrite existing settings.');
  copy(dirname($settings) . '/default.settings.php', $settings);
  file_put_contents($settings, "\n\$settings['hash_salt'] = 'disposable-selected-drupal-only';\n"
    . "\$settings['file_private_path'] = " . var_export($sandbox . '/backend/private', TRUE) . ";\n"
    . "\$settings['trusted_host_patterns'] = ['^selected-drupal\\.example\\.test$'];\n"
    . "\$config['system.mail']['interface']['default'] = 'test_mail_collector';\n"
    . "\$config['automated_cron.settings']['interval'] = 0;\n"
    . "\$config['famtastic_pipeline.settings']['frontend_base_url'] = 'https://agency.example.test';\n"
    . "\$config['famtastic_pipeline.settings']['notification_to_email'] = 'operator@example.test';\n"
    . "\$settings['container_yamls'][] = __DIR__ . '/selected-test.services.yml';\n", FILE_APPEND);
  // Guzzle must be constructible during install even with native networking
  // disabled. An empty MockHandler throws on every request, never contacting a
  // provider; all Drupal application services remain their installed classes.
  file_put_contents(dirname($settings) . '/selected-test.services.yml', <<<'YAML'
services:
  selected_drupal_http_blocker:
    class: GuzzleHttp\Handler\MockHandler
  http_handler_stack:
    class: GuzzleHttp\HandlerStack
    factory: GuzzleHttp\HandlerStack::create
    arguments: ['@selected_drupal_http_blocker']
YAML
  );
  exit(0);
}

$db = \Drupal::database();
$dbOptions = $db->getConnectionOptions();
$databasePath = realpath((string) $dbOptions['database']);
if (realpath(\Drupal::root()) !== $sandbox . '/backend/web'
  || $dbOptions['driver'] !== 'sqlite'
  || $databasePath !== $sandbox . '/backend/web/sites/default/files/.ht.sqlite'
  || getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') !== 'memory'
  || \Drupal::config('system.mail')->get('interface.default') !== 'test_mail_collector'
  || ini_get('allow_url_fopen') !== '0'
  || function_exists('curl_exec') || function_exists('stream_socket_client') || function_exists('mail')) {
  throw new RuntimeException('Disposable database/mail/network safety checks failed.');
}
$evidencePath = getenv('SELECTED_DRUPAL_EVIDENCE') . '/evidence.json';
$report = [
  'schema' => 'famtastic.selected-staging-drupal-proof.v1', 'status' => 'running',
  'source_sha' => getenv('SELECTED_DRUPAL_SOURCE_SHA'),
  'composer_lock_sha256' => hash_file('sha256', $sandbox . '/backend/composer.lock'),
  'drupal_version' => \Drupal::VERSION, 'php_version' => PHP_VERSION,
  'runtime_root' => \Drupal::root(), 'database' => $databasePath,
  'module_source_sha256' => [],
  'classification' => 'locally proven', 'checks' => [],
  'limits' => ['Synthetic source/hosting receipts; no hosting or provider execution.',
    'In-process authenticated Drupal controllers; no browser login/cookie session proof.',
    'SQLite transactions and trigger-interleaving only; no MySQL row-lock/concurrent-writer proof.',
    'Checkout eligibility only; no Commerce order or payment execution.'],
];
foreach (['CustomerPortalService', 'OperationalLedger', 'SiteStudioBuildPacketService', 'StagingReceiptService'] as $class) {
  $path = (new ReflectionClass('Drupal\\famtastic_pipeline\\Service\\' . $class))->getFileName();
  if (!str_starts_with($path, $sandbox . '/backend/web/modules/custom/famtastic_pipeline/')) throw new RuntimeException('Wrong custom-module autoload root.');
  $report['module_source_sha256'][$class] = hash_file('sha256', $path);
}
if ($phase === 'verify') $report = json_decode(file_get_contents($evidencePath), TRUE, 512, JSON_THROW_ON_ERROR);
// Drush includes php:script inside a method scope, not PHP's global scope.
$GLOBALS['selectedDrupalReport'] =& $report;
register_shutdown_function(static function () use (&$report, $evidencePath): void {
  $error = error_get_last();
  if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], TRUE)) {
    $report['status'] = 'failed';
    $report['error'] = $error['message'];
  }
  file_put_contents($evidencePath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
});

function check(bool $condition, string $name): void {
  $report =& $GLOBALS['selectedDrupalReport'];
  $report['checks'][$name] = $condition;
  if (!$condition) { $report['status'] = 'failed'; throw new RuntimeException('FAIL: ' . $name); }
  print "PASS: $name\n";
}

function row(string $publicId): array {
  return \Drupal::database()->select('famtastic_project_request', 'r')->fields('r')
    ->condition('public_id', $publicId)->execute()->fetchAssoc() ?: [];
}

function studio(string $publicId): array {
  $storage = \Drupal::entityTypeManager()->getStorage('famtastic_project');
  $id = (int) row($publicId)['project_id'];
  $storage->resetCache([$id]);
  return json_decode((string) $storage->load($id)->get('studio_json')->value, TRUE, 512, JSON_THROW_ON_ERROR);
}

function counts(): array {
  $tables = ['famtastic_project', 'proof_campaign', 'proof_variant', 'famtastic_job', 'famtastic_event', 'famtastic_notification_outbox', 'famtastic_portal_activity', 'famtastic_customer_resource'];
  return array_combine($tables, array_map(static fn(string $table): int => (int) \Drupal::database()->select($table, 't')->countQuery()->execute()->fetchField(), $tables));
}

function portalCall(int $uid, string $method, string $publicId, array $body, int $expected = 200): array {
  $account = $uid ? \Drupal::entityTypeManager()->getStorage('user')->load($uid) : new AnonymousUserSession();
  $switcher = \Drupal::service('account_switcher');
  $switcher->switchTo($account);
  try {
    $request = Request::create('/api/customer/website-requests/' . $publicId, 'POST', [], [], [],
      ['CONTENT_TYPE' => 'application/json'], json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $response = CustomerPortalController::create(\Drupal::getContainer())->$method($request, $publicId);
    $result = json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
    if ($response->getStatusCode() !== $expected) throw new RuntimeException("$method expected $expected, got " . $response->getStatusCode() . ': ' . $response->getContent());
    return $result;
  }
  finally { $switcher->switchBack(); }
}

function callback(array $data, int $expected = 200, bool $validSignature = TRUE): array {
  $raw = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
  $request = Request::create('/api/pipeline/site-studio/callback', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $raw);
  $request->headers->set('X-FAMtastic-Signature', 'sha256=' . hash_hmac('sha256', $raw, $validSignature ? getenv('SITE_STUDIO_CALLBACK_SECRET') : 'wrong-synthetic-secret'));
  $response = SiteStudioCallbackController::create(\Drupal::getContainer())->handle($request);
  if ($response->getStatusCode() !== $expected) throw new RuntimeException('Callback expected ' . $expected . ', got ' . $response->getStatusCode() . ': ' . $response->getContent());
  return json_decode($response->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
}

function receipt(array $packet, string $event): array {
  $c = $packet['continuation'];
  return [
    'schema' => 'famtastic.site-studio.staging-receipt.v1', 'status' => 'deployed', 'event_id' => $event,
    'packet_id' => $packet['packet_id'], 'idempotency_key' => $packet['idempotency_key'],
    'request_id' => $packet['request_id'], 'project_id' => $packet['project_id'],
    'website_request_id' => $c['website_request_id'], 'customer_id' => $c['customer']['id'],
    'selection_revision' => $c['selection_revision'], 'selected_direction_id' => $packet['selected_direction_ids'][0],
    'selected_artifact_sha256' => $packet['selected_artifacts'][0]['source_artifact_sha256'],
    'artifact_manifest_sha256' => $packet['artifact_manifest_sha256'],
    'artifact_sha256' => hash('sha256', 'synthetic-hosted-output:' . $event), 'completed_at' => gmdate(DATE_ATOM),
    'repository' => ['branch' => 'fixture-only', 'mode' => 'local_only'],
    'qa' => [['name' => 'synthetic-receipt-contract-only', 'status' => 'passed']],
  ] + $c['hosting_target'];
}

/** Prerequisite records are fixtures; selection/registration/revision are real writers. */
function fixture(int $uid, string $label, bool $executable = TRUE, bool $normalCallback = FALSE): string {
  $portal = \Drupal::service('famtastic_pipeline.customer_portal');
  $customer = $portal->customerForUid($uid);
  $org = $portal->organizations((int) $customer['id'])[0];
  $result = portalCall($uid, 'createWebsiteRequest', '', [
    'organization' => $org['public_id'], 'project_name' => $label, 'business_name' => $label,
    'page_count' => 1, 'page_list' => 'Home', 'action' => 'submit',
    'primary_goal' => 'Describe this synthetic business', 'products_services' => 'Synthetic services',
  ], 201);
  $publicId = $result['website_request']['public_id'];
  $row = row($publicId);
  $entities = \Drupal::entityTypeManager();
  $campaign = $entities->getStorage('proof_campaign')->create([
    'campaign_id' => 'selected-drupal-' . $row['id'], 'prospect_id' => $row['prospect_id'],
    'business_name' => $label, 'generation_status' => 'ready', 'studio_job_id' => 'fixture-' . $row['id'],
  ]);
  $campaign->save();
  if ($normalCallback) {
    $portal->bindWebsiteRequestProofCampaign((int) $row['id'], (int) $row['prospect_id'], (int) $campaign->id());
    $variants = [];
    foreach (['a', 'b', 'c'] as $direction) {
      $variants[] = ['direction_id' => $direction,
        'html' => '<!doctype html><html lang="en"><head><title>Normal source ' . $direction . '</title></head><body><main><section data-section-type="intro" data-section-id="intro"><h1 data-field-type="text" data-field-id="heading">Synthetic heading</h1><p data-field-type="text" data-field-id="body">Synthetic source paragraph.</p></section></main></body></html>',
        'design_dna' => ['direction_name' => 'Normal synthetic ' . $direction],
      ];
    }
    check(callback(['event_id' => 'normal-source-' . $row['id'], 'campaign_id' => 'selected-drupal-' . $row['id'], 'job_id' => 'fixture-' . $row['id'], 'variants' => $variants])['newly_processed'], 'normal_signed_proof_callback_creates_real_variants_and_capture');
    $portal->saveWebsiteRequestProofResearchSnapshot((int) $row['id'], 1, [
      'overview' => 'Synthetic callback source for local integration only.', 'researched_at' => gmdate('Y-m-d'),
      'sources' => ['Synthetic local test author'], 'direction_rationale' => ['a' => 'Source A', 'b' => 'Source B', 'c' => 'Source C'],
    ]);
    $portal->approveWebsiteRequestProof((int) $row['id'], 1);
    return $publicId;
  }
  foreach (['a', 'b', 'c'] as $direction) {
    $path = 'web/proofs/selected-drupal-' . $row['id'] . '/' . $direction . '/index.html';
    $absolute = dirname(\Drupal::root()) . '/' . $path;
    mkdir(dirname($absolute), 0700, TRUE);
    $html = '<!doctype html><html lang="en"><head><title>Synthetic ' . $direction . '</title></head><body><main><h1>Disposable selected source ' . $direction . '</h1></main></body></html>';
    file_put_contents($absolute, $html);
    $continuation = [
      'request_binding' => \Drupal\famtastic_pipeline\Service\SelectedSourceIntent::requestBinding(array_replace($row, ['proof_campaign_id' => (string) $campaign->id()])),
      'operation' => 'package_existing', 'initiating_system' => 'designs', 'correlation_id' => 'fixture:' . $row['id'], 'requested_next_action' => 'protected_review',
      'spec' => ['capability_class' => 'static', 'site_needs' => ['pages' => ['home']]], 'required_pages' => ['index.html'],
      'files' => [['path' => 'index.html', 'source_path' => $path, 'url' => 'https://artifacts.example.test/' . $row['id'] . '/' . $direction . '/index.html', 'rights' => ['status' => 'approved', 'evidence_ref' => 'synthetic-authored-source']]],
      'source' => ['campaign_id' => (string) $campaign->id()],
      'selection' => ['direction_name' => 'Synthetic ' . $direction, 'proof_version' => '1', 'approval_id' => 'fixture-owner-review'],
      'brand' => ['design_contract' => ['schema_version' => 1, 'kind' => 'synthetic-static-contract']],
      'research_packet_ref' => ['packet_id' => 'fixture-research', 'brief_hash' => hash('sha256', 'synthetic-research')],
      'hosting_target' => ['staging_url' => 'https://fixture.famtasticinc.com/', 'target_path' => '/synthetic-never-deployed/' . $row['id'], 'remote_subdirectory' => 'fixture-' . $row['id']],
    ];
    $variant = $entities->getStorage('proof_variant')->create([
      'campaign_id' => $campaign->id(), 'direction_id' => $direction, 'direction_name' => 'Synthetic ' . $direction,
      'artifact_path' => $path, 'preview_url' => 'https://artifacts.example.test/' . $row['id'] . '/' . $direction,
      'design_dna' => json_encode($executable ? ['selected_build_continuation' => $continuation] : [], JSON_THROW_ON_ERROR),
    ]);
    $variant->save();
  }
  \Drupal::database()->update('famtastic_project_request')->fields([
    'proof_campaign_id' => $campaign->id(), 'proof_review_status' => 'customer_ready',
    'proof_approved_by_uid' => 1, 'proof_approved_at' => time(),
  ])->condition('id', $row['id'])->execute();
  return $publicId;
}

function stagingJob(array $packet): array {
  return \Drupal::database()->select('famtastic_job', 'j')->fields('j')
    ->condition('job_key', 'site-studio.staging:' . $packet['packet_id'])->execute()->fetchAssoc() ?: [];
}

function expectThrow(callable $fn, string $contains): void {
  try { $fn(); }
  catch (Throwable $error) {
    if (str_contains($error->getMessage(), $contains)) return;
    throw new RuntimeException('Unexpected exception: ' . $error->getMessage(), 0, $error);
  }
  throw new RuntimeException('Expected exception containing: ' . $contains);
}

check(TRUE, 'isolated_real_drupal_sqlite_memory_mail_network_disabled');
$entities = \Drupal::entityTypeManager();
$portal = \Drupal::service('famtastic_pipeline.customer_portal');
$registry = \Drupal::service('famtastic_pipeline.site_studio_build_packets');
$receipts = \Drupal::service('famtastic_pipeline.staging_receipts');

if ($phase === 'verify') {
  $saved = json_decode(file_get_contents($sandbox . '/state.json'), TRUE, 512, JSON_THROW_ON_ERROR);
  check(studio($saved['request'])['site_studio_build_packet'] === $saved['packet'], 'new_process_reloads_exact_packet');
  check(row($saved['request'])['staging_review_status'] === 'accepted' && $receipts->isReady((int) row($saved['request'])['id']), 'new_process_reloads_exact_acceptance');
  $before = counts();
  portalCall($saved['uid'], 'websiteStagingReviewAccept', $saved['request'], ['receipt_hash' => row($saved['request'])['staging_receipt_hash']]);
  check(studio($saved['request'])['site_studio_build_packet'] === $saved['packet'] && counts() === $before, 'new_process_acceptance_retry_is_idempotent');
  check(!callback($saved['receipt'])['newly_processed'] && counts() === $before, 'new_process_callback_retry_is_idempotent');
  check((int) $db->select('commerce_order', 'o')->countQuery()->execute()->fetchField() === 0, 'no_commerce_orders_or_payments');
  check((int) $db->select('famtastic_notification_outbox', 'n')->condition('status', 'sent')->countQuery()->execute()->fetchField() === 0, 'no_outbox_delivery_or_customer_mail');
  $report['status'] = 'passed';
  $report['assertion_count'] = count($report['checks']);
  print 'PASS: ' . $report['assertion_count'] . " installed Drupal assertions; durable verification complete.\n";
  return;
}

foreach (['owner', 'other'] as $name) {
  $user = $entities->getStorage('user')->create(['name' => $name . '@example.test', 'mail' => $name . '@example.test', 'pass' => bin2hex(random_bytes(20)), 'status' => 1]);
  $user->save();
  $customer = $portal->createCustomer($user, ['name' => 'Synthetic ' . $name, 'business_name' => 'Synthetic ' . $name]);
  $portal->markVerified((int) $customer['id']);
  $users[$name] = (int) $user->id();
}
$uid = $users['owner'];
$publicId = fixture($uid, 'Installed selected staging');
$initial = row($publicId);
$before = counts();
portalCall(0, 'websiteProofDecision', $publicId, ['direction' => 'a'], 401);
portalCall($users['other'], 'websiteProofDecision', $publicId, ['direction' => 'a'], 404);
check(row($publicId) === $initial && counts() === $before, 'anonymous_and_other_account_selection_denied_without_writes');
portalCall($uid, 'websiteProofDecision', $publicId, ['direction' => 'a']);
$selected = row($publicId);
$packet = studio($publicId)['site_studio_build_packet'];
check($selected['proof_review_status'] === 'selected' && $selected['selected_proof_direction'] === 'a' && (int) $selected['project_id'] > 0, 'authenticated_selection_persists_request_and_project');
check($packet['continuation']['selection_revision'] === 1 && $packet['continuation']['customer']['id'] === (string) $selected['customer_id'] && $packet['request_id'] === $publicId, 'packet_binds_real_customer_request_revision');
check(json_decode(stagingJob($packet)['payload'], TRUE)['packet'] === $packet, 'queued_job_contains_exact_registered_packet');
check(!StagingReceiptService::checkoutGateSatisfied($selected), 'selection_does_not_enable_checkout');
$before = counts();
portalCall($uid, 'websiteProofDecision', $publicId, ['direction' => 'a']);
check(counts() === $before && studio($publicId)['site_studio_build_packet'] === $packet, 'duplicate_selection_preserves_packet_job_and_event_counts');
check(!$registry->registerPacket($packet)['newly_registered'] && counts() === $before, 'duplicate_registry_registration_is_idempotent');
$changed = $packet; $changed['created_at'] = 'changed';
expectThrow(static fn() => $registry->registerPacket($changed), 'revision');
check(counts() === $before && studio($publicId)['site_studio_build_packet'] === $packet, 'changed_same_revision_packet_rejected');

$firstReceipt = receipt($packet, 'installed-first');
callback($firstReceipt, 400, FALSE);
check(row($publicId)['staging_receipt_hash'] === '' && counts() === $before, 'invalid_hmac_callback_has_no_writes');
foreach (['customer_id' => '999999', 'website_request_id' => 999999, 'project_id' => '999999', 'selection_revision' => 99, 'artifact_manifest_sha256' => str_repeat('e', 64), 'staging_url' => 'https://wrong.famtasticinc.com/', 'customer_accepted' => TRUE] as $field => $value) {
  callback(array_replace($firstReceipt, [$field => $value]), 422);
}
check(row($publicId)['staging_receipt_hash'] === '' && counts() === $before, 'callback_identity_manifest_target_and_acceptance_spoof_rejected');
check(callback($firstReceipt)['newly_processed'], 'signed_callback_persists_receipt');
$receiptHash = row($publicId)['staging_receipt_hash'];
check($receiptHash === hash('sha256', json_encode($firstReceipt, JSON_UNESCAPED_SLASHES)) && !$receipts->isReady((int) $selected['id']), 'stored_exact_receipt_pending_customer_acceptance');
$before = counts();
check(!callback($firstReceipt)['newly_processed'] && counts() === $before, 'duplicate_callback_has_one_ledger_event_and_outbox_row');
callback(array_replace($firstReceipt, ['artifact_sha256' => str_repeat('d', 64)]), 422);
portalCall(0, 'websiteStagingReviewAccept', $publicId, ['receipt_hash' => $receiptHash], 401);
portalCall($users['other'], 'websiteStagingReviewAccept', $publicId, ['receipt_hash' => $receiptHash], 404);
portalCall($uid, 'websiteStagingReviewAccept', $publicId, [], 422);
portalCall($uid, 'websiteStagingReviewAccept', $publicId, ['receipt_hash' => str_repeat('f', 64)], 422);
check(row($publicId)['staging_review_status'] === 'pending' && counts() === $before, 'ownership_missing_and_stale_acceptance_rejected');
$accepted = portalCall($uid, 'websiteStagingReviewAccept', $publicId, ['receipt_hash' => $receiptHash]);
check($receipts->isReady((int) $selected['id']) && $accepted['website_request']['direct_checkout_available'] === TRUE, 'owner_accepts_current_receipt_and_gate_opens');
$before = counts();
portalCall($uid, 'websiteStagingReviewAccept', $publicId, ['receipt_hash' => $receiptHash]);
check(counts() === $before, 'duplicate_acceptance_has_one_activity');

$notes = 'Keep direction A; adjust the contact heading.';
portalCall($uid, 'websiteProofDecision', $publicId, ['action' => 'revision', 'notes' => $notes]);
$revision = studio($publicId)['site_studio_build_packet'];
check($revision['continuation']['selection_revision'] === 2 && $revision['project_id'] === $packet['project_id'] && $revision['selected_artifacts'] === $packet['selected_artifacts'], 'revision_keeps_same_project_and_source_and_increments_packet');
check($revision['continuation']['operation'] === 'continue_build' && $revision['continuation']['requested_changes'] === $notes && $revision['continuation']['remaining_stages'] === ['apply_customer_revision'], 'revision_packet_preserves_exact_customer_notes');
$history = studio($publicId);
check($history['site_studio_build_packet_history'][0] === $packet && $history['site_studio_staging_history'][0]['receipt_hash'] === $receiptHash && $history['site_studio_staging_history'][0]['review_status'] === 'accepted', 'old_packet_receipt_and_acceptance_preserved_in_history');
check(!$receipts->isReady((int) $selected['id']) && row($publicId)['staging_receipt_hash'] === '' && json_decode(stagingJob($revision)['payload'], TRUE)['packet'] === $revision, 'revision_invalidates_acceptance_and_queues_exact_successor');
check($db->select('famtastic_notification_outbox', 'n')->fields('n', ['status'])->condition('notification_key', 'website-request:' . $selected['id'] . ':staging-review-ready:' . $receiptHash)->execute()->fetchField() === 'superseded', 'revision_supersedes_pending_ready_notification');
$before = counts();
portalCall($uid, 'websiteProofDecision', $publicId, ['action' => 'revision', 'notes' => $notes]);
check(counts() === $before && studio($publicId)['site_studio_build_packet'] === $revision, 'duplicate_revision_is_idempotent');
callback($firstReceipt, 422);
portalCall($uid, 'websiteStagingReviewAccept', $publicId, ['receipt_hash' => $receiptHash], 422);
expectThrow(static fn() => $registry->assertActiveSelectedPacket($packet), 'superseded');
check(counts() === $before, 'stale_callback_acceptance_and_dispatch_are_rejected');

// Late failure occurs after packet/entity/event writes; real nested transactions
// must roll back all of them when the operational job cannot be inserted.
$beforeRow = row($publicId); $beforeStudio = studio($publicId); $before = counts();
$db->query("CREATE TRIGGER selected_fail_job BEFORE INSERT ON famtastic_job WHEN NEW.job_type = 'site_studio_staging_prepare' BEGIN SELECT RAISE(ABORT, 'selected_test_job_failure'); END", [], ['allow_delimiter_in_query' => TRUE]);
try {
  expectThrow(static fn() => $portal->decideWebsiteRequestProof((int) $selected['customer_id'], $publicId, ['action' => 'revision', 'notes' => 'Force a late job rollback.']), 'selected_test_job_failure');
} finally { $db->query('DROP TRIGGER selected_fail_job'); }
check(row($publicId) === $beforeRow && studio($publicId) === $beforeStudio && counts() === $before, 'late_revision_job_failure_rolls_back_request_entity_history_events');

$secondReceipt = receipt($revision, 'installed-second');
$db->query("CREATE TRIGGER selected_fail_event BEFORE INSERT ON famtastic_event WHEN NEW.event_type = 'site_studio.staging_deployed' BEGIN SELECT RAISE(ABORT, 'selected_test_event_failure'); END", [], ['allow_delimiter_in_query' => TRUE]);
try { expectThrow(static fn() => callback($secondReceipt), 'selected_test_event_failure'); }
finally { $db->query('DROP TRIGGER selected_fail_event'); }
check(row($publicId) === $beforeRow && counts() === $before, 'event_integrity_failure_is_not_misclassified_as_duplicate');
$db->query("CREATE TRIGGER selected_fail_outbox BEFORE INSERT ON famtastic_notification_outbox WHEN NEW.category = 'project_staging_review_ready' BEGIN SELECT RAISE(ABORT, 'selected_test_outbox_failure'); END", [], ['allow_delimiter_in_query' => TRUE]);
try { expectThrow(static fn() => callback($secondReceipt), 'selected_test_outbox_failure'); }
finally { $db->query('DROP TRIGGER selected_fail_outbox'); }
check(row($publicId) === $beforeRow && counts() === $before, 'late_callback_outbox_failure_rolls_back_receipt_and_event');
check(callback($secondReceipt)['newly_processed'], 'callback_retry_after_rollback_succeeds');
$secondHash = row($publicId)['staging_receipt_hash'];
// A trigger simulates a receipt change between acceptance SELECT and UPDATE.
$db->query("CREATE TRIGGER selected_accept_race BEFORE UPDATE OF staging_review_status ON famtastic_project_request WHEN NEW.staging_review_status = 'accepted' AND NEW.id = " . (int) $selected['id'] . " BEGIN UPDATE famtastic_project_request SET staging_receipt_hash = 'simulated-concurrent-change' WHERE id = NEW.id; SELECT RAISE(IGNORE); END", [], ['allow_delimiter_in_query' => TRUE]);
try { portalCall($uid, 'websiteStagingReviewAccept', $publicId, ['receipt_hash' => $secondHash], 404); }
finally { $db->query('DROP TRIGGER selected_accept_race'); }
check(row($publicId)['staging_review_status'] === 'pending', 'conditional_acceptance_update_rejects_interleaved_receipt_change');
$db->update('famtastic_project_request')->fields(['staging_receipt_hash' => $secondHash])->condition('id', (int) $selected['id'])->execute();
portalCall($uid, 'websiteStagingReviewAccept', $publicId, ['receipt_hash' => $secondHash]);

$rollbackId = fixture($uid, 'Rollback initial selection');
$beforeRow = row($rollbackId); $before = counts();
$db->query("CREATE TRIGGER selected_fail_initial BEFORE INSERT ON famtastic_job WHEN NEW.job_type = 'site_studio_staging_prepare' BEGIN SELECT RAISE(ABORT, 'selected_test_initial_failure'); END", [], ['allow_delimiter_in_query' => TRUE]);
try { expectThrow(static fn() => $portal->decideWebsiteRequestProof((int) $beforeRow['customer_id'], $rollbackId, ['direction' => 'a']), 'selected_test_initial_failure'); }
finally { $db->query('DROP TRIGGER selected_fail_initial'); }
$entities->getStorage('proof_campaign')->resetCache();
check(row($rollbackId) === $beforeRow && counts() === $before && !$entities->getStorage('proof_campaign')->load($beforeRow['proof_campaign_id'])->get('selected_variant')->value, 'late_initial_selection_failure_rolls_back_project_resource_and_campaign');

$failureId = fixture($uid, 'Unsupported selected revision');
portalCall($uid, 'websiteProofDecision', $failureId, ['direction' => 'a']);
$failure = receipt(studio($failureId)['site_studio_build_packet'], 'installed-failure');
$failure = array_diff_key($failure, array_flip(['staging_url', 'artifact_sha256', 'target_path', 'remote_subdirectory', 'qa', 'repository']));
$failure['schema'] = 'famtastic.site-studio.staging-failure.v1'; $failure['status'] = 'failed';
$failure['error'] = ['code' => 'unsupported_revision_executor', 'stage' => 'build'];
$before = counts();
check(callback($failure)['newly_processed'], 'signed_failure_callback_persists_actionable_failure');
check(row($failureId)['staging_status'] === 'failed' && !$receipts->isReady((int) row($failureId)['id']) && counts()['famtastic_notification_outbox'] === $before['famtastic_notification_outbox'], 'failure_never_queues_ready_mail_or_opens_checkout');
check($portal->websiteRequestProofHandoff((int) row($failureId)['id'])['state'] === 'needs_attention', 'failed_build_handoff_displays_needs_attention');
$before = counts();
check(!callback($failure)['newly_processed'] && counts() === $before, 'duplicate_failure_callback_is_idempotent');

$planningId = fixture($uid, 'Missing executable source metadata', FALSE);
portalCall($uid, 'websiteProofDecision', $planningId, ['direction' => 'a']);
$planning = studio($planningId);
check(row($planningId)['proof_review_status'] === 'selected' && row($planningId)['staging_status'] === 'planning' && $planning['selected_dispatch_packet']['schema'] === 'famtastic.site-studio.planning-packet.v1' && !isset($planning['site_studio_build_packet']), 'missing_metadata_preserves_selection_and_queues_nonready_planning');
check(!$receipts->isReady((int) row($planningId)['id']), 'planning_is_not_review_or_checkout_ready');
$planningPacket = $planning['selected_dispatch_packet'];
$intent = $planningPacket['intent'];
$planningResult = [
  'schema' => 'famtastic.site-studio.planning-result.v1', 'status' => 'planning_complete', 'event_id' => 'installed-planning-blocked',
  'website_request_id' => $intent['website_request_id'], 'customer_id' => $intent['customer_id'],
  'intent_id' => $intent['intent_id'], 'intent_sha256' => $planningPacket['intent_sha256'], 'selection_revision' => $intent['selection']['revision'],
  'selected_direction_id' => $planningPacket['selected_direction_ids'][0], 'selected_artifact_sha256' => $planningPacket['selected_artifacts'][0]['source_artifact_sha256'],
  'ready' => FALSE, 'customer_accepted' => FALSE, 'checkout_eligible' => FALSE, 'final_launch' => FALSE,
  'plan' => ['schema' => 'famtastic.selected-source-plan.v1', 'operation' => 'continue_build', 'source_artifacts' => $intent['source']['artifacts'],
    'requested_scope' => $intent['scope'], 'intent_id' => $intent['intent_id'], 'ready' => FALSE, 'executable' => FALSE,
    'issues' => [['code' => 'synthetic_missing_execution_authority']]],
] + array_intersect_key($planningPacket, array_flip(['packet_id', 'idempotency_key', 'request_id', 'project_id', 'artifact_manifest_sha256']));
check(callback($planningResult)['newly_processed'] && row($planningId)['staging_status'] === 'planning_blocked' && $portal->websiteRequestProofHandoff((int) row($planningId)['id'])['state'] === 'needs_attention', 'signed_planning_blocked_result_displays_needs_attention');
$before = counts();
check(!callback($planningResult)['newly_processed'] && counts() === $before && !$receipts->isReady((int) row($planningId)['id']), 'duplicate_blocked_plan_is_idempotent_and_nonready');

// Exercise the current normal producer too: no prebuilt continuation on DNA.
// Source comes through the signed callback; normal selection creates the project
// before this synthetic installation config can bind its local review target.
$normalId = fixture($uid, 'Normal callback and authored pages', FALSE, TRUE);
portalCall($uid, 'websiteProofDecision', $normalId, ['direction' => 'a']);
$normalRow = row($normalId);
\Drupal::configFactory()->getEditable('famtastic_pipeline.settings')->set('selected_staging', [
  'artifact_base_url' => 'https://agency.example.test',
  'authored_shell_policy' => ['status' => 'approved', 'evidence_ref' => 'synthetic-source-author'],
  'targets' => [(string) $normalRow['project_id'] => ['customer_id' => (string) $normalRow['customer_id'],
    'staging_url' => 'https://fixture.famtasticinc.com/', 'target_path' => '/synthetic-never-deployed/normal', 'remote_subdirectory' => 'normal']],
])->save();
$beforeRow = row($normalId); $beforeStudio = studio($normalId); $before = counts();
$db->query("CREATE TRIGGER selected_fail_refresh BEFORE INSERT ON famtastic_job WHEN NEW.job_type = 'site_studio_staging_prepare' BEGIN SELECT RAISE(ABORT, 'selected_test_refresh_failure'); END", [], ['allow_delimiter_in_query' => TRUE]);
try { expectThrow(static fn() => $portal->refreshSelectedWebsiteRequest((int) $normalRow['customer_id'], $normalId), 'selected_test_refresh_failure'); }
finally { $db->query('DROP TRIGGER selected_fail_refresh'); }
check(row($normalId) === $beforeRow && studio($normalId) === $beforeStudio && counts() === $before, 'normal_source_refresh_job_failure_rolls_back_packet_and_events');
$portal->refreshSelectedWebsiteRequest((int) $normalRow['customer_id'], $normalId);
$normalPacket = studio($normalId)['site_studio_build_packet'];
check($normalPacket['continuation']['operation'] === 'package_existing' && $normalPacket['continuation']['brand']['design_contract']['kind'] === 'selected-source-preservation-v1', 'normal_callback_source_resolves_executable_packet_without_seeded_continuation');
$normalVariant = $entities->getStorage('proof_variant')->load($normalPacket['continuation']['selection']['proof_variant_id']);
$normalDna = json_decode($normalVariant->get('design_dna')->value, TRUE);
check(!isset($normalDna['selected_build_continuation']) && isset($normalDna['source_capture']) && json_decode(stagingJob($normalPacket)['payload'], TRUE)['packet'] === $normalPacket, 'normal_source_capture_and_job_packet_are_persisted');
$sourceHash = $normalPacket['selected_artifacts'][0]['source_artifact_sha256'];
$timestamp = (string) time();
$authorization = 'FAMtastic-Artifact ' . $timestamp . ':' . hash_hmac('sha256', "selected-artifact.v1\n$normalId\n" . $normalPacket['continuation']['selection_revision'] . "\n$sourceHash\n$timestamp", getenv('FAMTASTIC_STUDIO_DISPATCH_SECRET'));
$artifactRequest = Request::create('/api/site-studio/selected-artifacts/' . $normalId);
$artifactRequest->headers->set('Authorization', $authorization);
$artifactResponse = SiteStudioCallbackController::create(\Drupal::getContainer())->artifact($artifactRequest, $normalId, $normalPacket['continuation']['selection_revision'], $sourceHash);
check($artifactResponse->getStatusCode() === 200 && hash('sha256', $artifactResponse->getContent()) === $sourceHash, 'authenticated_artifact_controller_returns_exact_stored_source_bytes');
portalCall($uid, 'updateWebsiteRequest', $normalId, [
  'project_name' => 'Normal callback and authored pages', 'page_count' => 2, 'page_list' => "Home\nAbout",
  'page_content' => [['page_name' => 'About', 'title' => 'Synthetic About', 'heading' => 'About our synthetic service', 'body' => 'Exact customer-authored test paragraph.']],
]);
$normalRevision = studio($normalId)['site_studio_build_packet'];
check($normalRevision['continuation']['selection_revision'] === $normalPacket['continuation']['selection_revision'] + 1 && $normalRevision['continuation']['recipe']['id'] === 'legacy-shared-shell-v1' && $normalRevision['continuation']['required_pages'] === ['index.html', 'about.html'], 'normal_authenticated_content_update_persists_executable_revision_recipe');
check($normalRevision['project_id'] === $normalPacket['project_id'] && $normalRevision['selected_artifacts'] === $normalPacket['selected_artifacts'] && count($normalRevision['artifacts']) === 3 && json_decode(stagingJob($normalRevision)['payload'], TRUE)['packet'] === $normalRevision, 'normal_revision_reuses_source_and_persists_content_permission_records');
$step = $normalRevision['continuation']['recipe']['steps'][0];
$contentBytes = file_get_contents(dirname(\Drupal::root()) . '/' . $step['content_source_path']);
check(hash('sha256', $contentBytes) === $step['content_sha256'] && json_decode($contentBytes, TRUE)['fields']['intro/body'] === 'Exact customer-authored test paragraph.', 'normal_revision_file_digest_and_authored_text_match');
$before = counts();
callback(receipt($normalRevision, 'normal-missing-completion'), 422);
check(counts() === $before && row($normalId)['staging_status'] === 'queued', 'normal_source_receipt_without_completion_rolls_back_receipt_and_event');

// The ordinary authored-content writer must share the same atomicity contract
// as the explicit revision endpoint, including the customer's saved intake.
$beforeRow = row($normalId); $beforeStudio = studio($normalId); $before = counts();
$db->query("CREATE TRIGGER selected_fail_content BEFORE INSERT ON famtastic_job WHEN NEW.job_type = 'site_studio_staging_prepare' BEGIN SELECT RAISE(ABORT, 'selected_test_content_failure'); END", [], ['allow_delimiter_in_query' => TRUE]);
try {
  expectThrow(static fn() => $portal->updateWebsiteRequest((int) $normalRow['customer_id'], $normalId, [
    'project_name' => 'Normal callback and authored pages', 'page_count' => 2, 'page_list' => "Home\nAbout",
    'page_content' => [['page_name' => 'About', 'title' => 'Changed About', 'heading' => 'Changed synthetic heading', 'body' => 'This update must roll back with its job.']],
  ]), 'selected_test_content_failure');
} finally { $db->query('DROP TRIGGER selected_fail_content'); }
check(row($normalId) === $beforeRow && studio($normalId) === $beforeStudio && counts() === $before, 'normal_content_update_job_failure_rolls_back_intake_packet_and_events');

// P1 regression: immutable embedded build evidence must not silently cover a
// later request. These edits use the real authenticated writer, not a serializer.
foreach ([
  'added_page' => ['page_count' => 2, 'page_list' => "Home\nAbout", 'page_content' => [['page_name' => 'About', 'title' => 'About', 'heading' => 'About us', 'body' => 'New customer-authored About copy.']]],
  'same_count_copy' => ['page_content' => [['page_name' => 'Home', 'title' => 'Updated Home', 'heading' => 'New heading', 'body' => 'Changed copy with exactly the same page count.']]],
  'commerce_scope' => ['ecommerce_details' => 'WooCommerce product catalog and checkout'],
  'project_type_only' => ['project_type' => 'online_store'],
] as $case => $change) {
  $editId = fixture($uid, 'Embedded scope ' . $case);
  $baseInput = ['project_name' => 'Embedded scope ' . $case, 'business_name' => 'Embedded scope ' . $case,
    'action' => 'save', 'page_count' => 1, 'page_list' => 'Home',
    'primary_goal' => 'Describe this synthetic business', 'products_services' => 'Synthetic services'];
  portalCall($uid, 'websiteProofDecision', $editId, ['direction' => 'a']);
  $oldPacket = studio($editId)['site_studio_build_packet'];
  $before = counts();
  portalCall($uid, 'updateWebsiteRequest', $editId, $baseInput);
  check(studio($editId)['site_studio_build_packet'] === $oldPacket && counts() === $before, 'embedded_' . $case . '_unchanged_save_keeps_one_packet_job');
  $oldReceipt = receipt($oldPacket, 'embedded-old-' . $case);
  callback($oldReceipt);
  $oldHash = row($editId)['staging_receipt_hash'];
  portalCall($uid, 'websiteStagingReviewAccept', $editId, ['receipt_hash' => $oldHash]);
  $before = counts();
  portalCall($uid, 'updateWebsiteRequest', $editId, array_replace($baseInput, $change));
  $edited = row($editId); $state = studio($editId); $planned = $state['selected_dispatch_packet'];
  check($edited['staging_status'] === 'planning' && $planned['schema'] === 'famtastic.site-studio.planning-packet.v1'
    && $planned['intent']['selection']['revision'] === 2 && $state['site_studio_build_packet'] === $oldPacket
    && json_decode(stagingJob($planned)['payload'], TRUE)['packet'] === $planned,
    'embedded_' . $case . '_queues_exact_planning_not_stale_executable');
  $savedIntake = json_decode($edited['intake_data'], TRUE);
  check($planned['intent']['scope']['snapshot']['page_count'] === $savedIntake['page_count']
    && $planned['intent']['scope']['snapshot']['project_type'] === $edited['project_type']
    && $planned['intent']['authored_content'] === $savedIntake['authored_content']
    && $planned['selected_artifacts'] === $oldPacket['selected_artifacts'], 'embedded_' . $case . '_preserves_current_request_and_winning_source');
  check($edited['staging_receipt_hash'] === '' && $edited['staging_review_status'] === 'not_started'
    && !$receipts->isReady((int) $edited['id'])
    && $state['site_studio_staging_history'][0]['receipt_hash'] === $oldHash
    && $state['site_studio_staging_history'][0]['review_status'] === 'accepted'
    && counts()['famtastic_notification_outbox'] === $before['famtastic_notification_outbox'], 'embedded_' . $case . '_invalidates_readiness_preserves_receipt_without_new_mail');
  check($db->select('famtastic_notification_outbox', 'n')->fields('n', ['status'])->condition('notification_key', 'website-request:' . $edited['id'] . ':staging-review-ready:' . $oldHash)->execute()->fetchField() === 'superseded', 'embedded_' . $case . '_supersedes_old_review_notice');
  callback(array_replace($oldReceipt, ['event_id' => 'embedded-late-' . $case]), 422);
  portalCall($uid, 'websiteStagingReviewAccept', $editId, ['receipt_hash' => $oldHash], 422);
  expectThrow(static fn() => $registry->assertActiveSelectedPacket($oldPacket), 'superseded');
  $before = counts();
  $portal->refreshSelectedWebsiteRequest((int) $edited['customer_id'], $editId);
  check(counts() === $before && studio($editId)['selected_dispatch_packet'] === $planned, 'embedded_' . $case . '_retry_is_idempotent_and_old_work_rejected');
}

$unboundId = fixture($uid, 'Legacy embedded evidence has no baseline');
$unboundRow = row($unboundId);
$unboundVariants = $entities->getStorage('proof_variant');
$variantIds = $unboundVariants->getQuery()->accessCheck(FALSE)->condition('campaign_id', $unboundRow['proof_campaign_id'])->execute();
foreach ($unboundVariants->loadMultiple($variantIds) as $variant) {
  $dna = json_decode($variant->get('design_dna')->value, TRUE);
  unset($dna['selected_build_continuation']['request_binding']);
  $variant->set('design_dna', json_encode($dna, JSON_THROW_ON_ERROR))->save();
}
portalCall($uid, 'websiteProofDecision', $unboundId, ['direction' => 'a']);
check(row($unboundId)['staging_status'] === 'planning' && !isset(studio($unboundId)['site_studio_build_packet']), 'unbound_legacy_evidence_cannot_be_blessed_at_first_selection');

$preEditId = fixture($uid, 'Scope changed before selection');
portalCall($uid, 'updateWebsiteRequest', $preEditId, ['project_name' => 'Scope changed before selection', 'project_type' => 'online_store', 'page_count' => 1, 'page_list' => 'Home']);
portalCall($uid, 'websiteProofDecision', $preEditId, ['direction' => 'a']);
check(row($preEditId)['staging_status'] === 'planning' && !isset(studio($preEditId)['site_studio_build_packet']), 'preselection_scope_change_cannot_reuse_old_static_evidence');

// Producer -> real association issue/accept must use one canonical scope.
// This synthetic verified-source envelope proves persistence/validation only,
// not a real Studio build or server-side HMAC endpoint execution.
$associationId = fixture($uid, 'Source association scope contract', FALSE, TRUE);
portalCall($uid, 'websiteProofDecision', $associationId, ['direction' => 'a']);
$associationRow = row($associationId); $associationState = studio($associationId);
$identity = ['project_id' => (string) $associationRow['project_id'], 'customer_id' => (string) $associationRow['customer_id'], 'request_id' => $associationId];
$association = $registry->issueSourceAssociation($identity, 'synthetic-association-secret')['association'];
$grantPayload = json_decode($association['payload_json'], TRUE);
check($grantPayload['intent'] === $associationState['selected_source_intent']
  && $grantPayload['intent']['scope']['snapshot']['project_type'] === 'new_website'
  && hash_equals(hash_hmac('sha256', "famtastic.source-association.v1\n" . $association['payload_json'], 'synthetic-association-secret'), $association['signature']),
  'real_source_association_issue_accepts_current_producer_scope');
$home = $associationState['selected_source_intent']['source']['artifacts'][0];
$manifestHash = hash('sha256', 'synthetic-association-manifest');
$record = ['schema' => 'famtastic.finalized-source.v1', 'site_id' => 'synthetic-associated-site', 'run_id' => 'synthetic-associated-run',
  'repository' => ['repository_path' => '/synthetic-only/associated-site'], 'manifest_sha256' => $manifestHash,
  'scope' => ['request_scope_sha256' => $grantPayload['scope_sha256'], 'evidence_ref' => 'association:' . $grantPayload['association_id'], 'required_pages' => ['index.html']],
  'scope_complete' => TRUE, 'issues' => [], 'use_restrictions' => [],
  'files' => [['path' => 'index.html', 'sha256' => $home['sha256'], 'bytes' => $home['bytes']]],
  'review_qa' => ['passed' => TRUE, 'problems' => [], 'source_binding' => ['manifest_sha256' => $manifestHash, 'site_id' => 'synthetic-associated-site', 'run_id' => 'synthetic-associated-run']]];
$recordJson = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$export = ['schema' => 'famtastic.finalized-source-wire.v2', 'payload_json' => $recordJson, 'sha256' => hash('sha256', "famtastic.finalized-source-wire.v2\n" . $recordJson)];
$mapping = $identity + ['association_id' => $grantPayload['association_id'], 'association_scope_sha256' => $grantPayload['scope_sha256'],
  'originating_system' => 'studio', 'handoff_initiator' => 'studio', 'site_id' => $record['site_id'], 'run_id' => $record['run_id'],
  'repository_path' => $record['repository']['repository_path'], 'evidence_ref' => 'synthetic-verified-source', 'source_export_sha256' => $export['sha256'], 'source_export' => $export];
$envelope = $identity + ['association' => $association, 'source_export' => $export, 'source_completion' => $mapping];
check($registry->registerSourceExport($envelope)['newly_processed'] && studio($associationId)['selected_source_mapping'] === $mapping,
  'real_source_association_accept_persists_exact_verified_mapping');
check(!$registry->registerSourceExport($envelope)['newly_processed'], 'real_source_association_retry_is_idempotent');
$changedRow = array_replace($associationRow, ['project_type' => 'online_store']);
expectThrow(static fn() => \Drupal\famtastic_pipeline\Service\SelectedSourceAssociation::issue($changedRow, studio($associationId), 'synthetic-association-secret', time()), 'source_association_current_input_changed');
portalCall($uid, 'updateWebsiteRequest', $associationId, ['project_name' => 'Source association scope contract', 'project_type' => 'online_store', 'page_count' => 1, 'page_list' => 'Home']);
expectThrow(static fn() => $registry->registerSourceExport($envelope), 'source_association_stale');
check(studio($associationId)['selected_source_mapping'] === $mapping && row($associationId)['staging_status'] === 'planning',
  'changed_scope_rejects_stale_association_without_replacing_recorded_source');

// Simulate a historical mapped intent that already matches today's facts but
// whose embedded executable never recorded a trustworthy baseline. The old
// unchanged-intent shortcut must not rescue it, even if its policy label exists.
$mappedId = fixture($uid, 'Legacy mapped shortcut');
portalCall($uid, 'websiteProofDecision', $mappedId, ['direction' => 'a']);
$mappedRow = row($mappedId); $mappedState = studio($mappedId);
$mappedOldPacket = $mappedState['site_studio_build_packet'];
$mappedVariant = $unboundVariants->load($mappedOldPacket['continuation']['selection']['proof_variant_id']);
$mappedDna = json_decode($mappedVariant->get('design_dna')->value, TRUE);
unset($mappedDna['selected_build_continuation']['request_binding']);
$mappedVariant->set('design_dna', json_encode($mappedDna, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))->save();
$mappedState['selected_source_intent']['source']['design_dna'] = $mappedDna;
$mappedState['selected_source_intent']['source']['design_dna_sha256'] = hash('sha256', json_encode($mappedDna, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$mappedState['selected_source_intent']['execution_binding']['association_id'] = 'synthetic-legacy-association';
$mappedState['selected_source_mapping'] = ['association_id' => 'synthetic-legacy-association'];
$entities->getStorage('famtastic_project')->load($mappedRow['project_id'])->set('studio_json', json_encode($mappedState, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))->save();
$portal->refreshSelectedWebsiteRequest((int) $mappedRow['customer_id'], $mappedId);
check(row($mappedId)['staging_status'] === 'planning'
  && studio($mappedId)['selected_dispatch_packet']['intent']['selection']['revision'] === 2,
  'unbound_mapped_intent_cannot_bypass_guard_via_unchanged_shortcut');
expectThrow(static fn() => $registry->assertActiveSelectedPacket($mappedOldPacket), 'superseded');

file_put_contents($sandbox . '/state.json', json_encode(['uid' => $uid, 'request' => $publicId, 'packet' => $revision, 'receipt' => $secondReceipt], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$report['records'] = ['request' => $publicId, 'project_id' => $revision['project_id'], 'packet_id' => $revision['packet_id'], 'counts' => counts()];
$report['status'] = 'awaiting_fresh_process_verification';
print "PASS: installed Drupal scenarios complete; verifying from a fresh process next.\n";
