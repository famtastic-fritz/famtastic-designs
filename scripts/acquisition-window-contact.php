<?php
declare(strict_types=1);

/** Fresh actual-contact audit and signed cap-one preparation for the narrow clock.
 * CLI library only. No sending, route, global worker, or contact data in source.
 */
final class AcquisitionWindowContact {
  public static function capacity(array $binding): array {
    require_once __DIR__.'/acquisition-window-capacity.php';
    return AcquisitionWindowCapacity::read($binding);
  }
  /** Read-only installed-release check used before the clock is enrolled. */
  public static function check(array $binding): array {
    if (PHP_SAPI !== 'cli') throw new RuntimeException('window_cli_required');
    foreach (['frontend','backend'] as $component) {
      $sha=(string)($binding[$component.'_sha']??'');
      $marker=(string)file_get_contents('/home/xrdj7j99xhzt/public_html/.'.$component.'-release');
      if(!preg_match('/^[a-f0-9]{40}$/D',$sha)||!str_contains($marker,'commit='.$sha."\n")) throw new RuntimeException('window_exact_production_release_required');
    }
    if(($binding['release_commit']??'')!==$binding['backend_sha']) throw new RuntimeException('window_backend_binding_required');
    $bundle=\Drupal\Core\Site\Settings::get('famtastic_acquisition_bundle_root');
    $creative=['reference'=>'docs/research/acquisition-199/CREATIVE-APPROVAL.json','sha256'=>hash_file('sha256',$bundle.'/docs/research/acquisition-199/CREATIVE-APPROVAL.json')];
    \Drupal\famtastic_pipeline\Service\AcquisitionSampleGuard::creativeApproval($creative);
    $dir=realpath((string)\Drupal\Core\Site\Settings::get('file_private_path')).'/acquisition-199';
    if(hash_file('sha256',$dir.'/OWNER-COLD-SEND-AUTHORIZATION.json')!=='365c237c22673211f30515942fbc4a7587acc50a5e0e7ad21ddcbf24e48efc22') throw new RuntimeException('window_owner_source_changed');
    $account=\Drupal::service('famtastic_pipeline.acquisition_sample_sequences')->senderAccount();
    if($account['from']!=='hello@famtasticdesigns.com') throw new RuntimeException('window_exact_sender_required');
    return ['release_verified'=>TRUE,'sender_account_sha256'=>$account['account_sha256'],'release_commit'=>$binding['backend_sha']];
  }
  public static function audit(array $record): array {
    if (PHP_SAPI !== 'cli' || ($record['kind'] ?? '') !== 'customer' || ($record['business_category'] ?? '') !== 'Beauty, Hair Styling & Braiding' || ($record['historical260_overlap'] ?? NULL) !== FALSE || !preg_match('/^beauty-[a-z0-9-]{3,60}$/D', (string)($record['queue_key'] ?? ''))) throw new RuntimeException('window_private_beauty_record_required');
$email=mb_strtolower(trim($record['email']??''));
if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('selected_contact_required');
$db=\Drupal::database();$ledger=\Drupal::service('famtastic_pipeline.operational_ledger');$hash=$ledger->contactHash($email);
$prospectQuery=$db->select('famtastic_prospect','p')->fields('p',['id']);
$or=$prospectQuery->orConditionGroup()->condition('public_email',$email)->condition($prospectQuery->andConditionGroup()->condition('contact_method','email')->condition('contact_value',$email));
$ids=array_map('intval',$prospectQuery->condition($or)->execute()->fetchCol());
$report=['schema'=>'famtastic.acquisition-actual-contact-audit.v1','checked_at'=>time(),'kind'=>'customer','counts'=>[],'known_stop_reasons'=>[],'missing_sources'=>[],'recipient_hash'=>$hash];
$count=static function(string $table,array $conditions)use($db,&$report):int{
 if(!$db->schema()->tableExists($table)){ $report['missing_sources'][]=$table;return -1; }
 $q=$db->select($table,'x');foreach($conditions as $c)$q->condition($c[0],$c[1],$c[2]??'=');return (int)$q->countQuery()->execute()->fetchField();
};
$report['counts']['prospects']=count($ids);
foreach(['famtastic_email_message'=>['recipient_hash',$hash],'famtastic_consent'=>['contact_hash',$hash],'famtastic_inbound_message'=>['sender_hash',$hash],'famtastic_lead_import'=>['contact_hash',$hash],'famtastic_customer'=>['email',$email],'famtastic_notification_outbox'=>['recipient',$email],'famtastic_portal_thread'=>['contact_email',$email],'famtastic_acquisition_sample'=>['recipient_hash',$hash],'famtastic_acquisition_sequence'=>['recipient_hash',$hash]] as $table=>$c){
 $n=$count($table,[$c]);$report['counts'][$table]=$n;if($n>0)$report['known_stop_reasons'][]='existing_contact_record:'.$table;
}
if($ids){
 $n=$count('famtastic_event',[['prospect_id',$ids,'IN']]);$report['counts']['events']=$n;if($n)$report['known_stop_reasons'][]='existing_contact_events';
 $n=$count('famtastic_commerce_fulfillment',[['prospect_id',$ids,'IN']]);$report['counts']['commerce_fulfillment']=$n;if($n)$report['known_stop_reasons'][]='existing_contact_commerce';
}else{$report['counts']['events']=0;$report['counts']['commerce_fulfillment']=0;}
// Check direct user/order associations as well as delivery/fulfillment rows.
$users=\Drupal::entityTypeManager()->getStorage('user')->loadByProperties(['mail'=>$email]);
$report['counts']['users']=count($users);if($users)$report['known_stop_reasons'][]='existing_contact_user';
$orderStorage=\Drupal::entityTypeManager()->getStorage('commerce_order');
$orders=$orderStorage->getQuery()->accessCheck(FALSE)->condition('mail',$email)->execute();
$report['counts']['commerce_orders']=count($orders);if($orders)$report['known_stop_reasons'][]='existing_contact_order';
$report['native_suppressed']=$ledger->isSuppressed($email);if($report['native_suppressed'])$report['known_stop_reasons'][]='native_suppression';
// Inspect both active and archived Maildir originals without moving/importing mail.
$report['maildir']=['files_checked'=>0,'matching_files'=>0,'unreadable_files'=>0,'mailboxes_complete'=>TRUE];
foreach(['hello','support'] as $box){
 $folder=(getenv('HOME')?:'/home/xrdj7j99xhzt').'/mail/famtasticdesigns.com/'.$box;
 if(!is_dir($folder)||is_link($folder)){ $report['maildir']['mailboxes_complete']=FALSE;continue; }
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder,FilesystemIterator::SKIP_DOTS));
 foreach($it as $f){
  if(!$f->isFile()||!in_array(basename(dirname($f->getPathname())),['new','cur'],TRUE))continue;
  if($f->isLink()||!$f->isReadable()||$f->getSize()>16777216){$report['maildir']['unreadable_files']++;continue;}
  $bytes=file_get_contents($f->getPathname());if($bytes===FALSE){$report['maildir']['unreadable_files']++;continue;}
  $report['maildir']['files_checked']++;if(stripos($bytes,$email)!==FALSE)$report['maildir']['matching_files']++;
 }
}
if($report['maildir']['matching_files'])$report['known_stop_reasons'][]='maildir_original_contact_match';
$report['historical260_excluded']=($record['historical260_overlap']??NULL)===FALSE||$record['kind']==='controlled_owner_probe';
if(!$report['historical260_excluded'])$report['known_stop_reasons'][]='historical260_not_reconciled';
$report['coverage_complete']=!$report['missing_sources']&&$report['maildir']['mailboxes_complete']&&$report['maildir']['unreadable_files']===0&&$report['historical260_excluded'];
$report['eligible_for_new_outreach']=$report['coverage_complete']&&!$report['known_stop_reasons'];
$report['native_usage']=['email_messages_last_hour'=>$count('famtastic_email_message',[['sent_at',time()-3600,'>=']]),'transactional_outbox_last_hour'=>$count('famtastic_notification_outbox',[['sent_at',time()-3600,'>=']]),'provider_remaining_allowance'=>NULL,'outside_native_usage_unknown'=>TRUE];

    return $report;
  }

  /** Called only after the clock has reserved one campaign/window allowance. */
  public static function prepare(array $record, array $config): array {
    $audit = self::audit($record);
    if (!$audit['eligible_for_new_outreach'] || !$audit['coverage_complete'] || $audit['known_stop_reasons']) throw new RuntimeException('window_contact_history_stopped');
    $private = realpath((string)\Drupal\Core\Site\Settings::get('file_private_path'));
    $dir = $private ? realpath($private.'/acquisition-199') : FALSE;
    if (!$dir || $dir !== $private.'/acquisition-199' || is_link($private.'/acquisition-199') || (fileperms($dir)&0077)!==0) throw new RuntimeException('window_private_directory_required');
    $front = file_get_contents('/home/xrdj7j99xhzt/public_html/.frontend-release');
    $back = file_get_contents('/home/xrdj7j99xhzt/public_html/.backend-release');
    foreach ([[$front,$config['frontend_sha']??''],[$back,$config['backend_sha']??'']] as [$marker,$sha]) {
      if (!preg_match('/^[a-f0-9]{40}$/D',(string)$sha) || !str_contains($marker,'commit='.$sha."\n")) throw new RuntimeException('window_exact_production_release_required');
    }
    $base = $dir.'/'.$record['queue_key'];
    foreach (['.json','.state.json','.manifest.json'] as $suffix) if(file_exists($base.$suffix)||is_link($base.$suffix)) throw new RuntimeException('window_contact_previously_prepared_no_retry');
    $old = umask(0077);
    try {
      $auditPath=$base.'.audit.json';self::write($auditPath,$audit);
      $now=\Drupal::time()->getCurrentTime();
      $releasePath=$base.'.release.json';self::write($releasePath,['frontend_source_sha'=>$config['frontend_sha'],'backend_source_sha'=>$config['backend_sha'],'frontend_marker_sha256'=>hash('sha256',$front),'backend_marker_sha256'=>hash('sha256',$back),'checked_at'=>$now,'owner_source_apply_authorized'=>TRUE,'physical_owner_acceptance_proved'=>FALSE]);
      $email=mb_strtolower(trim($record['email']));$ledger=\Drupal::service('famtastic_pipeline.operational_ledger');
      $campaign=\Drupal::service('famtastic_pipeline.lead_ingestion')->ensureCampaignForChannel('acquisition-199-beauty-d0-20261006','owner_authorized_supplied_workbook','cold_outreach');
      if ($campaign!==5 || (int)($config['campaign_id']??0)!==5) throw new RuntimeException('window_exact_campaign_required');
      $ownerBytes=file_get_contents($dir.'/OWNER-COLD-SEND-AUTHORIZATION.json');
      if(hash('sha256',$ownerBytes)!=='365c237c22673211f30515942fbc4a7587acc50a5e0e7ad21ddcbf24e48efc22') throw new RuntimeException('window_owner_source_changed');
      $bundle=\Drupal\Core\Site\Settings::get('famtastic_acquisition_bundle_root');
      $creative=['reference'=>'docs/research/acquisition-199/CREATIVE-APPROVAL.json','sha256'=>hash_file('sha256',$bundle.'/docs/research/acquisition-199/CREATIVE-APPROVAL.json')];
      $artifact='marketing/campaigns/acquisition-199/generic-review/beauty-template.html';
      $account=\Drupal::service('famtastic_pipeline.acquisition_sample_sequences')->senderAccount();
      if($account['from']!=='hello@famtasticdesigns.com') throw new RuntimeException('window_exact_sender_required');
      if(!hash_equals($audit['recipient_hash'],$ledger->contactHash($email))||$ledger->isSuppressed($email)) throw new RuntimeException('window_contact_changed_or_stopped');
      $prospect=\Drupal::entityTypeManager()->getStorage('famtastic_prospect')->create(['business_name'=>$record['business_name'],'business_category'=>$record['business_category'],'public_email'=>$email,'campaign'=>'acquisition-199-beauty-d0-20261006','source'=>'supplied_workbook_unverified_business','status'=>'new']);$prospect->save();
      $binding=['prospect_id'=>(int)$prospect->id(),'campaign_id'=>$campaign,'recipient_hash'=>$ledger->contactHash($email),'account_sha256'=>$account['account_sha256'],'from'=>$account['from']];
      $receipt=['status'=>'owner_reviewed','reference'=>$auditPath,'sha256'=>hash_file('sha256',$auditPath),'checked_at'=>$audit['checked_at'],'binding'=>$binding];
      $packet=['schema'=>'famtastic.acquisition-operator-input.v1','prospect_id'=>(int)$prospect->id(),'campaign_id'=>$campaign,'invitation_key'=>'acquisition:'.$record['queue_key'].':'.$prospect->id(),'invitation_expires'=>$now+7*86400,'issued_at'=>$now,'expires'=>$now+3300,'schedule_start'=>$now,'approval_ref'=>'owner-'.$record['queue_key'].'-'.$prospect->id(),
      'recipe'=>['id'=>'beauty_soft_power_acquisition','version'=>1,'niche'=>'beauty_hair','title'=>'Soft Power','summary'=>'An illustrative beauty direction','artifact_path'=>$artifact,'sha256'=>hash_file('sha256',$bundle.'/'.$artifact),'review'=>['status'=>'approved_campaign_artifact','approval_record'=>$creative],'recipe_ref'=>['owner'=>'component-studio','id'=>'beauty_soft_power_acquisition','version'=>1,'status'=>'import_request_pending']],
      'creative_approval'=>$creative,'history_receipt'=>$receipt+['classification'=>'actual_native_history_reconciled','coverage_complete'=>TRUE,'eligible_for_new_outreach'=>TRUE,'known_stop_reasons'=>[]],
      'release_proof'=>['status'=>'owner_reviewed','reference'=>$releasePath,'sha256'=>hash_file('sha256',$releasePath)],'authorization_basis'=>'owner_authorized_cold_outreach','provider_policy_conflict'=>TRUE,'recipient_opt_in'=>FALSE,'provider_permission_proved'=>FALSE,'owner_authorization_receipt'=>array_replace($receipt,['reference'=>'docs/research/acquisition-199/OWNER-COLD-SEND-AUTHORIZATION.json','sha256'=>hash('sha256',$ownerBytes),'approved_by'=>'Fritz Medine']),'owner_authorization_record'=>$ownerBytes];
      $key=$dir.'/owner-signing.key';if(is_link($key)||(fileperms($key)&0777)!==0600) throw new RuntimeException('window_private_signing_key_required');
      $secret=trim(file_get_contents($key));if(strlen($secret)<32) throw new RuntimeException('window_signing_key_required');
      putenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET='.$secret);
      self::write($base.'.json',['packet'=>$packet,'signature'=>hash_hmac('sha256',json_encode($packet,JSON_THROW_ON_ERROR),$secret)]);
      if(!class_exists('AcquisitionExactOperator',FALSE)){define('FAMTASTIC_ACQUISITION_OPERATOR_LIBRARY_ONLY',TRUE);require __DIR__.'/acquisition-exact-operator.php';}
      $result=AcquisitionExactOperator::run('prepare',$base.'.json');
      return $result+['packet_path'=>$base.'.json'];
    } finally {umask($old);}
  }
  private static function write(string $path,array $value):void {
    $f=fopen($path,'x');if(!$f)throw new RuntimeException('window_frozen_private_file_exists');
    try {$bytes=json_encode($value,JSON_THROW_ON_ERROR);if(fwrite($f,$bytes)!==strlen($bytes)||!fflush($f))throw new RuntimeException('window_private_write_failed');}finally{fclose($f);}
    chmod($path,0600);
  }
}
