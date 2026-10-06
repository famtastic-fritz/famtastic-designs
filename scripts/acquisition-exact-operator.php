<?php

declare(strict_types=1);

/**
 * Internal Drush operator; no HTTP route or automatic worker.
 * Run php:script with FAMTASTIC_ACQUISITION_OPERATOR_MODE=prepare|status|dispatch
 * and FAMTASTIC_ACQUISITION_OPERATOR_INPUT=<file_private_path>/acquisition-199/<name>.json.
 * Input is 0600 JSON {packet,signature}, HMAC of exact json_encode(packet).
 * packet schema famtastic.acquisition-operator-input.v1: native prospect_id,
 * campaign_id, invitation_key, invitation_expires, issued_at, expires,
 * schedule_start, approval_ref, recipe, creative_approval, history_receipt,
 * release_proof, authorization_basis and that basis's truth/owner/provider fields.
 * Every history/owner/provider receipt must already have checked_at/status/ref/hash
 * and binding {prospect_id,campaign_id,recipient_hash,account_sha256,from} from the
 * actual reviewed runtime audit. Only new invitation/evidence IDs are appended.
 * This script does NOT inspect historical archives or create eligibility facts.
 * Frozen state and signed cap-one manifest stay beside input, 0600; stdout has
 * only IDs, aggregate status and content/manifest hashes. Signing secret stays env.
 */
final class AcquisitionExactOperator {
  private static function secret(): string {
    $secret=(string)getenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET');
    if(strlen($secret)<32)throw new RuntimeException('operator_signing_secret_required');
    return $secret;
  }
  private static function sign(array $value): string {
    return hash_hmac('sha256',json_encode($value,JSON_THROW_ON_ERROR),self::secret());
  }
  private static function read(string $path, string $key): array {
    clearstatcache(TRUE,$path);
    if(is_link($path)||!is_file($path)||(fileperms($path)&0777)!==0600||filesize($path)>4194304)throw new RuntimeException('operator_private_file_required');
    $envelope=json_decode((string)file_get_contents($path),TRUE,64,JSON_THROW_ON_ERROR);
    if(!is_array($envelope[$key]??NULL)||!hash_equals(self::sign($envelope[$key]),(string)($envelope['signature']??'')))throw new RuntimeException('operator_input_signature_invalid');
    return $envelope[$key];
  }
  private static function write(string $path,string $key,array $value): void {
    if(file_exists($path)||is_link($path))throw new RuntimeException('operator_frozen_file_exists');
    $old=umask(0077);
    try {
      $handle=fopen($path,'x');if(!$handle)throw new RuntimeException('operator_private_write_failed');
      try { $bytes=json_encode([$key=>$value,'signature'=>self::sign($value)],JSON_THROW_ON_ERROR);if(fwrite($handle,$bytes)!==strlen($bytes)||!fflush($handle))throw new RuntimeException('operator_private_write_failed'); }
      finally { fclose($handle); }
    } finally { umask($old); }
  }
  public static function run(string $mode,string $input): array {
    if(PHP_SAPI!=='cli'||!in_array($mode,['prepare','status','dispatch'],TRUE))throw new RuntimeException('operator_cli_mode_required');
    $private=realpath((string)\Drupal\Core\Site\Settings::get('file_private_path',''));
    $directory=$private?realpath($private.'/acquisition-199'):FALSE;
    $path=realpath($input);
    if(!$directory||$directory!==$private.'/acquisition-199'||is_link($private.'/acquisition-199')||!$path||$path!==$input||dirname($path)!==$directory||!preg_match('/^[a-zA-Z0-9_-]{1,80}\.json$/D',basename($path))||(fileperms($directory)&0077)!==0)throw new RuntimeException('operator_confined_input_required');
    $packet=self::read($path,'packet');
    if(($packet['schema']??'')!=='famtastic.acquisition-operator-input.v1')throw new RuntimeException('operator_packet_schema_required');
    $base=substr($path,0,-5);$statePath=$base.'.state.json';$manifestPath=$base.'.manifest.json';$lockPath=$base.'.lock';
    if(is_link($lockPath))throw new RuntimeException('operator_private_lock_required');
    $old=umask(0077);try{$lock=fopen($lockPath,'c');}finally{umask($old);}
    if(!$lock||(fileperms($lockPath)&0777)!==0600)throw new RuntimeException('operator_private_lock_required');
    if(!flock($lock,LOCK_EX))throw new RuntimeException('operator_lock_failed');
    try {
      $inputHash=hash('sha256',json_encode($packet,JSON_THROW_ON_ERROR));
      if(file_exists($statePath)) {
        $state=self::read($statePath,'state');
        if(!hash_equals($state['input_hash'],$inputHash))throw new RuntimeException('operator_input_replay_changed');
      }
      elseif($mode!=='prepare')throw new RuntimeException('operator_preparation_required');
      else {
        if(file_exists($manifestPath)||is_link($statePath)||is_link($manifestPath))throw new RuntimeException('operator_partial_prepare_manual_review');
        $db=\Drupal::database();$time=\Drupal::time()->getRequestTime();
        $prospect=$db->select('famtastic_prospect','p')->fields('p',['public_email'])->condition('id',(int)($packet['prospect_id']??0))->execute()->fetchField();
        $email=mb_strtolower(trim((string)$prospect));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('operator_stored_recipient_required');
        $sequences=\Drupal::service('famtastic_pipeline.acquisition_sample_sequences');$account=$sequences->senderAccount();
        $binding=['prospect_id'=>(int)$packet['prospect_id'],'campaign_id'=>(int)$packet['campaign_id'],'recipient_hash'=>\Drupal::service('famtastic_pipeline.operational_ledger')->contactHash($email),'account_sha256'=>$account['account_sha256'],'from'=>$account['from']];
        $basis=\Drupal\famtastic_pipeline\Service\AcquisitionSampleGuard::authorizationBasis($packet);
        $receiptKey=$basis==='owner_authorized_cold_outreach'?'owner_authorization_receipt':'provider_permission_receipt';
        foreach([$receiptKey,'history_receipt'] as $key)if(($packet[$key]['binding']??[])!==$binding)throw new RuntimeException('operator_actual_audit_binding_required');
        $release=$packet['release_proof']??[];
        if(($release['status']??'')!=='owner_reviewed'||empty($release['reference'])||preg_match('/^[a-f0-9]{64}$/D',(string)($release['sha256']??''))!==1)throw new RuntimeException('operator_reviewed_release_required');
        $transaction=$db->startTransaction();
        try {
          $samples=\Drupal::service('famtastic_pipeline.acquisition_samples');
          $invitation=$samples->prepareGeneric((string)$packet['invitation_key'],(int)$packet['prospect_id'],(int)$packet['campaign_id'],(array)$packet['recipe'],(int)$packet['invitation_expires']);
          if($invitation['duplicate']||!$invitation['token'])throw new RuntimeException('operator_existing_invitation_manual_review');
          $row=$db->select('famtastic_acquisition_sample','s')->fields('s')->condition('id',$invitation['id'])->execute()->fetchAssoc();
          $exactBinding=['invitation_id'=>(int)$row['id'],'prospect_id'=>(int)$row['prospect_id'],'campaign_id'=>(int)$row['campaign_id'],'recipient_hash'=>$row['recipient_hash'],'invitation_evidence_hash'=>$row['evidence_hash'],'account_sha256'=>$account['account_sha256'],'from'=>$account['from']];
          $creativeRecord=\Drupal\famtastic_pipeline\Service\AcquisitionSampleGuard::creativeApproval($packet['creative_approval']);
          $authorization=['schema'=>'famtastic.acquisition-generic-authorization.v1','issued_at'=>$packet['issued_at'],'expires'=>$packet['expires'],'binding'=>$exactBinding,'sender'=>$account,$receiptKey=>$packet[$receiptKey],'history_receipt'=>$packet['history_receipt'],'creative_approval'=>$packet['creative_approval'],'content_id'=>$creativeRecord['content_id']??'acquisition-199:beauty_soft_power_generic_d0:v1'];
          foreach([$receiptKey,'history_receipt'] as $key)$authorization[$key]['binding']=$exactBinding;
          if($basis==='owner_authorized_cold_outreach')foreach(['authorization_basis','provider_policy_conflict','recipient_opt_in','provider_permission_proved','owner_authorization_record'] as $key)$authorization[$key]=$packet[$key];
          $samples->authorizeGeneric($invitation['id'],$authorization,self::sign($authorization));
          $stage=$sequences->stageGenericD0($invitation['id'],$email,$invitation['token']);
          $sequences->activate($stage['sequence_id'],(int)$packet['schedule_start'],(string)$packet['approval_ref']);
          $content=$db->select('famtastic_acquisition_message','c')->fields('c')->condition('message_id',$stage['message_ids'][0])->execute()->fetchAssoc();
          $manifest=['schema'=>'famtastic.acquisition-exact-send.v1','transport'=>'native_smtp','cap'=>1,'sequence_id'=>$stage['sequence_id'],'message_id'=>(int)$content['message_id'],'recipient'=>$email,'from'=>$account['from'],'content_id'=>$content['content_id'],'content_hash'=>$content['content_hash'],'qualification_ref'=>hash('sha256',json_encode($authorization,JSON_THROW_ON_ERROR)),'invitation_evidence_hash'=>$row['evidence_hash'],'approval_ref'=>$packet['approval_ref'],'expires'=>$packet['expires'],$receiptKey=>$authorization[$receiptKey],'history_receipt'=>$authorization['history_receipt'],'release_proof'=>$release,'sender_account_sha256'=>$account['account_sha256'],'generic_authorization_hash'=>hash('sha256',json_encode($authorization,JSON_THROW_ON_ERROR))];
          if($basis==='owner_authorized_cold_outreach')foreach(['authorization_basis','provider_policy_conflict','recipient_opt_in','provider_permission_proved'] as $key)$manifest[$key]=$authorization[$key];
          $state=['input_hash'=>$inputHash,'invitation_id'=>$invitation['id'],'sequence_id'=>$stage['sequence_id'],'message_id'=>(int)$content['message_id'],'content_hash'=>$content['content_hash'],'manifest_hash'=>hash('sha256',json_encode($manifest,JSON_THROW_ON_ERROR))];
          self::write($manifestPath,'manifest',$manifest);self::write($statePath,'state',$state);
        }catch(Throwable $error){$transaction->rollBack();throw $error;}
        unset($transaction);
      }
      $manifest=self::read($manifestPath,'manifest');
      if(!hash_equals($state['manifest_hash'],hash('sha256',json_encode($manifest,JSON_THROW_ON_ERROR)))||$state['message_id']!==$manifest['message_id'])throw new RuntimeException('operator_frozen_manifest_drift');
      $dispatch=NULL;
      if($mode==='dispatch')$dispatch=\Drupal::service('famtastic_pipeline.acquisition_sample_exact_sender')->dispatch($manifest,self::sign($manifest));
      $db=\Drupal::database();
      $message=$db->select('famtastic_email_message','m')->fields('m',['status'])->condition('id',$state['message_id'])->execute()->fetchField();
      $sequence=$db->select('famtastic_acquisition_sequence','s')->fields('s',['status'])->condition('id',$state['sequence_id'])->execute()->fetchField();
      if(!$message||!$sequence)throw new RuntimeException('operator_partial_prepare_manual_review');
      return ['schema'=>'famtastic.acquisition-operator-result.v1','mode'=>$mode,'message_id'=>$state['message_id'],'sequence_id'=>$state['sequence_id'],'invitation_id'=>$state['invitation_id'],'content_hash'=>$state['content_hash'],'manifest_hash'=>$state['manifest_hash'],'message_status'=>$message,'sequence_status'=>$sequence,'duplicate'=>(bool)($dispatch['duplicate']??FALSE),'inbox_delivery_proved'=>FALSE];
    }finally{flock($lock,LOCK_UN);fclose($lock);}
  }
}
if(!defined('FAMTASTIC_ACQUISITION_OPERATOR_LIBRARY_ONLY')){
  try { print json_encode(AcquisitionExactOperator::run((string)getenv('FAMTASTIC_ACQUISITION_OPERATOR_MODE'),(string)getenv('FAMTASTIC_ACQUISITION_OPERATOR_INPUT')),JSON_THROW_ON_ERROR)."\n"; }
  catch(Throwable $error){fwrite(STDERR,json_encode(['schema'=>'famtastic.acquisition-operator-result.v1','status'=>'failed_closed','error_type'=>get_class($error),'error_code'=>preg_match('/^[a-z][a-z0-9_:]{1,100}$/D',$error->getMessage())?$error->getMessage():'operator_failure_requires_private_review'],JSON_THROW_ON_ERROR)."\n");exit(1);}
}
