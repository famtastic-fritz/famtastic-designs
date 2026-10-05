<?php

declare(strict_types=1);

/** Real installed Drupal, synthetic-only test in a separately copied runtime. */
use Drupal\famtastic_pipeline\Controller\CustomerPortalController;
use Drupal\famtastic_pipeline\Controller\AcquisitionSampleController;
use Drupal\famtastic_pipeline\Service\AcquisitionSampleGuard;
use Symfony\Component\HttpFoundation\Request;

$sandbox = realpath((string) getenv('ACQUISITION_DRUPAL_SANDBOX')) ?: '';
if (!preg_match('#/famtastic-acquisition-drupal\.[a-zA-Z0-9]{6}$#D', $sandbox) || realpath(__DIR__) !== $sandbox . '/scripts') throw new RuntimeException('Use acquisition-sample-drupal.sh; shared Drupal installation refused.');
if (getenv('ACQUISITION_DRUPAL_PHASE') === 'configure') {
  $settings = $sandbox . '/backend/web/sites/default/settings.php';
  if (file_exists($settings)) throw new RuntimeException('Refusing existing settings.');
  copy(dirname($settings) . '/default.settings.php', $settings);
  file_put_contents($settings, "\n\$settings['hash_salt'] = 'synthetic-acquisition-only';\n\$settings['file_private_path'] = " . var_export($sandbox . '/backend/private', TRUE) . ";\n\$settings['trusted_host_patterns'] = ['^acquisition-drupal\\.example\\.test$', '^127\\.0\\.0\\.1$', '^localhost$'];\n\$config['system.mail']['interface']['default'] = 'test_mail_collector';\n\$config['automated_cron.settings']['interval'] = 0;\n\$config['famtastic_pipeline.settings']['frontend_base_url'] = 'https://famtasticdesigns.com';\n\$config['famtastic_pipeline.settings']['notification_to_email'] = 'operator@example.test';\n\$settings['container_yamls'][] = __DIR__ . '/acquisition-test.services.yml';\n", FILE_APPEND);
  file_put_contents(dirname($settings) . '/acquisition-test.services.yml', "services:\n  acquisition_http_blocker:\n    class: GuzzleHttp\\Handler\\MockHandler\n  http_handler_stack:\n    class: GuzzleHttp\\HandlerStack\n    factory: GuzzleHttp\\HandlerStack::create\n    arguments: ['@acquisition_http_blocker']\n");
  exit;
}
$db = \Drupal::database();
$options = $db->getConnectionOptions();
if (realpath(\Drupal::root()) !== $sandbox . '/backend/web' || ($options['driver'] ?? '') !== 'sqlite' || realpath((string) $options['database']) !== $sandbox . '/backend/web/sites/default/files/.ht.sqlite' || getenv('FAMTASTIC_TRANSACTIONAL_EMAIL_TRANSPORT') !== 'memory' || function_exists('curl_exec') || function_exists('mail') || ini_get('allow_url_fopen') !== '0') throw new RuntimeException('Safety boundary failed.');
$report = ['schema' => 'famtastic.acquisition-installed-proof.v1', 'status' => 'running', 'classification' => 'local_synthetic', 'checks' => [], 'real_payments' => FALSE, 'customer_sends' => FALSE, 'production_deployment' => FALSE];
register_shutdown_function(static function () use (&$report): void { file_put_contents((string) getenv('ACQUISITION_DRUPAL_EVIDENCE') . '/evidence.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n"); });
$check = static function (bool $value, string $name) use (&$report): void { $report['checks'][$name] = $value; if (!$value) { $report['status'] = 'failed'; throw new RuntimeException('FAIL: ' . $name); } print 'PASS: ' . $name . "\n"; };
$portal = \Drupal::service('famtastic_pipeline.customer_portal');
$samples = \Drupal::service('famtastic_pipeline.acquisition_samples');
$sequences = \Drupal::service('famtastic_pipeline.acquisition_sample_sequences');
$check(\Drupal::hasService('famtastic_pipeline.acquisition_report'), 'installed_container_services');
$route = \Drupal::service('router.route_provider')->getRouteByName('famtastic_pipeline.acquisition_sample_claim');
$check($route->getRequirement('_csrf_request_header_token') === 'TRUE', 'installed_claim_route_csrf');
if (getenv('ACQUISITION_DRUPAL_PHASE') === 'generic') {
  $settings = \Drupal\Core\Site\Settings::getAll();
  new \Drupal\Core\Site\Settings($settings + ['famtastic_acquisition_internal_preparation' => TRUE]);
  $now = \Drupal::time()->getRequestTime();
  $runKey = bin2hex(random_bytes(4));
  $email = 'generic-' . $runKey . '@example.test';
  $prospect = \Drupal::entityTypeManager()->getStorage('famtastic_prospect')->create(['business_name' => 'Juniper Hair Studio', 'business_category' => 'Beauty, Hair Styling & Braiding', 'public_email' => $email, 'campaign' => 'acquisition-199', 'source' => 'local_synthetic', 'status' => 'new']);
  $prospect->save();
  $campaignId = (int) $db->select('famtastic_campaign', 'c')->fields('c', ['id'])->condition('campaign_key', 'acquisition-199')->execute()->fetchField();
  $artifact = 'marketing/campaigns/acquisition-199/generic-review/beauty-template.html';
  $recipe = ['id' => 'beauty_soft_power_acquisition', 'version' => 1, 'niche' => 'beauty_hair', 'title' => 'Soft Power', 'summary' => 'An illustrative beauty direction', 'artifact_path' => $artifact, 'sha256' => hash_file('sha256', $sandbox . '/' . $artifact), 'review' => ['status' => 'candidate'], 'recipe_ref' => ['owner' => 'component-studio', 'id' => 'beauty_soft_power_acquisition', 'version' => 1, 'status' => 'import_request_pending']];
  $invitation = $samples->prepareGeneric('synthetic:generic:' . $runKey, (int) $prospect->id(), $campaignId, $recipe, $now + 86400 * 10, TRUE);
  $controller = AcquisitionSampleController::create(\Drupal::getContainer());
  $response = $controller->resolve($invitation['token']);
  $sample = json_decode($response->getContent(), TRUE)['sample'];
  $check($sample['context_classification'] === 'supplied_generic_preparation' && $sample['business_name'] === 'Juniper Hair Studio' && $sample['industry'] === 'Beauty, Hair Styling & Braiding' && !$sample['context_provenance']['niche_verified'], 'installed_generic_stored_supplied_context');
  $check($sample['bindings']['recipient_name'] === '' && $sample['bindings']['phone'] === '' && !str_contains($response->getContent(), $email), 'installed_generic_unknown_owner_phone_and_no_url_pii');
  $check($response->headers->get('Cache-Control') === 'no-store, private', 'installed_generic_private_response');
  $preview = $controller->preview($invitation['token'], $recipe['id']);
  $check($preview->getStatusCode() === 200 && str_contains($preview->getContent(), 'Juniper Hair Studio') && !str_contains($preview->getContent(), '{{business_name}}') && str_contains($preview->getContent(), 'HAIR CARE · STYLE · CONFIDENCE') && !preg_match('/\b(?:caf[eé]|coffee|espresso|roastery)\b/iu', strip_tags($preview->getContent())), 'installed_generic_frozen_polished_preview');
  $samples->preference($invitation['token'], $recipe['id']);
  $samples->preference($invitation['token'], $recipe['id']);
  $row = $db->select('famtastic_acquisition_sample', 's')->fields('s')->condition('id', $invitation['id'])->execute()->fetchAssoc();
  $check($row['eligible_at'] === NULL && $row['qualification_ref'] === '' && !str_contains(json_encode($row), $invitation['token']), 'installed_preparation_not_outreach_eligible_and_token_hashed');
  try { $sequences->stage($invitation['id'], $email, $invitation['token'], [0 => [], 3 => [], 7 => []]); $check(FALSE, 'installed_generic_stage_rejected'); }
  catch (InvalidArgumentException $error) { $check($error->getMessage() === 'sample_outreach_qualification_required', 'installed_generic_stage_rejected'); }
  $check(!$db->select('famtastic_acquisition_sequence', 's')->condition('invitation_id', $invitation['id'])->countQuery()->execute()->fetchField(), 'installed_generic_no_sequence');
  $auth = CustomerPortalController::create(\Drupal::getContainer());
  $request = Request::create('http://acquisition-drupal.example.test/api/customer/register', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => 'Juniper Fixture', 'email' => $email, 'password' => 'synthetic-password-only-199', 'sample_continuation' => $invitation['token']]));
  $registered = $auth->register($request);
  $customer = $portal->customerForEmail($email);
  $check(in_array($registered->getStatusCode(), [201, 202], TRUE) && $customer && !$samples->continuation((int) $customer['id']), 'installed_generic_registration_pending_not_claimed');
  $mail = array_map(static fn(string $line): array => json_decode($line, TRUE), file($sandbox . '/mail.jsonl', FILE_IGNORE_NEW_LINES));
  $verification = array_values(array_filter($mail, static fn(array $m): bool => $m['to'] === $email && str_contains($m['subject'], 'Verify')));
  preg_match('/token=([A-Za-z0-9_-]+)/', end($verification)['body'], $match);
  $verified = $auth->verify(Request::create('http://acquisition-drupal.example.test/api/customer/verify', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['token' => $match[1]])));
  $continuation = json_decode($verified->getContent(), TRUE)['continuation'];
  $check($verified->getStatusCode() === 200 && $continuation['known_information']['business_name'] === 'Juniper Hair Studio' && $continuation['known_information']['business_category'] === 'Beauty, Hair Styling & Braiding', 'installed_generic_verified_known_information_resume');
  $output = (string) getenv('ACQUISITION_GENERIC_EXPORT');
  if (!$output || !is_dir($output)) throw new RuntimeException('Private generic export directory required.');
  file_put_contents($output . '/continuation-before-request.json', json_encode($continuation, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
  $organization = $portal->organizations((int) $customer['id'])[0];
  $known = $continuation['known_information'];
  $created = $portal->createWebsiteRequest((int) $customer['id'], $organization['public_id'], ['project_name' => $known['business_name'] . ' website', 'project_type' => 'new_website', 'action' => 'submit', 'primary_goal' => 'Test inquiry path', 'products_services' => 'Synthetic styling services', 'acquisition_context_id' => $continuation['context_id']]);
  $requestRow = $db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $created['public_id'])->execute()->fetchAssoc();
  $intake = json_decode($requestRow['intake_data'], TRUE);
  $check((int) $requestRow['prospect_id'] !== (int) $prospect->id() && $intake['acquisition_context']['known_information'] === $known && !$intake['acquisition_context']['formal_proof_selection'], 'installed_generic_native_interview_saved_supplied_context');
  $check($samples->associatedRequest((int) $customer['id'], $continuation['context_id']) === (int) $requestRow['id'], 'installed_generic_exact_request_mapping');
  $check($requestRow['business_name'] === $known['business_name'] && $intake['industry'] === $known['industry'], 'installed_generic_absent_business_industry_server_prefill');
  file_put_contents($output . '/request.json', json_encode($created, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
  $finalContinuation = $samples->continuation((int) $customer['id']);
  $override = $samples->prepareGeneric('synthetic:generic:override:' . $runKey, (int) $prospect->id(), $campaignId, $recipe, $now + 86400 * 10, TRUE);
  $overrideContext = $samples->claim((int) $customer['id'], $override['token']);
  $overrideInput = ['project_name' => 'Explicit edit fixture', 'business_name' => 'Edited Juniper Name', 'industry' => 'Edited Industry', 'action' => 'save', 'acquisition_context_id' => $overrideContext['context_id']];
  $overrideRaw = json_encode($overrideInput, JSON_THROW_ON_ERROR);
  $edited = $portal->createWebsiteRequest((int) $customer['id'], $organization['public_id'], $overrideInput, $overrideRaw);
  $check($edited['business_name'] === 'Edited Juniper Name' && $edited['intake']['industry'] === 'Edited Industry', 'installed_generic_explicit_customer_edits_preserved');
  $check($edited['intake']['request_submission']['raw_json'] === $overrideRaw, 'installed_generic_authored_raw_input_preserved');
  $output = (string) getenv('ACQUISITION_GENERIC_EXPORT');
  if (!$output || !is_dir($output)) throw new RuntimeException('Private generic export directory required.');
  file_put_contents($output . '/sample.json', $controller->resolve($invitation['token'])->getContent());
  file_put_contents($output . '/preview.html', $preview->getContent());
  file_put_contents($output . '/continuation.json', json_encode($finalContinuation, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
  file_put_contents($sandbox . '/generic-fixture.json', json_encode(['token' => $invitation['token'], 'email' => $email, 'password' => 'synthetic-password-only-199', 'verification_token_consumed' => TRUE, 'sandbox' => $sandbox, 'recipe_id' => $recipe['id'], 'prospect_id' => (int) $prospect->id(), 'customer_id' => (int) $customer['id']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
  $report['recipe_sha256'] = $recipe['sha256'];
  $report['status'] = 'passed';
  return;
}
if (getenv('ACQUISITION_DRUPAL_PHASE') === 'supplemental') {
  $mapping=$db->select('famtastic_acquisition_request','m')->fields('m')->execute()->fetchAssoc();
  $sequence=$db->select('famtastic_acquisition_sequence','s')->fields('s')->condition('invitation_id',$mapping['invitation_id'])->execute()->fetchAssoc();
  $db->update('famtastic_acquisition_sequence')->fields(['status'=>'active','stop_reason'=>NULL,'stopped_at'=>NULL])->condition('id',$sequence['id'])->execute();
  \Drupal::service('famtastic_pipeline.operational_ledger')->recordEvent('synthetic:installed:mapped:purchase:' . bin2hex(random_bytes(4)),'payment.fulfillment_started',['synthetic'=>TRUE,'processor_charge'=>FALSE],(int)$mapping['request_prospect_id']);
  $check($db->select('famtastic_acquisition_sequence','s')->fields('s',['stop_reason'])->condition('id',$sequence['id'])->execute()->fetchField()==='purchase','installed_new_request_prospect_purchase_stop');
  $ordinary=Request::create('http://acquisition-drupal.example.test/api/customer/register','POST',[],[],[],['CONTENT_TYPE'=>'application/json'],json_encode(['name'=>'Ordinary Fixture','email'=>'ordinary@example.test','password'=>'synthetic-password-only-199']));
  $auth=CustomerPortalController::create(\Drupal::getContainer());
  $check(in_array($auth->register($ordinary)->getStatusCode(),[201,202],TRUE),'ordinary_registration_without_sample_unchanged');
  $customer=$portal->customerForEmail('ordinary@example.test');
  $check($customer && !$samples->continuation((int)$customer['id']),'ordinary_account_has_no_acquisition_continuation');
  $mail=array_map(static fn(string $line):array=>json_decode($line,TRUE),file($sandbox.'/mail.jsonl',FILE_IGNORE_NEW_LINES));
  $verification=array_values(array_filter($mail,static fn(array $m):bool=>$m['to']==='ordinary@example.test' && str_contains($m['subject'],'Verify')));
  preg_match('/token=([A-Za-z0-9_-]+)/',end($verification)['body'],$match);
  $verified=$auth->verify(Request::create('http://acquisition-drupal.example.test/api/customer/verify','POST',[],[],[],['CONTENT_TYPE'=>'application/json'],json_encode(['token'=>$match[1]])));
  $payload=json_decode($verified->getContent(),TRUE);
  $check(($verified->getStatusCode()===200 || !empty($portal->customerForEmail('ordinary@example.test')['verified_at'])) && empty($payload['continuation']),'ordinary_verification_without_sample_unchanged');
  $check(\Drupal::hasService('famtastic_pipeline.acquisition_sample_exact_sender'),'installed_disabled_exact_sender_service');
  try { \Drupal::service('famtastic_pipeline.mailer')->assertAcquisitionTransportAllowed(); $check(FALSE,'installed_real_sender_held'); }
  catch (RuntimeException $error) {$check($error->getMessage()==='acquisition_real_dispatch_disabled','installed_real_sender_held');}
  \Drupal::moduleHandler()->loadInclude('famtastic_pipeline','install');
  // This retained synthetic fixture predates the exact-adapter unique approval key.
  if (!$db->schema()->indexExists('famtastic_acquisition_dispatch','approval')) $db->schema()->addUniqueKey('famtastic_acquisition_dispatch','approval',['approval_ref']);
  $updateSandbox=[];famtastic_pipeline_update_8067($updateSandbox);
  $check(TRUE,'installed_schema_update_idempotent');
  $db->schema()->dropUniqueKey('famtastic_acquisition_dispatch','approval');
  try {famtastic_pipeline_update_8067($updateSandbox);$check(FALSE,'partial_unique_approval_index_fails_closed');}
  catch(RuntimeException $error) {$check(str_contains($error->getMessage(),'schema is partial'),'partial_unique_approval_index_fails_closed');}
  $db->schema()->addUniqueKey('famtastic_acquisition_dispatch','approval',['approval_ref']);
  $db->schema()->dropField('famtastic_acquisition_dispatch','manifest_hash');
  try {famtastic_pipeline_update_8067($updateSandbox);$check(FALSE,'partial_migration_fails_closed');}
  catch(RuntimeException $error) {$check(str_contains($error->getMessage(),'schema is partial'),'partial_migration_fails_closed');}
  $definition=\Drupal\famtastic_pipeline\Service\AcquisitionSampleSchema::tables()['famtastic_acquisition_dispatch'];
  $db->schema()->addField('famtastic_acquisition_dispatch','manifest_hash',$definition['fields']['manifest_hash']);
  $report['status']='passed';return;
}
$now = \Drupal::time()->getRequestTime();
$prospect = \Drupal::entityTypeManager()->getStorage('famtastic_prospect')->create(['business_name' => 'Juniper Test Hair', 'public_email' => 'journey@example.test', 'campaign' => 'acquisition-199', 'source' => 'local_synthetic', 'status' => 'new']);
$prospect->save();
$campaignId = (int) $db->select('famtastic_campaign','c')->fields('c',['id'])->condition('campaign_key','acquisition-199')->execute()->fetchField();
if (!$campaignId) $campaignId = (int) $db->insert('famtastic_campaign')->fields(['campaign_key' => 'acquisition-199', 'name' => 'Local synthetic acquisition proof', 'status' => 'draft', 'created' => $now, 'changed' => $now])->execute();
$recipes = [];
foreach (['beauty_editorial', 'beauty_service_first'] as $id) $recipes[] = ['id' => $id, 'version' => 1, 'title' => $id, 'summary' => 'Synthetic review fixture', 'artifact_path' => 'marketing/campaigns/acquisition-199/templates/' . $id . '.html', 'sha256' => hash_file('sha256', $sandbox . '/marketing/campaigns/acquisition-199/templates/' . $id . '.html'), 'review' => ['status' => 'approved', 'reviewer' => 'synthetic-fixture-only', 'receipt' => 'local-synthetic-not-owner-approval'], 'recipe_ref' => ['owner' => 'component-studio', 'id' => $id, 'version' => 1, 'status' => 'registered']];
$qualification = ['business_verified' => TRUE, 'niche_confirmed' => TRUE, 'confirmed_niche' => 'beauty_hair', 'contact_owned' => TRUE, 'jurisdiction_eligible' => TRUE, 'provider_eligible' => TRUE, 'history_reconciled' => TRUE, 'bindings_verified' => TRUE, 'receipt' => 'synthetic-fixture-only', 'sha256' => hash('sha256', 'synthetic-not-qualified-real-business')];
$runKey = bin2hex(random_bytes(4));
$preferenceBefore = (int) $db->select('famtastic_event','e')->condition('event_type','acquisition.sample_preference')->countQuery()->execute()->fetchField();
$invite = $samples->issue('synthetic:journey:' . $runKey, 'journey@example.test', (int) $prospect->id(), $campaignId, 'beauty_hair', $recipes, ['business_name' => 'Juniper Test Hair', 'locality' => 'Fictional City', 'phone' => '+1 (555) 010-0100', 'booking_url' => 'https://example.test/book', 'inquiry_url' => 'https://example.test/inquire'], $qualification, $now + 86400 * 10);
$before = $db->select('famtastic_event', 'e')->countQuery()->execute()->fetchField();
$sampleController = AcquisitionSampleController::create(\Drupal::getContainer());
for ($i = 0; $i < 3; $i++) $sampleController->resolve($invite['token']);
$check((int) $before === (int) $db->select('famtastic_event', 'e')->countQuery()->execute()->fetchField(), 'scanner_resolution_no_events');
$response = $sampleController->preview($invite['token'], 'beauty_editorial');
$check($response->getStatusCode() === 200 && str_contains($response->getContent(), 'Juniper Test Hair') && $response->headers->get('Cache-Control') === 'no-store, private', 'frozen_bound_private_preview');
$samples->preference($invite['token'], 'beauty_editorial');
$samples->preference($invite['token'], 'beauty_editorial');
$check((int) $db->select('famtastic_event', 'e')->condition('event_type', 'acquisition.sample_preference')->countQuery()->execute()->fetchField() === $preferenceBefore + 1, 'idempotent_sample_preference');
$request = Request::create('http://acquisition-drupal.example.test/api/customer/register', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['name' => 'Juniper Fixture', 'email' => 'journey@example.test', 'password' => 'synthetic-password-only-199', 'sample_continuation' => $invite['token']], JSON_THROW_ON_ERROR));
$auth = CustomerPortalController::create(\Drupal::getContainer());
$response = $auth->register($request);
$check(in_array($response->getStatusCode(), [201, 202], TRUE), 'real_registration_controller');
$customer = $portal->customerForEmail('journey@example.test');
$check($customer && empty($customer['verified_at']) && !$samples->continuation((int) $customer['id']), 'pending_account_not_claimed');
$check((int) $db->select('famtastic_project_request', 'r')->condition('customer_id', $customer['id'])->countQuery()->execute()->fetchField() === 0, 'signup_hook_no_request_or_proof_generation');
$mail = array_map(static fn(string $line): array => json_decode($line, TRUE, 32, JSON_THROW_ON_ERROR), file($sandbox . '/mail.jsonl', FILE_IGNORE_NEW_LINES));
$verification = array_values(array_filter($mail, static fn(array $m): bool => $m['to'] === 'journey@example.test' && str_contains($m['subject'], 'Verify')));
preg_match('/token=([A-Za-z0-9_-]+)/', end($verification)['body'], $match);
$check(!empty($match[1]), 'verification_memory_capture_only');
$verify = Request::create('http://acquisition-drupal.example.test/api/customer/verify', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['token' => $match[1]], JSON_THROW_ON_ERROR));
$verified = $auth->verify($verify);
$payload = json_decode($verified->getContent(), TRUE, 32, JSON_THROW_ON_ERROR);
$check($verified->getStatusCode() === 200 && $payload['continuation']['recipe_id'] === 'beauty_editorial' && preg_match('/^[a-f0-9]{32}$/D', $payload['continuation']['context_id']) === 1, 'cross_device_verified_durable_continuation');
$check($auth->verify($verify)->getStatusCode() === 422, 'verification_replay_rejected');
$organization = $portal->organizations((int) $customer['id'])[0];
$created = $portal->createWebsiteRequest((int) $customer['id'], $organization['public_id'], ['project_name' => 'Juniper test website', 'business_name' => 'Juniper Test Hair', 'project_type' => 'new_website', 'action' => 'submit', 'primary_goal' => 'Test inquiry path', 'products_services' => 'Fictional hair services', 'acquisition_context_id' => $payload['continuation']['context_id']]);
$row = $db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $created['public_id'])->execute()->fetchAssoc();
$intake = json_decode((string) $row['intake_data'], TRUE, 32, JSON_THROW_ON_ERROR);
$check((int) $row['prospect_id'] !== (int) $prospect->id() && $intake['acquisition_context']['preferred_recipe'] === 'beauty_editorial' && $intake['acquisition_context']['formal_proof_selection'] === FALSE, 'distinct_production_request_prospect_and_preference_context');
$check($samples->associatedRequest((int) $customer['id'], $payload['continuation']['context_id']) === (int) $row['id'], 'exact_native_request_association');
$reportData = \Drupal::service('famtastic_pipeline.acquisition_report')->report('acquisition-199');
$report['report_snapshot'] = $reportData;
$check(isset($reportData['totals']) && $reportData['totals']['completed_interviews'] === 1, 'installed_native_campaign_report');
$allDrafts = json_decode(file_get_contents($sandbox . '/marketing/campaigns/acquisition-199/messages.json'), TRUE, 32, JSON_THROW_ON_ERROR);
$drafts = [];
foreach ($allDrafts['messages'] as $draft) if ($draft['niche'] === 'beauty_hair') $drafts[$draft['day']] = $draft + ['qr_sha256' => hash_file('sha256', $sandbox . '/marketing/campaigns/acquisition-199/assets/connect-qr.png')];
$sequence = $sequences->stage($invite['id'], 'journey@example.test', $invite['token'], $drafts);
$sequences->activate($sequence['sequence_id'], $now, 'synthetic-schedule-only');
$message = $sequence['message_ids'][0];
$contentHash = $db->select('famtastic_acquisition_message', 'm')->fields('m', ['content_hash'])->condition('message_id', $message)->execute()->fetchField();
$manifest = ['transport' => 'synthetic_memory_only', 'sequence_id' => $sequence['sequence_id'], 'expires' => $now + 3600, 'approval_ref' => 'synthetic-exact-content-only', 'messages' => [(string) $message => ['recipient' => 'journey@example.test', 'content_hash' => $contentHash]]];
putenv('FAMTASTIC_ACQUISITION_MEMORY_SECRET=local-synthetic-fixture-signing-key-only');
$signature = hash_hmac('sha256', json_encode($manifest, JSON_THROW_ON_ERROR), (string) getenv('FAMTASTIC_ACQUISITION_MEMORY_SECRET'));
$capture = \Drupal::service('famtastic_pipeline.acquisition_sample_memory')->capture($sequence['sequence_id'], $message, $manifest, $signature);
$check(!$capture['inbox_delivery'] && str_contains($capture['captured']['html'], 'cid:connect-qr'), 'controlled_synthetic_exact_content_capture');
$check(\Drupal::service('famtastic_pipeline.acquisition_sample_memory')->capture($sequence['sequence_id'], $message, $manifest, $signature)['duplicate'], 'controlled_synthetic_capture_replay');
// A separate live synthetic invitation enables actual HTTP/browser testing.
$browser = $samples->issue('synthetic:browser:' . $runKey, 'journey@example.test', (int) $prospect->id(), $campaignId, 'beauty_hair', $recipes, ['business_name' => 'Juniper Browser Fixture', 'locality' => 'Fictional City'], $qualification, $now + 86400 * 10);
file_put_contents($sandbox . '/fixture.json', json_encode(['token' => $browser['token'], 'email' => 'journey@example.test', 'password' => 'synthetic-password-only-199', 'verification_token_consumed' => TRUE, 'sandbox' => $sandbox], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
\Drupal::service('famtastic_pipeline.operational_ledger')->recordConsent('journey@example.test', 'unsubscribed', (int) $prospect->id());
$check($sequences->due($sequence['sequence_id']) === [], 'immediate_optout_exit');
$samples->revoke($invite['id']);
$check($samples->resolve($invite['token']) === NULL && $samples->continuation((int) $customer['id'])['request_public_id'] === $created['public_id'], 'revoked_public_room_preserves_account_resume');
$report['status'] = 'passed';
print "PASS: installed synthetic acquisition journey; no customer sends or real payments.\n";
