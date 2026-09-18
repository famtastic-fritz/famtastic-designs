<?php
/** Narrow, owner-authorized staff-assisted proof handoff. No mail or payment. */
declare(strict_types=1);
$confirm = getenv('CLASS2000_CONFIRM_REQUEST');
if ($confirm !== '8000fc68-aae3-4f40-a3de-2251bd09076a') throw new RuntimeException('Exact request confirmation required.');
$db = \Drupal::database();
$row = $db->select('famtastic_project_request', 'r')->fields('r')->condition('id', 16)->execute()->fetchAssoc();
$customer = $db->select('famtastic_customer', 'c')->fields('c')->condition('id', 14)->execute()->fetchAssoc();
if (!$row || $row['public_id'] !== $confirm || (int)$row['customer_id'] !== 14 || (int)$row['organization_id'] !== 14 || (int)$row['prospect_id'] !== 298 || !$customer || !$customer['verified_at'] || strtolower($customer['email']) !== 'mbshclassof2000@gmail.com') throw new RuntimeException('Request/customer/organization identity mismatch.');
$ledger = \Drupal::service('famtastic_pipeline.operational_ledger');
$portal = \Drupal::service('famtastic_pipeline.customer_portal');
$proofs = \Drupal::service('famtastic_pipeline.proof_campaign_service');
if (\Drupal::service('famtastic_pipeline.site_studio_proof_client')->isRemote()
  || getenv('FAMTASTIC_ALLOW_NO_IMAGE_PILOT_PROOFS') === '1'
  || getenv('FAMTASTIC_ALLOW_STUB_OUTREACH') === '1'
  || \Drupal\Core\Site\Settings::get('famtastic_allow_no_image_pilot_proofs', FALSE)) {
  throw new RuntimeException('This narrow handoff requires actual local-artifact mode without stub generation or remote dispatch.');
}
$key = 'website-request:16:staff-assisted-brief:owner-approved-20260918:v1';
$brief = [
  'schema' => 'famtastic.staff-assisted-brief.v1', 'actor' => 'codex:class-2000-delivery',
  'authority' => 'Fritz explicitly approved Client delivery first plan on 2026-09-18',
  'customer_supplied' => FALSE, 'preserve_original_customer_intake' => TRUE,
  'purpose' => 'Create three reunion website proofs for Miami Beach Senior High Class of 2000.',
  'directions' => ['a' => 'Hi-Tide Legacy', 'b' => 'Miami After Dark', 'c' => 'Class of 2000: Then & Now'],
  'proposed_scope' => ['event information', 'RSVP', 'tickets and sponsorship', 'alumni access', 'dinner preferences', 'memories', 'memorial and time capsule', 'committee administration', 'help'],
  'reference' => 'https://mbsh96reunion.com/',
  'unknowns' => ['date', 'venue', 'ticket pricing', 'capacity', 'photos and rights', 'committee details', 'domain'],
  'private_offer' => ['amount_minor' => 19900, 'currency' => 'USD', 'status' => 'owner_authorized_not_paid', 'scope' => 'reasonable launch work toward the agreed reunion functionality; unusual costs and subscriptions require an exception', 'payment_timing' => 'offered after direction selection; not necessary for proofs or staging'],
  'restrictions' => ['no borrowed private Class of 1996 data or assets', 'no payment captured', 'no final launch', 'no email from this script'],
];
$existing = $db->select('famtastic_event','e')->fields('e',['payload'])->condition('event_key',$key)->execute()->fetchField();
$tx = $db->startTransaction();
try {
  if (!$existing) {
    if ($row['status'] !== 'draft' || !empty($row['proof_campaign_id'])) throw new RuntimeException('Original draft changed; inspect before continuing.');
    $original = json_decode($row['intake_data'], TRUE, 512, JSON_THROW_ON_ERROR);
    if (($original['primary_goal'] ?? '') !== 'high school reunion website') throw new RuntimeException('Original customer brief differs.');
    $ledger->recordEvent($key,'website_request.staff_assisted_brief',[ 'website_request_id'=>16,'customer_id'=>14,'actor'=>'codex:class-2000-delivery','authority'=>$brief['authority'],'original_status'=>$row['status'],'original_intake_raw'=>$row['intake_data'],'original_intake_sha256'=>hash('sha256',$row['intake_data']),'staff_assisted_brief'=>$brief ],298);
    // Original fields remain byte-for-value unchanged; owner additions are explicitly namespaced.
    $intake = $original;
    $intake['staff_assisted_brief'] = $brief;
    $now = time();
    $db->update('famtastic_project_request')->fields(['status'=>'submitted','intake_data'=>json_encode($intake,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),'submitted_at'=>$now,'changed'=>$now])->condition('id',16)->condition('status','draft')->isNull('proof_campaign_id')->execute();
  } else {
    $audit = json_decode($existing,TRUE,512,JSON_THROW_ON_ERROR);
    if (($audit['staff_assisted_brief']??NULL) !== $brief) throw new RuntimeException('Previously recorded staff brief does not match.');
  }
  $prospect = \Drupal::entityTypeManager()->getStorage('famtastic_prospect')->load(298);
  if (!$prospect) throw new RuntimeException('Prospect missing.');
  $context = $portal->websiteRequestProofContext(16);
  $bound = $portal->websiteRequestProofCampaignId(16);
  if ($bound) {
    $campaign = \Drupal::entityTypeManager()->getStorage('proof_campaign')->load($bound);
    if (!$campaign || (int)$campaign->get('prospect_id')->target_id !== 298 || !str_starts_with((string)$campaign->get('studio_job_id')->value,'local-')) throw new RuntimeException('Bound campaign identity changed.');
  } else {
    // Prospect298 already owns public-inquiry campaign55. Do not reuse, expire or alter it.
    // This existing service binds a distinct account-owned campaign before dispatch.
    $created = $proofs->createForProspect($prospect,$context);
    $campaign = $created['campaign'];
    if (count($created['variants']) !== 0 || $campaign->get('generation_status')->value !== 'waiting_callback') throw new RuntimeException('Unexpected generated/stub campaign state.');
    $campaign->set('business_name',$row['project_name']);
    $campaign->save();
  }
  $briefHash = hash('sha256',json_encode($context['website_discovery_v3'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
  $jobId = $ledger->enqueue('website_proof.generate.v1:request:16:brief:'.$briefHash,'proof.generate',array_merge($context,['brief_version'=>1,'brief_sha256'=>$briefHash,'prospect_id'=>298,'staff_assisted'=>TRUE]),298);
  $ledger->recordEvent('website-request:16:staff-assisted-handoff:'.(int)$campaign->id(),'website_request.staff_assisted_handoff',['website_request_id'=>16,'campaign_id'=>(int)$campaign->id(),'job_id'=>$jobId,'actor'=>'codex:class-2000-delivery','no_customer_submission_impersonated'=>TRUE,'notification_queued'=>FALSE,'payment_changed'=>FALSE],298);
  unset($tx);
  echo json_encode(['schema_version'=>2,'routine'=>'website_proof.generate.v1','website_request_id'=>16,'website_request_public_id'=>$confirm,'customer_id'=>14,'prospect_id'=>298,'campaign_entity_id'=>(int)$campaign->id(),'campaign_id'=>$campaign->get('campaign_id')->value,'job_id'=>$campaign->get('studio_job_id')->value,'queue_job_id'=>$jobId,'website_discovery_v3'=>$context['website_discovery_v3'],'staff_assisted_brief'=>$brief,'required_directions'=>$brief['directions'],'notification_queued'=>FALSE,'payment_changed'=>FALSE],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $e) { $tx->rollBack(); throw $e; }
