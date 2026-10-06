<?php

declare(strict_types=1);

/** Installed SQLite proof: synthetic recipients, no SMTP or network functions. */
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Controller\AcquisitionSampleController;
use Drupal\famtastic_pipeline\Controller\CustomerPortalController;
use Drupal\famtastic_pipeline\Service\AcquisitionIndustryTemplate;
use Symfony\Component\HttpFoundation\Request;

$sandbox = realpath((string) getenv('ACQUISITION_DRUPAL_SANDBOX')) ?: '';
$db = \Drupal::database();
$options = $db->getConnectionOptions();
if (!preg_match('#/famtastic-acquisition-drupal\.[a-zA-Z0-9]{6}$#D', $sandbox) || realpath(__DIR__) !== $sandbox . '/scripts' || realpath(\Drupal::root()) !== $sandbox . '/backend/web' || ($options['driver'] ?? '') !== 'sqlite' || realpath((string) $options['database']) !== $sandbox . '/backend/web/sites/default/files/.ht.sqlite' || getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') !== 'memory' || function_exists('curl_exec') || function_exists('mail') || ini_get('allow_url_fopen') !== '0') throw new RuntimeException('Industry proof isolation required.');
$bundle = $sandbox . '/backend/private/industry-d0';
new Settings(array_replace(Settings::getAll(), ['famtastic_acquisition_industry_bundle_root' => $bundle, 'famtastic_acquisition_industry_bundle_sha256' => hash_file('sha256', $bundle . '/manifest.json')]));
$GLOBALS['config']['smtp.settings'] = ['smtp_on' => TRUE, 'smtp_host' => 'smtp.synthetic.invalid', 'smtp_port' => 587, 'smtp_username' => 'sender@example.test', 'smtp_from' => 'sender@example.test', 'smtp_protocol' => 'tls'];
$GLOBALS['config']['famtastic_pipeline.settings'] = array_replace($GLOBALS['config']['famtastic_pipeline.settings'] ?? [], ['outreach_postal_address' => '123 Fictional Test Street, Example City, FL 00000']);
\Drupal::service('config.factory')->reset('smtp.settings')->reset('famtastic_pipeline.settings');
$samples = \Drupal::service('famtastic_pipeline.acquisition_samples');
$sequences = \Drupal::service('famtastic_pipeline.acquisition_sample_sequences');
$controller = AcquisitionSampleController::create(\Drupal::getContainer());
$campaignId = (int) $db->select('famtastic_campaign', 'c')->fields('c', ['id'])->condition('campaign_key', 'acquisition-199')->execute()->fetchField();
$now = \Drupal::time()->getRequestTime();
$secret = str_repeat('synthetic-industry-owner-key-', 3);
putenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET=' . $secret);
$mailer = new class extends \Drupal\famtastic_pipeline\Service\OutreachMailer {
  public int $calls = 0;
  public function __construct() {}
  public function fromAddress(): string {return 'sender@example.test';}
  public function assertAcquisitionTransportAllowed(): void {}
  public function sendFrozenAcquisition(string $to, array $snapshot, string $unsubscribeUrl): string {
    if (!str_ends_with($to, '@example.test')) throw new RuntimeException('Synthetic recipient only.');
    $this->calls++;
    return '<synthetic-industry-not-real@example.test>';
  }
};
$exact = new \Drupal\famtastic_pipeline\Service\AcquisitionSampleExactAdapter($db, \Drupal::time(), \Drupal::service('famtastic_pipeline.operational_ledger'), $sequences, $mailer);
$report = ['schema' => 'famtastic.acquisition-industry-installed-proof.v1', 'classification' => 'local_synthetic', 'status' => 'running', 'checks' => [], 'customer_sends' => FALSE, 'production_deployment' => FALSE];
register_shutdown_function(static function () use (&$report): void { file_put_contents((string) getenv('ACQUISITION_DRUPAL_EVIDENCE') . '/evidence.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n"); });
$check = static function (bool $result, string $name) use (&$report): void { $report['checks'][$name] = $result; if (!$result) { $report['status'] = 'failed'; throw new RuntimeException('FAIL: ' . $name); } print 'PASS: ' . $name . "\n"; };
$approval = json_decode((string) file_get_contents($bundle . '/' . AcquisitionIndustryTemplate::APPROVAL_REFERENCE), TRUE, 64, JSON_THROW_ON_ERROR);
$source = $sandbox . '/marketing';
$hold = $sandbox . '/marketing-industry-proof-held';
if (is_dir($hold)) throw new RuntimeException('Existing proof hold refused.');
rename($source, $hold);
$fixtures = [];
require_once __DIR__ . '/acquisition-window-contact.php';
try {
  foreach ($approval['templates'] as $template) {
    $slug = $template['industry'];
    $recipe = AcquisitionIndustryTemplate::nativeRecipe($slug);
    $entry = AcquisitionIndustryTemplate::bySlug($slug);
    $email = $slug . '-synthetic@example.test';
    $prospect = \Drupal::entityTypeManager()->getStorage('famtastic_prospect')->create(['business_name' => 'Synthetic ' . $slug, 'business_category' => AcquisitionWindowContact::INDUSTRIES[$slug], 'public_email' => $email, 'campaign' => 'acquisition-199', 'source' => 'local_synthetic', 'status' => 'new']);
    $prospect->save();
    $other = $approval['templates'][($slug === $approval['templates'][0]['industry']) ? 1 : 0]['industry'];
    try {
      $samples->prepareGeneric('synthetic:industry:wrong:' . $slug, (int) $prospect->id(), $campaignId, AcquisitionIndustryTemplate::nativeRecipe($other), $now + 86400);
      $check(FALSE, $slug . '_cross_industry_recipe_rejected');
    } catch (InvalidArgumentException) {
      $check((int) $db->select('famtastic_acquisition_sample', 's')->condition('prospect_id', (int) $prospect->id())->countQuery()->execute()->fetchField() === 0, $slug . '_cross_industry_recipe_rejected');
    }
    $invite = $samples->prepareGeneric('synthetic:industry:' . $slug, (int) $prospect->id(), $campaignId, $recipe, $now + 86400);
    $resolved = $samples->resolve($invite['token']);
    $check(!is_dir($source) && ($resolved['recipes'][0]['interactive'] ?? FALSE) === TRUE && $resolved['recipes'][0]['id'] === $recipe['id'], $slug . '_private_bundle_and_interactive_recipe');
    $response = $controller->preview($invite['token'], $recipe['id']);
    $html = $response->getContent();
    $csp = $response->headers->get('Content-Security-Policy');
    $check($response->getStatusCode() === 200 && str_contains($html, 'data-famtastic-industry-lab="' . $slug . '"') && str_contains($html, '<script') && !str_contains($html, 'src="lab.js"') && !str_contains($html, 'href="lab.css"'), $slug . '_frozen_matching_lab');
    $check(str_contains($csp, 'sandbox allow-scripts') && !str_contains($csp, 'allow-same-origin') && str_contains($csp, "connect-src 'none'") && $response->headers->get('Cache-Control') === 'no-store, private', $slug . '_isolated_private_preview');
    $row = $db->select('famtastic_acquisition_sample', 's')->fields('s')->condition('id', $invite['id'])->execute()->fetchAssoc();
    $account = $sequences->senderAccount();
    $binding = ['invitation_id' => (int) $row['id'], 'prospect_id' => (int) $row['prospect_id'], 'campaign_id' => (int) $row['campaign_id'], 'recipient_hash' => $row['recipient_hash'], 'invitation_evidence_hash' => $row['evidence_hash'], 'account_sha256' => $account['account_sha256'], 'from' => $account['from']];
    $receipt = ['status' => 'owner_reviewed', 'reference' => 'synthetic-industry-test-only', 'sha256' => str_repeat('c', 64), 'checked_at' => $now, 'binding' => $binding];
    $authorization = ['schema' => 'famtastic.acquisition-generic-authorization.v1', 'issued_at' => $now, 'expires' => $now + 3600, 'binding' => $binding, 'sender' => $account, 'provider_permission_receipt' => $receipt + ['provider' => 'godaddy_cpanel', 'policy' => 'opt_in_only', 'permitted_use' => TRUE, 'written_opt_in_reference' => 'synthetic-only', 'written_opt_in_sha256' => str_repeat('d', 64)], 'history_receipt' => $receipt + ['classification' => 'actual_native_history_reconciled', 'coverage_complete' => TRUE, 'eligible_for_new_outreach' => TRUE, 'known_stop_reasons' => []], 'creative_approval' => $recipe['review']['approval_record'], 'content_id' => AcquisitionIndustryTemplate::contentId($slug)];
    $samples->authorizeGeneric($invite['id'], $authorization, hash_hmac('sha256', json_encode($authorization, JSON_THROW_ON_ERROR), $secret));
    $stage = $sequences->stageGenericD0($invite['id'], $email, $invite['token']);
    $content = $db->select('famtastic_acquisition_message', 'm')->fields('m')->condition('message_id', $stage['message_ids'][0])->execute()->fetchAssoc();
    $snapshot = json_decode($content['snapshot'], TRUE, 64, JSON_THROW_ON_ERROR);
    $check($stage['status'] === 'held' && !$stage['real_dispatch_enabled'] && $snapshot['content_id'] === AcquisitionIndustryTemplate::contentId($slug) && str_contains($snapshot['html'], '/web/api/pipeline/email/click/') && str_contains($snapshot['html'], 'sample_continuation=' . $invite['token']) && str_contains($snapshot['body'], '/unsubscribe/confirm/') && !str_contains($snapshot['html'], 'example.invalid'), $slug . '_matching_held_native_email_bindings');
    $check($sequences->stageGenericD0($invite['id'], $email, $invite['token'])['duplicate'] === TRUE, $slug . '_staging_replay');
    $sequences->activate($stage['sequence_id'], $now, 'synthetic-industry-schedule');
    $authorizedRow = $db->select('famtastic_acquisition_sample', 's')->fields('s')->condition('id', $invite['id'])->execute()->fetchAssoc();
    $manifest = ['schema' => 'famtastic.acquisition-exact-send.v1', 'transport' => 'native_smtp', 'cap' => 1, 'expires' => $now + 3600, 'approval_ref' => 'synthetic-industry-' . $slug, 'message_id' => (int) $content['message_id'], 'sequence_id' => (int) $stage['sequence_id'], 'recipient' => $email, 'from' => $account['from'], 'content_id' => $content['content_id'], 'content_hash' => $content['content_hash'], 'qualification_ref' => $authorizedRow['qualification_ref'], 'invitation_evidence_hash' => $authorizedRow['evidence_hash'], 'sender_account_sha256' => $account['account_sha256'], 'generic_authorization_hash' => $authorizedRow['qualification_ref'], 'provider_permission_receipt' => $authorization['provider_permission_receipt'], 'history_receipt' => $authorization['history_receipt'], 'release_proof' => ['status' => 'owner_reviewed', 'reference' => 'synthetic-release-not-hosted', 'sha256' => str_repeat('e', 64)]];
    $signature = hash_hmac('sha256', json_encode($manifest, JSON_THROW_ON_ERROR), $secret);
    $beforeCalls = $mailer->calls;
    $captured = $exact->dispatch($manifest, $signature);
    $check(!$captured['duplicate'] && $mailer->calls === $beforeCalls + 1 && $exact->dispatch($manifest, $signature)['duplicate'] && $mailer->calls === $beforeCalls + 1, $slug . '_exact_adapter_fake_transport_once');
    $samples->preference($invite['token'], $recipe['id']);
    $auth = CustomerPortalController::create(\Drupal::getContainer());
    $portal = \Drupal::service('famtastic_pipeline.customer_portal');
    $registration = $auth->register(Request::create('http://acquisition-drupal.example.test/api/customer/register', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => 'Synthetic Industry Fixture', 'email' => $email, 'password' => 'synthetic-password-only-199', 'sample_continuation' => $invite['token']])));
    $customer = $portal->customerForEmail($email);
    $check(in_array($registration->getStatusCode(), [201, 202], TRUE) && $customer && !$samples->continuation((int) $customer['id']), $slug . '_pending_registration');
    $mail = array_map(static fn(string $line): array => json_decode($line, TRUE), file($sandbox . '/mail.jsonl', FILE_IGNORE_NEW_LINES));
    $verification = array_values(array_filter($mail, static fn(array $m): bool => $m['to'] === $email && str_contains($m['subject'], 'Verify')));
    preg_match('/token=([A-Za-z0-9_-]+)/', end($verification)['body'], $match);
    $verified = $auth->verify(Request::create('http://acquisition-drupal.example.test/api/customer/verify', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['token' => $match[1]])));
    $continuation = json_decode($verified->getContent(), TRUE)['continuation'];
    $check($verified->getStatusCode() === 200 && $continuation['recipe_id'] === $recipe['id'] && $continuation['known_information']['business_name'] === 'Synthetic ' . $slug && $continuation['known_information']['business_category'] === AcquisitionWindowContact::INDUSTRIES[$slug], $slug . '_verified_matched_continuation');
    $fixtures[] = ['slug' => $slug, 'recipe_id' => $recipe['id'], 'token' => $invite['token'], 'preview_path' => $resolved['recipes'][0]['preview_path']];
  }
  $bundleHold = $bundle . '.proof-held';
  if (is_dir($bundleHold)) throw new RuntimeException('Existing package hold refused.');
  rename($bundle, $bundleHold);
  try {
    foreach ($fixtures as $fixture) {
      $frozen = $samples->preview($fixture['token'], $fixture['recipe_id']);
      $check(str_contains((string) $frozen, 'data-famtastic-industry-lab="' . $fixture['slug'] . '"'), $fixture['slug'] . '_native_snapshot_without_source_or_bundle');
    }
  } finally {rename($bundleHold, $bundle);}
  $manifestPath = $bundle . '/manifest.json';
  $manifestBytes = (string) file_get_contents($manifestPath);
  file_put_contents($manifestPath, $manifestBytes . "\n");
  try {
    try {
      AcquisitionIndustryTemplate::nativeRecipe($fixtures[0]['slug']);
      $check(FALSE, 'private_manifest_drift_rejected');
    } catch (RuntimeException $error) {
      $check($error->getMessage() === 'industry_bundle_manifest_changed', 'private_manifest_drift_rejected');
    }
  } finally {file_put_contents($manifestPath, $manifestBytes);}
  $report['status'] = 'passed';
  $report['fake_dispatches'] = $mailer->calls;
  $report['bundle_manifest_sha256'] = hash_file('sha256', $bundle . '/manifest.json');
  file_put_contents($sandbox . '/industry-fixture.json', json_encode($fixtures, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
} finally {
  rename($hold, $source);
}
