<?php

declare(strict_types=1);

/**
 * Installed SQLite proof of the signed operator prepare path for nine synthetic
 * industry prospects. This script never invokes dispatch or a mail transport.
 * Run only inside the separately copied acquisition Drupal sandbox.
 */
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\AcquisitionIndustryTemplate;

$sandbox = realpath((string) getenv('ACQUISITION_DRUPAL_SANDBOX')) ?: '';
$db = \Drupal::database();
$options = $db->getConnectionOptions();
if (!preg_match('#/famtastic-acquisition-drupal\.[a-zA-Z0-9]{6}$#D', $sandbox)
  || realpath(__DIR__) !== $sandbox . '/scripts'
  || realpath(\Drupal::root()) !== $sandbox . '/backend/web'
  || ($options['driver'] ?? '') !== 'sqlite'
  || realpath((string) ($options['database'] ?? '')) !== $sandbox . '/backend/web/sites/default/files/.ht.sqlite'
  || getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') !== 'memory'
  || function_exists('curl_exec')
  || function_exists('mail')
  || ini_get('allow_url_fopen') !== '0') {
  throw new RuntimeException('Industry operator proof isolation required.');
}

$bundle = $sandbox . '/backend/private/industry-d0';
$manifest = $bundle . '/manifest.json';
if (!is_file($manifest) || is_link($manifest)) throw new RuntimeException('Private industry bundle required.');
new Settings(array_replace(Settings::getAll(), [
  'famtastic_acquisition_industry_bundle_root' => $bundle,
  'famtastic_acquisition_industry_bundle_sha256' => hash_file('sha256', $manifest),
]));
$GLOBALS['config']['smtp.settings'] = ['smtp_on' => TRUE, 'smtp_host' => 'smtp.synthetic.invalid', 'smtp_port' => 587, 'smtp_username' => 'sender@example.test', 'smtp_from' => 'sender@example.test', 'smtp_protocol' => 'tls'];
$GLOBALS['config']['famtastic_pipeline.settings'] = array_replace($GLOBALS['config']['famtastic_pipeline.settings'] ?? [], ['outreach_postal_address' => '123 Fictional Test Street, Example City, FL 00000']);
\Drupal::service('config.factory')->reset('smtp.settings')->reset('famtastic_pipeline.settings');

require_once __DIR__ . '/acquisition-window-contact.php';
define('FAMTASTIC_ACQUISITION_OPERATOR_LIBRARY_ONLY', TRUE);
require_once __DIR__ . '/acquisition-exact-operator.php';

$private = realpath((string) Settings::get('file_private_path', ''));
if (!$private || is_link($private)) throw new RuntimeException('Private Drupal directory required.');
$operatorDir = $private . '/acquisition-199';
if (!is_dir($operatorDir)) {
  $oldMask = umask(0077);
  try { if (!mkdir($operatorDir, 0700) && !is_dir($operatorDir)) throw new RuntimeException('Private operator directory creation failed.'); }
  finally { umask($oldMask); }
}
if (is_link($operatorDir) || realpath($operatorDir) !== $operatorDir || (fileperms($operatorDir) & 0077) !== 0) throw new RuntimeException('Private operator directory mode required.');

$evidenceDir = realpath((string) getenv('ACQUISITION_DRUPAL_EVIDENCE')) ?: '';
if ($evidenceDir === '' || !str_starts_with($evidenceDir . '/', $sandbox . '/')) throw new RuntimeException('Sandbox evidence path required.');
$report = [
  'schema' => 'famtastic.acquisition-industry-operator-installed-proof.v1',
  'classification' => 'local_synthetic',
  'status' => 'running',
  'checks' => [],
  'industries' => [],
  'operator_mode' => 'prepare',
  'dispatch_invoked' => FALSE,
  'customer_sends' => FALSE,
  'provider_transport_calls' => 0,
  'production_deployment' => FALSE,
];
register_shutdown_function(static function () use (&$report, $evidenceDir): void {
  if (($report['status'] ?? '') === 'running') {
    $last = error_get_last();
    if ($last && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], TRUE)) $report['status'] = 'failed';
  }
  $report['ended_at'] = gmdate('c');
  file_put_contents($evidenceDir . '/operator-industry-evidence.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
});
$check = static function (bool $passed, string $name) use (&$report): void {
  $report['checks'][$name] = $passed;
  if (!$passed) {
    $report['status'] = 'failed';
    throw new RuntimeException('FAIL: ' . $name);
  }
  print 'PASS: ' . $name . "\n";
};

$approvalBytes = (string) file_get_contents($bundle . '/' . AcquisitionIndustryTemplate::APPROVAL_REFERENCE);
$approval = json_decode($approvalBytes, TRUE, 64, JSON_THROW_ON_ERROR);
if (($approval['schema'] ?? '') !== AcquisitionIndustryTemplate::APPROVAL_SCHEMA || count($approval['templates'] ?? []) !== 9) throw new RuntimeException('Nine-template approval bundle required.');
$campaignId = (int) $db->select('famtastic_campaign', 'c')->fields('c', ['id'])->condition('campaign_key', 'acquisition-199')->execute()->fetchField();
if ($campaignId < 1) throw new RuntimeException('Acquisition campaign missing from isolated database.');
$now = \Drupal::time()->getRequestTime();
$secret = str_repeat('synthetic-industry-operator-key-', 3);
putenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET=' . $secret);
$fixtures = [];
$attemptedOperatorFiles = [];

try {
  foreach ($approval['templates'] as $template) {
    $slug = (string) ($template['industry'] ?? '');
    $recipe = AcquisitionIndustryTemplate::nativeRecipe($slug);
    $category = AcquisitionWindowContact::INDUSTRIES[$slug] ?? NULL;
    if ($category === NULL) throw new RuntimeException('Industry segment map mismatch.');
    $runId = bin2hex(random_bytes(5));
    $email = 'operator-' . $slug . '-' . $runId . '@example.test';
    $prospect = \Drupal::entityTypeManager()->getStorage('famtastic_prospect')->create([
      'business_name' => 'Synthetic Operator Fixture ' . $slug,
      'business_category' => $category,
      'public_email' => $email,
      'campaign' => 'acquisition-199',
      'source' => 'local_synthetic',
      'status' => 'new',
    ]);
    $prospect->save();
    $prospectId = (int) $prospect->id();
    $account = \Drupal::service('famtastic_pipeline.acquisition_sample_sequences')->senderAccount();
    $binding = [
      'prospect_id' => $prospectId,
      'campaign_id' => $campaignId,
      'recipient_hash' => \Drupal::service('famtastic_pipeline.operational_ledger')->contactHash($email),
      'account_sha256' => $account['account_sha256'],
      'from' => $account['from'],
    ];
    $providerReceipt = [
      'status' => 'owner_reviewed',
      'reference' => 'synthetic-industry-operator-history-only',
      'sha256' => str_repeat('c', 64),
      'checked_at' => $now,
      'binding' => $binding,
      'provider' => 'godaddy_cpanel',
      'policy' => 'opt_in_only',
      'permitted_use' => TRUE,
      'written_opt_in_reference' => 'synthetic-only-not-real-permission',
      'written_opt_in_sha256' => str_repeat('d', 64),
    ];
    $historyReceipt = [
      'status' => 'owner_reviewed',
      'reference' => 'synthetic-industry-operator-history-only',
      'sha256' => str_repeat('c', 64),
      'checked_at' => $now,
      'binding' => $binding,
      'classification' => 'actual_native_history_reconciled',
      'coverage_complete' => TRUE,
      'eligible_for_new_outreach' => TRUE,
      'known_stop_reasons' => [],
    ];
    $inputName = 'industry-op-' . $slug . '-' . $runId . '.json';
    $inputPath = $operatorDir . '/' . $inputName;
    $attemptedOperatorFiles[] = $inputPath;
    $packet = [
      'schema' => 'famtastic.acquisition-operator-input.v1',
      'prospect_id' => $prospectId,
      'campaign_id' => $campaignId,
      'invitation_key' => 'synthetic:industry-operator:' . $slug . ':' . $runId,
      'invitation_expires' => $now + 86400,
      'issued_at' => $now,
      'expires' => $now + 1800,
      'schedule_start' => $now,
      'approval_ref' => 'synthetic-industry-operator-' . $slug . '-' . $runId,
      'recipe' => $recipe,
      'creative_approval' => $recipe['review']['approval_record'],
      'provider_permission_receipt' => $providerReceipt,
      'history_receipt' => $historyReceipt,
      'release_proof' => ['status' => 'owner_reviewed', 'reference' => 'synthetic-release-not-hosted', 'sha256' => str_repeat('e', 64)],
    ];
    $signature = hash_hmac('sha256', json_encode($packet, JSON_THROW_ON_ERROR), $secret);
    $oldMask = umask(0077);
    try {
      if (file_put_contents($inputPath, json_encode(['packet' => $packet, 'signature' => $signature], JSON_THROW_ON_ERROR), LOCK_EX) === FALSE || !chmod($inputPath, 0600)) throw new RuntimeException('Synthetic operator packet write failed.');
    }
    finally { umask($oldMask); }

    $first = AcquisitionExactOperator::run('prepare', $inputPath);
    $message = $db->select('famtastic_email_message', 'm')->fields('m')->condition('id', $first['message_id'])->execute()->fetchAssoc();
    $content = $db->select('famtastic_acquisition_message', 'a')->fields('a')->condition('message_id', $first['message_id'])->execute()->fetchAssoc();
    $invitation = $db->select('famtastic_acquisition_sample', 's')->fields('s')->condition('id', $first['invitation_id'])->execute()->fetchAssoc();
    $recipeSnapshot = json_decode((string) ($invitation['recipe_snapshot'] ?? ''), TRUE, 32, JSON_THROW_ON_ERROR);
    $bindings = json_decode((string) ($invitation['bindings'] ?? ''), TRUE, 32, JSON_THROW_ON_ERROR);
    $snapshot = json_decode((string) ($content['snapshot'] ?? ''), TRUE, 64, JSON_THROW_ON_ERROR);
    $frozenApproval = json_decode((string) ($snapshot['industry_approval_record'] ?? ''), TRUE, 64, JSON_THROW_ON_ERROR);
    $dispatchCount = (int) $db->select('famtastic_acquisition_dispatch', 'd')->condition('message_id', $first['message_id'])->countQuery()->execute()->fetchField();
    $expectedContentId = AcquisitionIndustryTemplate::contentId($slug);
    $check(
      $first['mode'] === 'prepare'
      && $first['message_status'] === 'held'
      && $first['sequence_status'] === 'active'
      && $message && $message['status'] === 'held'
      && ($recipeSnapshot[0]['industry_family'] ?? '') === $slug
      && ($snapshot['content_id'] ?? '') === $expectedContentId
      && ($content['content_id'] ?? '') === $expectedContentId
      && ($bindings['_preparation']['industry_template_slug'] ?? '') === $slug
      && ($bindings['_preparation']['industry'] ?? '') === $category
      && ($snapshot['authorization']['content_id'] ?? '') === $expectedContentId
      && ($frozenApproval['templates'][array_search($slug, array_column($frozenApproval['templates'] ?? [], 'industry'), TRUE)]['industry'] ?? '') === $slug
      && str_contains((string) ($snapshot['html'] ?? ''), 'sample_continuation=')
      && str_contains((string) ($snapshot['body'] ?? ''), '/unsubscribe/confirm/')
      && !str_contains((string) ($snapshot['html'] ?? '') . (string) ($snapshot['body'] ?? ''), 'example.invalid')
      && $dispatchCount === 0,
      $slug . '_real_operator_prepare_bound_held_content'
    );
    $beforeMessageCount = (int) $db->select('famtastic_email_message', 'm')->condition('prospect_id', $prospectId)->countQuery()->execute()->fetchField();
    $beforeSampleCount = (int) $db->select('famtastic_acquisition_sample', 's')->condition('prospect_id', $prospectId)->countQuery()->execute()->fetchField();
    $replay = AcquisitionExactOperator::run('prepare', $inputPath);
    $afterMessageCount = (int) $db->select('famtastic_email_message', 'm')->condition('prospect_id', $prospectId)->countQuery()->execute()->fetchField();
    $afterSampleCount = (int) $db->select('famtastic_acquisition_sample', 's')->condition('prospect_id', $prospectId)->countQuery()->execute()->fetchField();
    $check(
      $replay['message_id'] === $first['message_id']
      && $replay['invitation_id'] === $first['invitation_id']
      && $replay['sequence_id'] === $first['sequence_id']
      && hash_equals($replay['content_hash'], $first['content_hash'])
      && $afterMessageCount === $beforeMessageCount
      && $afterSampleCount === $beforeSampleCount
      && (int) $db->select('famtastic_acquisition_dispatch', 'd')->condition('message_id', $first['message_id'])->countQuery()->execute()->fetchField() === 0,
      $slug . '_operator_prepare_replay_no_duplicate_no_dispatch'
    );
    $report['industries'][$slug] = [
      'content_id' => $expectedContentId,
      'recipe_id' => $recipe['id'],
      'message_status' => $message['status'],
      'sequence_status' => $first['sequence_status'],
      'creative_approval_sha256' => $recipe['review']['approval_record']['sha256'],
      'content_hash' => $first['content_hash'],
      'manifest_hash' => $first['manifest_hash'],
      'replay_same_ids' => TRUE,
      'dispatch_rows' => 0,
    ];
    $fixtures[] = ['slug' => $slug, 'prospect_id' => $prospectId];
  }
  $report['status'] = 'passed';
  $report['industry_count'] = count($fixtures);
  $report['bundle_manifest_sha256'] = hash_file('sha256', $manifest);
}
finally {
  foreach ($attemptedOperatorFiles as $inputPath) {
    $base = substr($inputPath, 0, -5);
    foreach ([$inputPath, $base . '.state.json', $base . '.manifest.json', $base . '.lock'] as $path) {
      if (dirname($path) === $operatorDir && (is_file($path) || is_link($path))) unlink($path);
    }
  }
}
