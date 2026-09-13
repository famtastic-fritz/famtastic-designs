<?php
/** Exact-recipient, idempotent launch notices after recorded live proof. */
declare(strict_types=1);
$db=\Drupal::database();
$customer=\Drupal::service('famtastic_pipeline.customer_portal')->customerForId(11);
$project=\Drupal::entityTypeManager()->getStorage('famtastic_project')->load(5);
$reference='f82a13ba-293d-40f8-baee-5cff1c9f85f5';
$qa=$db->select('famtastic_booking_request','b')->fields('b',['status'])->condition('public_id',$reference)->condition('site_key','site-dffd4cb9c3aa47fd')->execute()->fetchField();
$alert=$db->select('famtastic_notification_outbox','n')->fields('n',['status'])->condition('notification_key','booking-request:'.$reference.':owner')->execute()->fetchField();
if (!$project || $project->get('delivery_status')->value!=='launched' || $project->get('live_url')->value!=='https://tightenupyourlocs.com/' || $qa!=='closed' || $alert!=='sent' || mb_strtolower($customer['email']??'')!=='junyeismom@gmail.com' || empty($customer['verified_at'])) throw new RuntimeException('Launch proof or exact recipient gate not satisfied');
$file=getenv('LOCS_NOTICE_FILE')?:'';
if (!is_file($file) || (fileperms($file)&0077)) throw new RuntimeException('Private reviewed notice file required');
$notices=json_decode(file_get_contents($file),TRUE,16,JSON_THROW_ON_ERROR);
if (array_keys($notices)!==['customer','fritz']) throw new RuntimeException('Exactly two reviewed notices required');
$recipients=['customer'=>'junyeismom@gmail.com','fritz'=>'fritz.medine@gmail.com'];
$keys=[];$now=time();
foreach ($notices as $role=>$notice) {
  if (!is_string($notice['subject']??NULL) || !is_string($notice['body']??NULL) || strlen($notice['subject'])>200 || strlen($notice['body'])>12000) throw new RuntimeException('Invalid notice');
  $key='locs-launch:2026-09-13:'.$role;$keys[]=$key;
  if (getenv('LOCS_NOTICE_ACTION')==='send-exact-locs-launch') {
    \Drupal::service('famtastic_pipeline.customer_portal')->queueNotification($key, $role==='customer'?'transactional':'operational', $recipients[$role], $notice['subject'], $notice['body']);
  }
}
if (getenv('LOCS_NOTICE_ACTION')==='send-exact-locs-launch') $result=\Drupal::service('famtastic_pipeline.lifecycle_operations')->dispatchNotifications(2,$keys);
else $result=['status'=>'reviewed_notice_preflight_passed','sent'=>0];
$result['receipts']=$db->select('famtastic_notification_outbox','n')->fields('n',['notification_key','status','attempts','sent_at','provider_message_id'])->condition('notification_key',$keys,'IN')->execute()->fetchAll(\PDO::FETCH_ASSOC);
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
