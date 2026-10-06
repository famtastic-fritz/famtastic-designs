<?php

declare(strict_types=1);

/** Installed Drupal/SQLite test; fake adapter transport, no live runtime allowed. */
$sandbox=realpath((string)getenv('ACQUISITION_DRUPAL_SANDBOX'))?:'';
$db=\Drupal::database();$options=$db->getConnectionOptions();
if(!preg_match('#/famtastic-acquisition-drupal\.[a-zA-Z0-9]{6}$#D',$sandbox)||realpath(__DIR__)!==$sandbox.'/scripts'||realpath(\Drupal::root())!==$sandbox.'/backend/web'||($options['driver']??'')!=='sqlite'||realpath((string)$options['database'])!==$sandbox.'/backend/web/sites/default/files/.ht.sqlite'||function_exists('curl_exec')||function_exists('mail')||ini_get('allow_url_fopen')!=='0')throw new RuntimeException('Use isolated acquisition runtime only.');
define('FAMTASTIC_ACQUISITION_OPERATOR_LIBRARY_ONLY',TRUE);require __DIR__.'/acquisition-exact-operator.php';
$report=['schema'=>'famtastic.acquisition-operator-proof.v1','status'=>'running','classification'=>'local_synthetic','checks'=>[],'actual_sends'=>FALSE,'production_deployment'=>FALSE];
register_shutdown_function(static function()use(&$report):void{file_put_contents((string)getenv('ACQUISITION_DRUPAL_EVIDENCE').'/operator-evidence.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");});
$check=static function(bool $value,string $name)use(&$report):void{$report['checks'][$name]=$value;if(!$value){$report['status']='failed';throw new RuntimeException($name);}print 'PASS: '.$name."\n";};
$GLOBALS['config']['smtp.settings']=['smtp_on'=>TRUE,'smtp_host'=>'smtp.synthetic.invalid','smtp_port'=>587,'smtp_username'=>'hello@famtasticdesigns.com','smtp_from'=>'hello@famtasticdesigns.com','smtp_protocol'=>'tls'];
$GLOBALS['config']['famtastic_pipeline.settings']=array_replace($GLOBALS['config']['famtastic_pipeline.settings']??[],['outreach_postal_address'=>'123 Fictional Test Street, Example City, FL 00000']);
\Drupal::service('config.factory')->reset('smtp.settings')->reset('famtastic_pipeline.settings');
$bundle=$sandbox.'/backend/private/generic-d0';
new \Drupal\Core\Site\Settings(array_replace(\Drupal\Core\Site\Settings::getAll(),['famtastic_acquisition_bundle_root'=>$bundle,'famtastic_acquisition_bundle_sha256'=>hash_file('sha256',$bundle.'/manifest.json')]));
$now=\Drupal::time()->getRequestTime();$key=bin2hex(random_bytes(4));$email='operator-'.$key.'@example.test';
$prospect=\Drupal::entityTypeManager()->getStorage('famtastic_prospect')->create(['business_name'=>'Juniper Operator Proof','business_category'=>'Beauty, Hair Styling & Braiding','public_email'=>$email,'campaign'=>'acquisition-199','source'=>'local_synthetic','status'=>'new']);$prospect->save();
$campaign=(int)$db->select('famtastic_campaign','c')->fields('c',['id'])->condition('campaign_key','acquisition-199')->execute()->fetchField();
$account=\Drupal::service('famtastic_pipeline.acquisition_sample_sequences')->senderAccount();
$binding=['prospect_id'=>(int)$prospect->id(),'campaign_id'=>$campaign,'recipient_hash'=>\Drupal::service('famtastic_pipeline.operational_ledger')->contactHash($email),'account_sha256'=>$account['account_sha256'],'from'=>$account['from']];
$ownerBytes=(string)file_get_contents($sandbox.'/backend/private/owner-cold-source-record.json');
$receipt=['status'=>'owner_reviewed','reference'=>'synthetic-history-only-no-actual-cohort','sha256'=>str_repeat('c',64),'checked_at'=>$now,'binding'=>$binding];
$approval=['reference'=>'docs/research/acquisition-199/CREATIVE-APPROVAL.json','sha256'=>hash_file('sha256',$bundle.'/docs/research/acquisition-199/CREATIVE-APPROVAL.json')];
$artifact='marketing/campaigns/acquisition-199/generic-review/beauty-template.html';
$recipe=['id'=>'beauty_soft_power_acquisition','version'=>1,'niche'=>'beauty_hair','artifact_path'=>$artifact,'sha256'=>hash_file('sha256',$bundle.'/'.$artifact),'review'=>['status'=>'approved_campaign_artifact','approval_record'=>$approval],'recipe_ref'=>['owner'=>'component-studio','id'=>'beauty_soft_power_acquisition','version'=>1,'status'=>'import_request_pending']];
$packet=['schema'=>'famtastic.acquisition-operator-input.v1','prospect_id'=>(int)$prospect->id(),'campaign_id'=>$campaign,'invitation_key'=>'synthetic:operator:'.$key,'invitation_expires'=>$now+86400,'issued_at'=>$now,'expires'=>$now+3600,'schedule_start'=>$now,'approval_ref'=>'synthetic-operator-'.$key,'recipe'=>$recipe,'creative_approval'=>$approval,'history_receipt'=>$receipt+['classification'=>'actual_native_history_reconciled','coverage_complete'=>TRUE,'eligible_for_new_outreach'=>TRUE,'known_stop_reasons'=>[]],'release_proof'=>['status'=>'owner_reviewed','reference'=>'synthetic-release-not-hosted','sha256'=>str_repeat('e',64)],'authorization_basis'=>'owner_authorized_cold_outreach','provider_policy_conflict'=>TRUE,'recipient_opt_in'=>FALSE,'provider_permission_proved'=>FALSE,'owner_authorization_receipt'=>array_replace($receipt,['reference'=>'docs/research/acquisition-199/OWNER-COLD-SEND-AUTHORIZATION.json','sha256'=>hash('sha256',$ownerBytes),'approved_by'=>'Fritz Medine']),'owner_authorization_record'=>$ownerBytes];
$secret=str_repeat('synthetic-operator-key-',3);putenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET='.$secret);
$directory=$sandbox.'/backend/private/acquisition-199';if(!is_dir($directory))mkdir($directory,0700);chmod($directory,0700);
$input=$directory.'/proof-'.$key.'.json';
$save=static function(array $p)use($input,$secret):void{file_put_contents($input,json_encode(['packet'=>$p,'signature'=>hash_hmac('sha256',json_encode($p,JSON_THROW_ON_ERROR),$secret)],JSON_THROW_ON_ERROR));chmod($input,0600);};
$save($packet);
try{AcquisitionExactOperator::run('prepare',$sandbox.'/backend/private/owner-cold-source-record.json');$check(FALSE,'operator_confines_input');}catch(RuntimeException){$check(TRUE,'operator_confines_input');}
chmod($input,0644);try{AcquisitionExactOperator::run('prepare',$input);$check(FALSE,'operator_requires_0600');}catch(RuntimeException){$check(TRUE,'operator_requires_0600');}chmod($input,0600);
$raw=json_decode((string)file_get_contents($input),TRUE);$raw['signature']=str_repeat('0',64);file_put_contents($input,json_encode($raw));try{AcquisitionExactOperator::run('prepare',$input);$check(FALSE,'operator_rejects_unsigned_input');}catch(RuntimeException){$check(TRUE,'operator_rejects_unsigned_input');}$save($packet);
$link=$directory.'/link-'.$key.'.json';symlink($input,$link);try{AcquisitionExactOperator::run('prepare',$link);$check(FALSE,'operator_rejects_symlink_input');}catch(RuntimeException){$check(TRUE,'operator_rejects_symlink_input');}unlink($link);
$bad=$packet;$bad['history_receipt']['binding']['prospect_id']++;$save($bad);try{AcquisitionExactOperator::run('prepare',$input);$check(FALSE,'operator_does_not_relabel_wrong_history');}catch(RuntimeException){$check(TRUE,'operator_does_not_relabel_wrong_history');}$save($packet);
$result=AcquisitionExactOperator::run('prepare',$input);$check($result['message_status']==='held'&&$result['sequence_status']==='active','operator_prepares_one_native_d0_without_sending');
$check((int)$db->select('famtastic_email_message','m')->condition('prospect_id',(int)$prospect->id())->countQuery()->execute()->fetchField()===1,'operator_exactly_one_message');
$check(AcquisitionExactOperator::run('prepare',$input)['message_id']===$result['message_id'],'operator_prepare_replay_no_second_invitation');
$stdout=json_encode(AcquisitionExactOperator::run('status',$input));$check(!str_contains($stdout,$email)&&!str_contains($stdout,'token')&&!str_contains($stdout,'recipient_hash')&&!str_contains($stdout,$secret),'operator_status_no_pii_or_secret');
$bad=$packet;$bad['approval_ref'].='changed';$save($bad);try{AcquisitionExactOperator::run('prepare',$input);$check(FALSE,'operator_changed_input_replay_rejected');}catch(RuntimeException){$check(TRUE,'operator_changed_input_replay_rejected');}$save($packet);
$manifestPath=substr($input,0,-5).'.manifest.json';$manifestBytes=(string)file_get_contents($manifestPath);$altered=json_decode($manifestBytes,TRUE);$altered['manifest']['content_hash']=str_repeat('0',64);file_put_contents($manifestPath,json_encode($altered));try{AcquisitionExactOperator::run('status',$input);$check(FALSE,'operator_frozen_manifest_tamper_rejected');}catch(RuntimeException){$check(TRUE,'operator_frozen_manifest_tamper_rejected');}file_put_contents($manifestPath,$manifestBytes);
$mailer=new class extends \Drupal\famtastic_pipeline\Service\OutreachMailer {
 public int $calls=0;
 public function __construct(){}
 public function fromAddress():string{return 'hello@famtasticdesigns.com';}
 public function assertAcquisitionTransportAllowed():void{}
 public function sendFrozenAcquisition(string $to,array $snapshot,string $unsubscribeUrl):string{if(!str_ends_with($to,'@example.test'))throw new RuntimeException('Synthetic recipient only.');$this->calls++;return '<synthetic-operator-not-real@example.test>';}
};
$exact=new \Drupal\famtastic_pipeline\Service\AcquisitionSampleExactAdapter($db,\Drupal::time(),\Drupal::service('famtastic_pipeline.operational_ledger'),\Drupal::service('famtastic_pipeline.acquisition_sample_sequences'),$mailer);
\Drupal::getContainer()->set('famtastic_pipeline.acquisition_sample_exact_sender',$exact);
$check(!AcquisitionExactOperator::run('dispatch',$input)['inbox_delivery_proved']&&$mailer->calls===1,'operator_fake_exact_dispatch_once');
$check(AcquisitionExactOperator::run('dispatch',$input)['duplicate']&&$mailer->calls===1,'operator_accepted_replay_no_second_transport');
$db->update('famtastic_acquisition_dispatch')->fields(['status'=>'uncertain'])->condition('message_id',$result['message_id'])->execute();
try{AcquisitionExactOperator::run('dispatch',$input);$check(FALSE,'operator_uncertain_never_retries');}catch(RuntimeException){$check($mailer->calls===1,'operator_uncertain_never_retries');}
$report['status']='passed';$report['source_sha256']=hash_file('sha256',__DIR__.'/acquisition-exact-operator.php');$report['evidence_scope']='Actual operator/native services in disposable SQLite; owner source bytes actual, row/history/release/fake transport synthetic. No recipient outputs.';
