<?php
declare(strict_types=1);
/** Installed disposable SQLite proof. All transport callbacks restrict .test. */
$sandbox=realpath((string)getenv('ACQUISITION_DRUPAL_SANDBOX'))?:'';$db=\Drupal::database();$options=$db->getConnectionOptions();
if(!preg_match('#/famtastic-acquisition-drupal\.[a-zA-Z0-9]{6}$#D',$sandbox)||realpath(__DIR__)!==$sandbox.'/scripts'||realpath(\Drupal::root())!==$sandbox.'/backend/web'||($options['driver']??'')!=='sqlite'||realpath((string)$options['database'])!==$sandbox.'/backend/web/sites/default/files/.ht.sqlite'||function_exists('curl_exec')||function_exists('mail')||ini_get('allow_url_fopen')!=='0')throw new RuntimeException('Use isolated acquisition runtime only.');
$report=['schema'=>'famtastic.acquisition-window-installed-proof.v1','classification'=>'local_synthetic','status'=>'running','checks'=>[],'actual_sends'=>FALSE,'production_deployment'=>FALSE];
register_shutdown_function(static function()use(&$report):void{file_put_contents((string)getenv('ACQUISITION_DRUPAL_EVIDENCE').'/window-evidence.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");});
$check=static function(bool $ok,string $name)use(&$report):void{$report['checks'][$name]=$ok;if(!$ok){$report['status']='failed';throw new RuntimeException($name);}print 'PASS: '.$name."\n";};
require_once \Drupal::root().'/modules/custom/famtastic_pipeline/famtastic_pipeline.install';
foreach(\Drupal\famtastic_pipeline\Service\AcquisitionWindowSchema::tables() as $table=>$definition)if($db->schema()->tableExists($table))$db->schema()->dropTable($table);
$unused=[];famtastic_pipeline_update_8069($unused);
// Repeated proof resets only this sandbox's window journal and prior fictional
// Juniper Window Fixture rows. The safety predicate above excludes other sites.
foreach(\Drupal\famtastic_pipeline\Service\AcquisitionWindowSchema::tables() as $table=>$definition)$db->truncate($table)->execute();
$oldProspects=$db->select('famtastic_prospect','p')->fields('p',['id'])->condition('business_name','Juniper Window Fixture')->condition('source','local_synthetic')->execute()->fetchCol();
if($oldProspects){
  $oldMessages=$db->select('famtastic_email_message','m')->fields('m',['id'])->condition('prospect_id',$oldProspects,'IN')->execute()->fetchCol();
  if($oldMessages){foreach(['famtastic_acquisition_message','famtastic_acquisition_dispatch'] as $table)$db->delete($table)->condition('message_id',$oldMessages,'IN')->execute();$db->delete('famtastic_email_message')->condition('id',$oldMessages,'IN')->execute();}
  foreach(['famtastic_acquisition_sequence','famtastic_acquisition_sample','famtastic_event'] as $table)$db->delete($table)->condition('prospect_id',$oldProspects,'IN')->execute();
  foreach(\Drupal::entityTypeManager()->getStorage('famtastic_prospect')->loadMultiple($oldProspects) as $entity)$entity->delete();
}
foreach(\Drupal\famtastic_pipeline\Service\AcquisitionWindowSchema::tables() as $table=>$definition)$check($db->schema()->tableExists($table),'installed_'.$table);
$db->schema()->dropUniqueKey('famtastic_acquisition_slot','queue');try{famtastic_pipeline_update_8069($unused);$check(FALSE,'partial_unique_schema_rejected');}catch(RuntimeException){$check(TRUE,'partial_unique_schema_rejected');}$db->schema()->addUniqueKey('famtastic_acquisition_slot','queue',['queue_hash']);
$check(\Drupal::service('famtastic_pipeline.acquisition_window_executor') instanceof \Drupal\famtastic_pipeline\Service\AcquisitionWindowExecutor,'native_service_registration');
$GLOBALS['config']['smtp.settings']=['smtp_on'=>TRUE,'smtp_host'=>'smtp.synthetic.invalid','smtp_port'=>587,'smtp_username'=>'hello@famtasticdesigns.com','smtp_from'=>'hello@famtasticdesigns.com','smtp_protocol'=>'tls'];
$GLOBALS['config']['famtastic_pipeline.settings']=array_replace($GLOBALS['config']['famtastic_pipeline.settings']??[],['outreach_postal_address'=>"123 Fictional Test Street\nExample City, FL 00000"]);\Drupal::service('config.factory')->reset('smtp.settings')->reset('famtastic_pipeline.settings');
$bundle=$sandbox.'/backend/private/generic-d0';new \Drupal\Core\Site\Settings(array_replace(\Drupal\Core\Site\Settings::getAll(),['famtastic_acquisition_bundle_root'=>$bundle,'famtastic_acquisition_bundle_sha256'=>hash_file('sha256',$bundle.'/manifest.json')]));
$time=new class implements \Drupal\Component\Datetime\TimeInterface {
 public int $now;public int $request;
 public function __construct(){$this->now=$this->request=(new DateTimeImmutable('2026-10-07 09:00:00',new DateTimeZone('America/New_York')))->getTimestamp();}
 public function getRequestTime(){return $this->request;}public function getRequestMicroTime(){return (float)$this->request;}public function getCurrentTime(){return $this->now;}public function getCurrentMicroTime(){return (float)$this->now;}
};
$container=\Drupal::getContainer();$container->set('datetime.time',$time);$factory=\Drupal::service('config.factory');
$ledger=new \Drupal\famtastic_pipeline\Service\OperationalLedger($db,$time);$sequences=new \Drupal\famtastic_pipeline\Service\AcquisitionSampleSequenceService($db,$time,$ledger,$factory);$quota=new \Drupal\famtastic_pipeline\Service\AcquisitionWindowQuota($db,$time);$executor=new \Drupal\famtastic_pipeline\Service\AcquisitionWindowExecutor($time,$quota,$sequences,$ledger);
$container->set('famtastic_pipeline.operational_ledger',$ledger);$container->set('famtastic_pipeline.acquisition_sample_sequences',$sequences);$container->set('famtastic_pipeline.acquisition_samples',new \Drupal\famtastic_pipeline\Service\AcquisitionSampleService($db,$time,$ledger,$factory));
$mailer=new class extends \Drupal\famtastic_pipeline\Service\OutreachMailer { public int $calls=0;public bool $fail=FALSE;public function __construct(){}public function fromAddress():string{return 'hello@famtasticdesigns.com';}public function assertAcquisitionTransportAllowed():void{}public function sendFrozenAcquisition(string $to,array $snapshot,string $url):string{if(!str_ends_with($to,'@example.test'))throw new RuntimeException('Synthetic recipient only.');if(getenv('FAMTASTIC_ALLOW_REAL_OUTREACH')!=='1'||getenv('FAMTASTIC_ALLOW_ACQUISITION_REAL_OUTREACH')!=='1')throw new RuntimeException('synthetic_dispatch_flags_missing');$this->calls++;if($this->fail)throw new RuntimeException('synthetic_transport_uncertain');return '<synthetic-window-'.$this->calls.'@example.test>';}};
$container->set('famtastic_pipeline.acquisition_sample_exact_sender',new \Drupal\famtastic_pipeline\Service\AcquisitionSampleExactAdapter($db,$time,$ledger,$sequences,$mailer,$quota));
$private=realpath((string)\Drupal\Core\Site\Settings::get('file_private_path'));$dir=$private.'/acquisition-199';if(!is_dir($dir))mkdir($dir,0700);chmod($dir,0700);$secret='synthetic-window-secret-no-provider-access';putenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET='.$secret);file_put_contents($dir.'/owner-signing.key',$secret);chmod($dir.'/owner-signing.key',0600);
$nonce=bin2hex(random_bytes(4));$campaign=(int)$db->select('famtastic_campaign','c')->fields('c',['id'])->condition('campaign_key','acquisition-199')->execute()->fetchField();$account=$sequences->senderAccount();
// Only this fixture's archived helper is synthetic. Native preparation, signatures,
// compiler, quota, operator and exact adapter below are actual source services.
$commit=str_repeat('a',40);$source=$sandbox.'/home/deploy/famtastic-designs/releases/'.$commit.'/backend-source';if(!is_dir($source.'/scripts'))mkdir($source.'/scripts',0700,TRUE);file_put_contents($source.'/commit.txt',$commit."\n");copy(__DIR__.'/acquisition-exact-operator.php',$source.'/scripts/acquisition-exact-operator.php');file_put_contents($source.'/scripts/acquisition-window-capacity.php',"<?php // synthetic source pin only\n");
$helper= <<<'HELPER'
<?php
final class AcquisitionWindowContact {
 public static function check(array $binding):array{return ['release_verified'=>TRUE,'release_commit'=>$binding['release_commit'],'sender_account_sha256'=>$binding['sender_account_sha256']];}
 public static function capacity(array $binding):array{return ($GLOBALS['window_fixture_capacity'])($binding);}
 public static function prepare(array $record,array $binding):array{return ($GLOBALS['window_fixture_prepare'])($record,$binding);}
}
HELPER;
file_put_contents($source.'/scripts/acquisition-window-contact.php',$helper);
$GLOBALS['window_fixture_capacity']=static fn(array $binding):array=>['capacity_mode'=>'published_limit_with_reserved_budget','budget_kind'=>'conservative_budget','outside_usage_unknown'=>TRUE,'sender_account_sha256'=>$binding['sender_account_sha256'],'checked_at'=>$time->now,'available_today'=>200,'available_hour'=>50,'reference'=>'synthetic-reserved-budget','sha256'=>str_repeat('b',64),'published_mailbox_day_limit'=>500,'published_account_hour_limit'=>500,'transactional_unobserved_day_reserve'=>250,'transactional_unobserved_hour_reserve'=>400,'observed_today'=>0,'observed_hour'=>0];
$GLOBALS['window_fixture_prepare']=static function(array $record,array $binding)use($db,$time,$dir,$secret,$campaign,$account,$sandbox,$bundle):array{
 if(!str_ends_with($record['email'],'@example.test'))throw new RuntimeException('Synthetic recipient only.');
 $p=\Drupal::entityTypeManager()->getStorage('famtastic_prospect')->create(['business_name'=>'Juniper Window Fixture','business_category'=>'Beauty, Hair Styling & Braiding','public_email'=>$record['email'],'campaign'=>'acquisition-199','source'=>'local_synthetic','status'=>'new']);$p->save();
 $row=['prospect_id'=>(int)$p->id(),'campaign_id'=>$campaign,'recipient_hash'=>\Drupal::service('famtastic_pipeline.operational_ledger')->contactHash($record['email']),'account_sha256'=>$account['account_sha256'],'from'=>$account['from']];
 $owner=(string)file_get_contents($sandbox.'/backend/private/owner-cold-source-record.json');$creative=['reference'=>'docs/research/acquisition-199/CREATIVE-APPROVAL.json','sha256'=>hash_file('sha256',$bundle.'/docs/research/acquisition-199/CREATIVE-APPROVAL.json')];$artifact='marketing/campaigns/acquisition-199/generic-review/beauty-template.html';
 $receipt=['status'=>'owner_reviewed','reference'=>'synthetic-history-not-real-customer','sha256'=>str_repeat('c',64),'checked_at'=>$time->now,'binding'=>$row];
 $packet=['schema'=>'famtastic.acquisition-operator-input.v1','prospect_id'=>(int)$p->id(),'campaign_id'=>$campaign,'invitation_key'=>'window:'.$record['queue_key'],'invitation_expires'=>$time->now+86400,'issued_at'=>$time->now,'expires'=>$time->now+3300,'schedule_start'=>$time->now,'approval_ref'=>'fixture-'.$record['queue_key'],
 'recipe'=>['id'=>'beauty_soft_power_acquisition','version'=>1,'niche'=>'beauty_hair','title'=>'Soft Power','summary'=>'Illustrative','artifact_path'=>$artifact,'sha256'=>hash_file('sha256',$bundle.'/'.$artifact),'review'=>['status'=>'approved_campaign_artifact','approval_record'=>$creative],'recipe_ref'=>['owner'=>'component-studio','id'=>'beauty_soft_power_acquisition','version'=>1,'status'=>'import_request_pending']],
 'creative_approval'=>$creative,'history_receipt'=>$receipt+['classification'=>'actual_native_history_reconciled','coverage_complete'=>TRUE,'eligible_for_new_outreach'=>TRUE,'known_stop_reasons'=>[]],'release_proof'=>['status'=>'owner_reviewed','reference'=>'synthetic-installed-release','sha256'=>str_repeat('d',64)],'authorization_basis'=>'owner_authorized_cold_outreach','provider_policy_conflict'=>TRUE,'recipient_opt_in'=>FALSE,'provider_permission_proved'=>FALSE,'owner_authorization_receipt'=>array_replace($receipt,['reference'=>'docs/research/acquisition-199/OWNER-COLD-SEND-AUTHORIZATION.json','sha256'=>hash('sha256',$owner),'approved_by'=>'Fritz Medine']),'owner_authorization_record'=>$owner];
 $path=$dir.'/'.$record['queue_key'].'.json';file_put_contents($path,json_encode(['packet'=>$packet,'signature'=>hash_hmac('sha256',json_encode($packet,JSON_THROW_ON_ERROR),$secret)],JSON_THROW_ON_ERROR));chmod($path,0600);return ['packet_path'=>$path];
};
$queuePath=$dir.'/window-queue-'.$nonce.'.json';$configPath=$dir.'/window-config-'.$nonce.'.json';
$records=[];foreach([1,2] as $n)$records[]=['kind'=>'customer','queue_key'=>'beauty-window-'.$nonce.'-'.$n,'email'=>'window-'.$nonce.'-'.$n.'@example.test'];
$queue=['schema'=>'famtastic.acquisition-window-queue.v1','records'=>$records];file_put_contents($queuePath,json_encode($queue,JSON_THROW_ON_ERROR));chmod($queuePath,0600);
$config=['schema'=>'famtastic.acquisition-window-config.v1','enabled'=>TRUE,'timezone'=>'America/New_York','hours'=>[9,10,11,12],'window_cap'=>50,'day_cap'=>200,'starts_on'=>'2026-10-07','ends_on'=>'2026-10-08','campaign_id'=>$campaign,'sender_account_sha256'=>$account['account_sha256'],'pace_seconds'=>1,'release_commit'=>$commit,'contact_helper_path'=>$source.'/scripts/acquisition-window-contact.php','contact_helper_sha256'=>hash_file('sha256',$source.'/scripts/acquisition-window-contact.php'),'operator_sha256'=>hash_file('sha256',$source.'/scripts/acquisition-exact-operator.php'),'capacity_helper_sha256'=>hash_file('sha256',$source.'/scripts/acquisition-window-capacity.php'),'queue_path'=>$queuePath,'queue_sha256'=>hash_file('sha256',$queuePath)];
$save=static function()use(&$config,$configPath):void{file_put_contents($configPath,json_encode(['config'=>$config,'signature'=>\Drupal\famtastic_pipeline\Service\AcquisitionWindowExecutor::sign($config)],JSON_THROW_ON_ERROR));chmod($configPath,0600);};$save();
$before=(int)$db->select('famtastic_acquisition_slot','s')->countQuery()->execute()->fetchField();$r=$executor->run($configPath,TRUE);$check($r['status']==='checked'&&$r['sent']===0&&(int)$db->select('famtastic_acquisition_slot','s')->countQuery()->execute()->fetchField()===$before,'check_config_readonly_no_reservation_or_smtp');
$bytes=(string)file_get_contents($queuePath);file_put_contents($queuePath,$bytes.' ');try{$executor->run($configPath,TRUE);$check(FALSE,'queue_tamper_rejected');}catch(RuntimeException){$check(TRUE,'queue_tamper_rejected');}file_put_contents($queuePath,$bytes);
$r=$executor->run($configPath);$check($r['status']==='complete'&&($r['counts']['accepted']??0)===2&&$mailer->calls===2,'installed_native_exact_adapter_fake_accepts_two');
$check(getenv('FAMTASTIC_ALLOW_REAL_OUTREACH')==='false'&&getenv('FAMTASTIC_ALLOW_ACQUISITION_REAL_OUTREACH')===FALSE,'exact_dispatch_flags_restored');
$check($executor->run($configPath)['duplicate']&&$mailer->calls===2,'installed_window_replay_no_transport');
$check($quota->usage('2026-10-07',9)['day']===2,'accepted_messages_count_once_with_slots');
$time->now+=3600;$mailer->fail=TRUE;$queue['records']=[['kind'=>'customer','queue_key'=>'beauty-window-'.$nonce.'-3','email'=>'window-'.$nonce.'-3@example.test']];file_put_contents($queuePath,json_encode($queue,JSON_THROW_ON_ERROR));$config['queue_sha256']=hash_file('sha256',$queuePath);$save();
$r=$executor->run($configPath);$check($r['status']==='halted'&&$mailer->calls===3,'uncertain_fake_smtp_halts_clock');
$check((int)$db->select('famtastic_acquisition_dispatch','d')->condition('status','uncertain')->countQuery()->execute()->fetchField()>=1,'uncertain_native_reservation_durable');
$check($executor->run($configPath)['status']==='halted'&&$mailer->calls===3,'uncertain_window_never_retried');
$time->now+=3600;$queue['records']=[['kind'=>'customer','queue_key'=>'beauty-window-'.$nonce.'-4','email'=>'window-'.$nonce.'-4@example.test']];file_put_contents($queuePath,json_encode($queue,JSON_THROW_ON_ERROR));$config['queue_sha256']=hash_file('sha256',$queuePath);$save();$check($executor->run($configPath)['status']==='halted'&&$mailer->calls===3,'later_window_stays_halted');
$time->now=(new DateTimeImmutable('2026-10-07 14:00:00',new DateTimeZone('America/New_York')))->getTimestamp();$check($executor->run('/private/no-such-config')['status']==='outside_window'&&$mailer->calls===3,'off_window_reads_no_queue_or_send');
$check(!str_contains(json_encode($r),'@example.test')&&!str_contains(json_encode($r),'token')&&!str_contains(json_encode($r),$secret),'window_result_has_no_pii_or_secret');
$db->update('famtastic_acquisition_clock')->fields(['status'=>'active','reason'=>''])->condition('id',1)->execute(); // This disposable fixture alone resets its simulated failure latch.
$mailer->fail=FALSE;$time->now=(new DateTimeImmutable('2026-10-08 15:15:00',new DateTimeZone('America/New_York')))->getTimestamp();
$queue['records']=[['kind'=>'customer','queue_key'=>'beauty-window-'.$nonce.'-asap','email'=>'window-'.$nonce.'-asap@example.test']];file_put_contents($queuePath,json_encode($queue,JSON_THROW_ON_ERROR));$config['queue_sha256']=hash_file('sha256',$queuePath);$config['campaign_id']=5;
$config['execution_mode']='asap_initial';$config['starts_on']=$config['ends_on']='2026-10-08';$config['asap_authorization']=['schema'=>'famtastic.acquisition-asap-authorization.v1','approval_ref'=>'synthetic_initial_fifty','issued_at'=>$time->now,'expires'=>$time->now+3300,'local_date'=>'2026-10-08','cap'=>50,'campaign_id'=>5,'owner_authorized'=>TRUE,'reference'=>'synthetic-owner-asap-approval','sha256'=>str_repeat('e',64)];
// Native fixture campaign 5 is created only inside this guarded disposable DB.
if(!$db->select('famtastic_campaign','c')->condition('id',5)->countQuery()->execute()->fetchField())$db->insert('famtastic_campaign')->fields(['id'=>5,'campaign_key'=>'synthetic-asap-acquisition-199','name'=>'Synthetic ASAP fixture','status'=>'draft','created'=>$time->now,'changed'=>$time->now])->execute();
$originalPrepare=$GLOBALS['window_fixture_prepare'];
$GLOBALS['window_fixture_prepare']=static function(array $record,array $binding)use($originalPrepare,$db,$dir,$secret):array{
  $prepared=$originalPrepare($record,$binding);$path=$prepared['packet_path'];$envelope=json_decode((string)file_get_contents($path),TRUE,64,JSON_THROW_ON_ERROR);$packet=$envelope['packet'];
  $db->update('famtastic_prospect')->fields(['campaign'=>'synthetic-asap-acquisition-199'])->condition('id',$packet['prospect_id'])->execute();$packet['campaign_id']=5;foreach(['history_receipt','owner_authorization_receipt'] as $key)$packet[$key]['binding']['campaign_id']=5;
  file_put_contents($path,json_encode(['packet'=>$packet,'signature'=>hash_hmac('sha256',json_encode($packet,JSON_THROW_ON_ERROR),$secret)],JSON_THROW_ON_ERROR));return $prepared;
};$save();
$check($executor->run($configPath,TRUE,TRUE)['status']==='checked'&&$mailer->calls===3,'asap_afternoon_check_config_has_no_transport');
$check($executor->run($configPath)['status']==='outside_window'&&$mailer->calls===3,'routine_clock_cannot_execute_asap_afternoon');
$r=$executor->run($configPath,FALSE,TRUE);$check($r['status']==='complete'&&$r['window_key']==='asap-first-50'&&$mailer->calls===4,'installed_asap_exact_adapter_fake_acceptance');
$check($executor->run($configPath,FALSE,TRUE)['duplicate']&&$mailer->calls===4,'asap_persistent_claim_never_replays_transport');
$check($quota->usedQueue($queue['records'][0]['queue_key'])&&$quota->usage('2026-10-08',15)['day']===1,'asap_accepted_queue_excluded_and_daily_counted');
$time->now+=3301;try{$executor->run($configPath,FALSE,TRUE);$check(FALSE,'asap_expired_authorization_rejected');}catch(RuntimeException){$check($mailer->calls===4,'asap_expired_authorization_rejected');}
$check(getenv('FAMTASTIC_ALLOW_REAL_OUTREACH')==='false'&&getenv('FAMTASTIC_ALLOW_ACQUISITION_REAL_OUTREACH')===FALSE,'asap_dispatch_flags_restored');
$report['status']='passed';$report['checks_count']=count($report['checks']);$report['fake_transport_calls']=$mailer->calls;$report['scope']='Installed native schema/services/compiler/operator/exact adapter with fictional .test rows and disabled network; hosted/capacity/history fixture evidence synthetic; no real SMTP.';
