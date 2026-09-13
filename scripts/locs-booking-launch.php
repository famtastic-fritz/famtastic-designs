<?php
/** Drush php:script: defaults read-only; LOCS_BOOKING_ACTION=enable-exact-locs opts in. */
declare(strict_types=1);
$site = 'site-dffd4cb9c3aa47fd';
$db = \Drupal::database();
$binding = $db->select('famtastic_booking_site_owner','b')->fields('b')->condition('site_key',$site)->execute()->fetchAssoc();
$customer = \Drupal::service('famtastic_pipeline.customer_portal')->customerForId(11);
$request = $db->select('famtastic_project_request','r')->fields('r')->condition('id',12)->execute()->fetchAssoc();
$membership = $db->select('famtastic_membership','m')->condition('customer_id',11)->condition('organization_id',11)->condition('status','active')->countQuery()->execute()->fetchField();
$ready = $binding && (int)$binding['customer_id']===11 && (int)$binding['organization_id']===11 && (int)$binding['website_request_id']===12 && $binding['status']==='active'
  && $customer && !empty($customer['verified_at']) && filter_var($customer['email']??'',FILTER_VALIDATE_EMAIL)
  && $request && (int)$request['customer_id']===11 && (int)$request['organization_id']===11 && $request['status']==='converted'
  && (int)$request['commerce_order_id']===19 && (int)$request['project_id']===5 && (int)$membership>0;
$action = getenv('LOCS_BOOKING_ACTION') ?: 'inspect';
if (!in_array($action,['inspect','enable-exact-locs'],TRUE)) throw new RuntimeException('Unknown action');
if (!$ready) throw new RuntimeException('Exact verified owner binding prerequisite failed; no changes');
$config=\Drupal::configFactory()->getEditable('famtastic_pipeline.settings');
if ($action==='enable-exact-locs') {
  foreach (['booking_request_enabled_sites','booking_availability_enabled_sites'] as $key) {
    $existing=$config->get($key) ?: []; if (!is_array($existing)) throw new RuntimeException('Unexpected config; no save');
    if (!in_array($site,$existing,TRUE)) $existing[]=$site;
    $config->set($key,array_values($existing));
  }
  $config->save(TRUE);
}
$result=['scope'=>$site,'action'=>$action,'verified_owner_ready'=>(bool)$ready,'requests_enabled'=>in_array($site,$config->get('booking_request_enabled_sites')?:[],TRUE),'availability_enabled'=>in_array($site,$config->get('booking_availability_enabled_sites')?:[],TRUE),'notifications_sent_by_script'=>0];
$result['general_outbox_dispatch_locked']=\Drupal::service('famtastic_pipeline.pilot_exact_dispatch_lock')->isActive();
$reference=getenv('LOCS_BOOKING_REFERENCE');
if ($reference) {
  if (!preg_match('/^[a-f0-9-]{36}$/D',$reference)) throw new RuntimeException('Invalid reference');
  $capture=$db->select('famtastic_booking_request','b')->fields('b',['public_id','site_key','status','created'])->condition('public_id',$reference)->condition('site_key',$site)->execute()->fetchAssoc();
  $alert=$db->select('famtastic_notification_outbox','n')->fields('n',['status','attempts','sent_at','provider_message_id','recipient'])->condition('notification_key','booking-request:'.$reference.':owner')->execute()->fetchAssoc();
  $result['capture']=$capture ?: NULL;
  $result['owner_alert']=$alert ? ['status'=>$alert['status'],'attempts'=>(int)$alert['attempts'],'sent_at'=>$alert['sent_at'],'provider_message_id_present'=>!empty($alert['provider_message_id']),'recipient_matches_bound_owner'=>mb_strtolower($alert['recipient'])===mb_strtolower($customer['email']),'meaning'=>'sent means mail transport accepted; not recipient inbox/read proof'] : NULL;
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
