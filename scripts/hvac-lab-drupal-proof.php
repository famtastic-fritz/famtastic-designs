<?php

declare(strict_types=1);

/** Installed native HVAC journey proof. Disposable SQLite and memory mail only. */
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Controller\AcquisitionSampleController;
use Drupal\famtastic_pipeline\Controller\CustomerPortalController;
use Drupal\famtastic_pipeline\Service\AcquisitionHvacTemplate;
use Symfony\Component\HttpFoundation\Request;

$sandbox = realpath((string) getenv('HVAC_DRUPAL_SANDBOX')) ?: '';
$db = \Drupal::database();
$options = $db->getConnectionOptions();
if (!preg_match('#/famtastic-hvac-drupal\.[a-zA-Z0-9]{6}$#D', $sandbox) || realpath(__DIR__) !== $sandbox . '/scripts' || realpath(\Drupal::root()) !== $sandbox . '/backend/web' || ($options['driver'] ?? '') !== 'sqlite' || realpath((string) $options['database']) !== $sandbox . '/backend/web/sites/default/files/.ht.sqlite' || getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') !== 'memory' || function_exists('curl_exec') || function_exists('mail') || ini_get('allow_url_fopen') !== '0') throw new RuntimeException('Disposable HVAC proof isolation required.');
$output = realpath((string) getenv('HVAC_DRUPAL_EVIDENCE')) ?: '';
if (!str_starts_with($output . '/', $sandbox . '/')) throw new RuntimeException('Private local evidence directory required.');
$bundle = $sandbox . '/backend/private/hvac';
new Settings(array_replace(Settings::getAll(), ['famtastic_acquisition_hvac_bundle_root' => $bundle, 'famtastic_acquisition_hvac_bundle_sha256' => hash_file('sha256', $bundle . '/manifest.json')]));
$report = ['schema' => 'famtastic.hvac-installed-proof.v1', 'status' => 'running', 'classification' => 'local_synthetic', 'checks' => [], 'provider_sends' => FALSE, 'production_deployment' => FALSE, 'native_recipe' => AcquisitionHvacTemplate::ID];
register_shutdown_function(static function () use (&$report, $output): void { file_put_contents($output . '/evidence.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n"); });
$check = static function (bool $result, string $name) use (&$report): void { $report['checks'][$name] = $result; if (!$result) { $report['status'] = 'failed'; throw new RuntimeException('FAIL: ' . $name); } print 'PASS: ' . $name . "\n"; };
$samples = \Drupal::service('famtastic_pipeline.acquisition_samples');
$portal = \Drupal::service('famtastic_pipeline.customer_portal');
$controller = AcquisitionSampleController::create(\Drupal::getContainer());
$auth = CustomerPortalController::create(\Drupal::getContainer());
$now = \Drupal::time()->getRequestTime();
$campaign = 'hvac-synthetic-' . bin2hex(random_bytes(4));
$campaignId = (int) $db->insert('famtastic_campaign')->fields(['campaign_key' => $campaign, 'name' => 'Fictional HVAC proof', 'status' => 'draft', 'created' => $now, 'changed' => $now])->execute();
$post = static fn(string $route, array $input): Request => Request::create('http://acquisition-drupal.example.test/' . $route, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($input, JSON_THROW_ON_ERROR));
$counts = static function () use ($db): array {
  $result = [];
  foreach (['famtastic_email_message', 'famtastic_acquisition_sequence', 'famtastic_acquisition_dispatch', 'famtastic_event'] as $table) $result[$table] = (int) $db->select($table, 't')->countQuery()->execute()->fetchField();
  return $result;
};
$beforeDispatch = $counts();
$fixtures = [];
foreach (['Juniper & "Friends" HVAC', 'Cypress Fixture HVAC'] as $index => $name) {
  $email = 'hvac-' . $index . '-' . $campaign . '@example.test';
  $prospect = \Drupal::entityTypeManager()->getStorage('famtastic_prospect')->create(['business_name' => $name, 'business_category' => $index === 0 ? 'HVAC' : 'Air conditioning', 'public_email' => $email, 'campaign' => $campaign, 'source' => 'local_synthetic', 'status' => 'new']);
  $prospect->save();
  $recipe = AcquisitionHvacTemplate::nativeRecipe();
  $invitation = $samples->prepareGeneric('synthetic:hvac:' . $campaign . ':' . $index, (int) $prospect->id(), $campaignId, $recipe, $now + 3600);
  $scannerBefore = $counts();
  $rowBefore = $db->select('famtastic_acquisition_sample', 's')->fields('s')->condition('id', $invitation['id'])->execute()->fetchAssoc();
  for ($read = 0; $read < 3; $read++) {
    $resolved = $controller->resolve($invitation['token']);
    $preview = $controller->preview($invitation['token'], $recipe['id']);
  }
  $rowAfter = $db->select('famtastic_acquisition_sample', 's')->fields('s')->condition('id', $invitation['id'])->execute()->fetchAssoc();
  $check($scannerBefore === $counts() && $rowBefore === $rowAfter, 'prospect_' . $index . '_scanner_gets_do_not_mutate');
  $check($resolved->getStatusCode() === 200 && $preview->getStatusCode() === 200 && str_contains($preview->getContent(), htmlspecialchars($name, ENT_QUOTES)) && str_contains($preview->getContent(), 'sample_continuation=' . $invitation['token']) && $preview->headers->get('Cache-Control') === 'no-store, private', 'prospect_' . $index . '_frozen_escaped_identity_and_private_route');
  $check(str_contains($preview->headers->get('Content-Security-Policy'), 'allow-top-navigation-by-user-activation') && !str_contains($preview->headers->get('Content-Security-Policy'), 'allow-same-origin'), 'prospect_' . $index . '_sandbox_isolated_user_activation_only');
  if ($index === 0) $samples->preference($invitation['token'], $recipe['id']);
  $registration = $auth->register($post('api/customer/register', ['name' => 'Fictional HVAC owner', 'email' => $email, 'password' => 'synthetic-password-only-199', 'sample_continuation' => $invitation['token']]));
  $customer = $portal->customerForEmail($email);
  $check(in_array($registration->getStatusCode(), [201, 202], TRUE) && $customer && !$samples->continuation((int) $customer['id']), 'prospect_' . $index . '_registration_waits_for_verified_email');
  $mail = array_map(static fn(string $line): array => json_decode($line, TRUE), file($sandbox . '/mail.jsonl', FILE_IGNORE_NEW_LINES));
  $verification = array_values(array_filter($mail, static fn(array $m): bool => $m['to'] === $email && str_contains($m['subject'], 'Verify')));
  preg_match('/token=([A-Za-z0-9_-]+)/', end($verification)['body'], $match);
  $verified = $auth->verify($post('api/customer/verify', ['token' => $match[1]]));
  $continuation = json_decode($verified->getContent(), TRUE)['continuation'];
  $check($verified->getStatusCode() === 200 && $continuation['known_information']['business_name'] === $name && ($continuation['recipe_id'] ?? NULL) === ($index === 0 ? $recipe['id'] : NULL), 'prospect_' . $index . '_verified_identity_and_preference_isolated');
  $organization = $portal->organizations((int) $customer['id'])[0];
  $created = $portal->createWebsiteRequest((int) $customer['id'], $organization['public_id'], ['project_name' => 'Fictional HVAC website', 'project_type' => 'new_website', 'action' => 'submit', 'primary_goal' => 'Describe HVAC inquiry workflow', 'products_services' => 'Illustrative HVAC repair, maintenance and assessment', 'acquisition_context_id' => $continuation['context_id']]);
  $request = $db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $created['public_id'])->execute()->fetchAssoc();
  $intake = json_decode($request['intake_data'], TRUE);
  $check($request['business_name'] === $name && $samples->associatedRequest((int) $customer['id'], $continuation['context_id']) === (int) $request['id'] && ($intake['acquisition_context']['formal_proof_selection'] ?? NULL) === FALSE, 'prospect_' . $index . '_native_website_inquiry_persisted');
  file_put_contents($output . '/prospect-' . $index . '.html', $preview->getContent());
  $fixtures[] = ['invitation' => $invitation, 'customer_id' => (int) $customer['id'], 'context_id' => $continuation['context_id'], 'request_public_id' => $request['public_id']];
}
$check($fixtures[0]['context_id'] !== $fixtures[1]['context_id'] && $fixtures[0]['request_public_id'] !== $fixtures[1]['request_public_id'], 'two_prospects_keep_context_and_request_separate');
try { $samples->requestContext($fixtures[1]['customer_id'], $fixtures[0]['context_id']); $check(FALSE, 'cross_recipient_request_context_rejected'); }
catch (InvalidArgumentException) { $check(TRUE, 'cross_recipient_request_context_rejected'); }
$db->update('famtastic_acquisition_sample')->fields(['expires' => $now - 1])->condition('id', $fixtures[0]['invitation']['id'])->execute();
$check($controller->resolve($fixtures[0]['invitation']['token'])->getStatusCode() === 404 && $controller->preview($fixtures[0]['invitation']['token'], AcquisitionHvacTemplate::ID)->getStatusCode() === 404 && $samples->continuation($fixtures[0]['customer_id'])['request_public_id'] === $fixtures[0]['request_public_id'], 'expired_public_room_keeps_verified_account_request_resume');
$held = $bundle . '-held';
rename($bundle, $held);
try { $check($controller->preview($fixtures[1]['invitation']['token'], AcquisitionHvacTemplate::ID)->getStatusCode() === 200, 'native_snapshot_survives_source_bundle_removal'); }
finally { rename($held, $bundle); }
$afterDispatch = $counts();
$check($afterDispatch['famtastic_email_message'] === $beforeDispatch['famtastic_email_message'] && $afterDispatch['famtastic_acquisition_sequence'] === $beforeDispatch['famtastic_acquisition_sequence'] && $afterDispatch['famtastic_acquisition_dispatch'] === $beforeDispatch['famtastic_acquisition_dispatch'], 'no_campaign_mail_queue_sequence_or_dispatch_created');
$report['status'] = 'passed';
$report['manifest_sha256'] = hash_file('sha256', $bundle . '/manifest.json');
$report['source_design_commit'] = AcquisitionHvacTemplate::bySlug()['source_design_commit'];
