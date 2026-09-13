<?php
/** Dedicated Drush php:script worker. Sends only this verified site's owner alerts. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') throw new RuntimeException('CLI required');
$site = 'site-dffd4cb9c3aa47fd';
$worker = 'locs_booking_owner_dispatch';
$started = time();
$db = \Drupal::database();
$result = ['processed'=>0,'sent'=>0,'failed'=>0,'retried'=>0,'selected'=>0];
$errorCode = NULL;
try {
  $binding=$db->select('famtastic_booking_site_owner','b')->fields('b')->condition('site_key',$site)->execute()->fetchAssoc();
  $customer=\Drupal::service('famtastic_pipeline.customer_portal')->customerForId(11);
  $request=$db->select('famtastic_project_request','r')->fields('r')->condition('id',12)->execute()->fetchAssoc();
  $membership=$db->select('famtastic_membership','m')->condition('customer_id',11)->condition('organization_id',11)->condition('status','active')->countQuery()->execute()->fetchField();
  $ready=$binding && (int)$binding['customer_id']===11 && (int)$binding['organization_id']===11 && (int)$binding['website_request_id']===12 && $binding['status']==='active'
    && $customer && !empty($customer['verified_at']) && filter_var($customer['email']??'',FILTER_VALIDATE_EMAIL)
    && $request && (int)$request['customer_id']===11 && (int)$request['organization_id']===11 && $request['status']==='converted'
    && (int)$request['commerce_order_id']===19 && (int)$request['project_id']===5 && (int)$membership>0;
  if (!$ready) throw new RuntimeException('scope_guard_failed');
  // Portable fixed-position UUID extraction; no MySQL-only CONCAT join. Verify
  // each complete reconstructed key again before it reaches the dispatcher.
  $query=$db->select('famtastic_notification_outbox','n');
  $query->innerJoin('famtastic_booking_request','b','b.public_id = SUBSTR(n.notification_key, 17, 36)');
  $query->fields('n',['notification_key'])->fields('b',['public_id']);
  $due=$query->andConditionGroup()->condition('n.status',['queued','retry'],'IN')->condition('n.available_at',$started,'<=');
  $expired=$query->andConditionGroup()->condition('n.status','dispatching')->condition('n.claimed_at',$started-1800,'<=');
  $query->condition($query->orConditionGroup()->condition($due)->condition($expired));
  $query->condition('b.site_key',$site)->condition('n.category','transactional')
    ->condition('n.recipient',mb_strtolower($customer['email']))
    ->condition('n.notification_key','booking-request:%:owner','LIKE')
    ->orderBy('n.created')->range(0,25);
  $keys=[];
  foreach ($query->execute() as $row) {
    if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$row->public_id)
      || $row->notification_key!=='booking-request:'.$row->public_id.':owner') throw new RuntimeException('notification_scope_invalid');
    $keys[]=$row->notification_key;
  }
  $result['selected']=count($keys);
  // [] explicitly means no work, never NULL (which means the broad queue).
  $dispatch=\Drupal::service('famtastic_pipeline.lifecycle_operations')->dispatchNotifications(25,$keys);
  foreach (['processed','sent','failed','retried'] as $field) $result[$field]=(int)($dispatch[$field]??0);
  if (!empty($dispatch['skipped']) && $dispatch['skipped']!=='empty_exact_scope') $errorCode='dispatch_blocked';
  if ($result['failed']>0 || $result['retried']>0) $errorCode='delivery_requires_attention';
} catch (Throwable) {
  $errorCode='worker_failed';
  $result['failed']=max(1,$result['failed']);
} finally {
  $finished=time();
  $db->merge('famtastic_worker_heartbeat')->key('worker_key',$worker)->fields([
    'worker_key'=>$worker,'status'=>$errorCode===NULL?'healthy':'degraded',
    'last_started'=>$started,'last_finished'=>$finished,'next_due'=>$finished+300,
    'processed'=>$result['processed'],'failed'=>$result['failed'],'retried'=>$result['retried'],
    'last_error'=>$errorCode,'changed'=>$finished,
  ])->execute();
}
echo json_encode(['worker'=>$worker,'status'=>$errorCode===NULL?'healthy':'degraded']+$result,JSON_UNESCAPED_SLASHES).PHP_EOL;
if ($errorCode!==NULL) throw new RuntimeException('Locs booking worker needs attention; inspect its scoped heartbeat');
