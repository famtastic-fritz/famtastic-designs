<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Site\Settings;

/** Dedicated clock: private signed queue, fresh audit, exact cap-one sender. */
final class AcquisitionWindowExecutor {
  public function __construct(private readonly TimeInterface $time,private readonly AcquisitionWindowQuota $quota,private readonly AcquisitionSampleSequenceService $sequences,private readonly OperationalLedger $ledger) {}
  public static function sign(array $value): string {
    $secret=(string)getenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET');
    if(strlen($secret)<32)throw new \RuntimeException('acquisition_window_secret_required');
    return hash_hmac('sha256',json_encode($value,JSON_THROW_ON_ERROR),$secret);
  }
  private static function privateFile(string $path): string {
    $private=realpath((string)Settings::get('file_private_path',''));
    $dir=$private?realpath($private.'/acquisition-199'):FALSE;$real=realpath($path);
    if(!$dir||$dir!==$private.'/acquisition-199'||is_link($private.'/acquisition-199')||!$real||$real!==$path||dirname($real)!==$dir||is_link($path)||!is_file($path)||(fileperms($dir)&0077)!==0||(fileperms($path)&0777)!==0600||filesize($path)>4194304)throw new \RuntimeException('acquisition_window_private_file_required');
    return (string)file_get_contents($path);
  }
  public function run(string $path,bool $checkConfig=FALSE,bool $asap=FALSE): array {
    if(PHP_SAPI!=='cli')throw new \RuntimeException('acquisition_window_cli_required');
    // Outside these four start slots, do not read private cohorts or call audits.
    $local=AcquisitionWindowQuota::local($this->time->getCurrentTime());
    if(!$asap&&!$checkConfig&&(!in_array((int)$local->format('G'),AcquisitionWindowQuota::HOURS,TRUE)||(int)$local->format('i')>=5))return ['status'=>'outside_window','inbox_delivery_proved'=>FALSE];
    $envelope=json_decode(self::privateFile($path),TRUE,64,JSON_THROW_ON_ERROR);
    $config=$envelope['config']??[];
    if(!is_array($config)||!hash_equals(self::sign($config),(string)($envelope['signature']??'')))throw new \RuntimeException('acquisition_window_signature_invalid');
    $this->validateMode($config,$this->time->getCurrentTime(),$asap);
    $queueBytes=self::privateFile((string)($config['queue_path']??''));
    if(!hash_equals((string)($config['queue_sha256']??''),hash('sha256',$queueBytes)))throw new \RuntimeException('acquisition_window_queue_drift');
    $queue=json_decode($queueBytes,TRUE,64,JSON_THROW_ON_ERROR);
    if(($queue['schema']??'')!=='famtastic.acquisition-window-queue.v1'||!is_array($queue['records']??NULL))throw new \RuntimeException('acquisition_window_queue_schema_required');
    $this->validateQueue($queue['records']);
    // Only the exact checked-in helper and operator in an archived release may execute.
    $helper=(string)($config['contact_helper_path']??'');$source=dirname(dirname($helper));
    if(!preg_match('#/deploy/famtastic-designs/releases/([a-f0-9]{40})/backend-source/scripts/acquisition-window-contact\.php$#D',$helper,$match)||is_link($helper)||realpath($helper)!==$helper||trim((string)@file_get_contents($source.'/commit.txt'))!==$match[1]||($config['release_commit']??'')!==$match[1]||!hash_equals((string)($config['contact_helper_sha256']??''),(string)hash_file('sha256',$helper)))throw new \RuntimeException('acquisition_window_exact_helper_required');
    $operator=$source.'/scripts/acquisition-exact-operator.php';
    if(!is_file($operator)||is_link($operator)||!hash_equals((string)($config['operator_sha256']??''),(string)hash_file('sha256',$operator)))throw new \RuntimeException('acquisition_window_exact_operator_required');
    $capacityFile=$source.'/scripts/acquisition-window-capacity.php';
    if(!is_file($capacityFile)||is_link($capacityFile)||!hash_equals((string)($config['capacity_helper_sha256']??''),(string)hash_file('sha256',$capacityFile)))throw new \RuntimeException('acquisition_window_exact_capacity_helper_required');
    if(!defined('FAMTASTIC_ACQUISITION_OPERATOR_LIBRARY_ONLY'))define('FAMTASTIC_ACQUISITION_OPERATOR_LIBRARY_ONLY',TRUE);
    require_once $operator;require_once $helper;
    if(!is_callable(['AcquisitionWindowContact','prepare'])||!is_callable(['AcquisitionWindowContact','capacity'])||!is_callable(['AcquisitionWindowContact','check']))throw new \RuntimeException('acquisition_window_helper_contract_required');
    $account=$this->validateConfig($config,$local->format('Y-m-d'),$checkConfig);
    $binding=$this->binding($config,$account['account_sha256']);
    $release=\AcquisitionWindowContact::check($binding);
    if(($release['release_verified']??FALSE)!==TRUE||($release['sender_account_sha256']??'')!==$account['account_sha256']||($release['release_commit']??'')!==$config['release_commit'])throw new \RuntimeException('acquisition_window_hosted_release_unproved');
    if($checkConfig&&$this->quota->clockStatus()==='halted')throw new \RuntimeException('acquisition_clock_halted_manual_review');
    if($checkConfig){$read=\AcquisitionWindowContact::capacity($binding);$budget=$this->capacity($read,$this->time->getCurrentTime(),$account['account_sha256']);return ['status'=>'checked','config_hash'=>hash('sha256',json_encode($config,JSON_THROW_ON_ERROR)),'queue_rows'=>count($queue['records']),'queue_sha256'=>hash('sha256',$queueBytes),'capacity_mode'=>$budget['capacity_mode'],'available_today'=>$budget['available_today'],'available_hour'=>$budget['available_hour'],'release_commit'=>$config['release_commit'],'reservations_created'=>0,'prepared'=>0,'sent'=>0,'inbox_delivery_proved'=>FALSE];}
    return $this->execute($config,$queue['records'],static fn(array $record,array $binding):array=>\AcquisitionWindowContact::prepare($record,$binding),self::exactOperator(...),static fn(array $binding):array=>\AcquisitionWindowContact::capacity($binding),static fn(int $seconds)=>sleep($seconds),$asap);
  }
  /** Injected callables exist for focused no-network proof; production run binds exact files. */
  public function execute(array $config,array $queue,callable $prepare,callable $operator,callable $capacity,?callable $pace=NULL,bool $asap=FALSE): array {
    $now=$this->time->getCurrentTime();$local=AcquisitionWindowQuota::local($now);$date=$local->format('Y-m-d');$hour=(int)$local->format('G');$key=$date.'-'.sprintf('%02d',$hour);
    if(!$asap&&(!in_array($hour,AcquisitionWindowQuota::HOURS,TRUE)||(int)$local->format('i')>=5))return ['status'=>'outside_window','inbox_delivery_proved'=>FALSE];
    $asapExpires=$this->validateMode($config,$now,$asap);if($asap)$key=($config['execution_mode']??'')==='asap_industry'?$config['asap_authorization']['window_key']:AcquisitionWindowQuota::ASAP_KEY;
    $account=$this->validateConfig($config,$date);
    $existing=$this->quota->result($key);
    if($existing['status']!=='absent')return $existing+['duplicate'=>TRUE]; // Never resume a running/interrupted window.
    $this->validateQueue($queue);$records=[];
    foreach($queue as $row){
      if(!$asap&&isset($row['schedule_date'])&&$row['schedule_date']!==$date)continue;
      if(!$asap&&isset($row['schedule_hour'])&&(int)$row['schedule_hour']!==$hour)continue;
      if($this->quota->usedQueue($row['queue_key']))continue;
      $email=mb_strtolower(trim((string)($row['email']??'')));
      if(!filter_var($email,FILTER_VALIDATE_EMAIL)||($row['kind']??'')!=='customer')throw new \RuntimeException('acquisition_window_stored_contact_required');
      $row['recipient_hash']=$this->ledger->contactHash($email);$records[]=$row;
    }
    if(!$records)return ['status'=>'empty','window_key'=>$key,'inbox_delivery_proved'=>FALSE];
    $usage=$this->quota->usage($date,$hour);$limit=min(50-$usage['window'],200-$usage['day']);
    if($limit<=0)return ['status'=>'capacity_exhausted','window_key'=>$key,'inbox_delivery_proved'=>FALSE];
    $records=array_slice($records,0,$limit);
    // This is a freshly derived window binding, not a renewed historical receipt.
    $binding=$this->binding($config,$account['account_sha256']);
    $binding['window_key']=$key;$binding['window_started_at']=$now;$binding['window_expires']=$asapExpires??$local->setTime($hour,59,59)->getTimestamp();
    $binding['sender_account_sha256']=$account['account_sha256'];
    try {
      $this->quota->assertNoInterruptedRun($key);
      $read=$capacity($binding);$available=$this->capacity($read,$this->time->getCurrentTime(),$account['account_sha256']);
      $records=array_slice($records,0,min(count($records),$available['available_today'],$available['available_hour']));
      if(!$records)return ['status'=>'capacity_exhausted','reason'=>'provider_capacity_unavailable','window_key'=>$key,'inbox_delivery_proved'=>FALSE];
      $reservation=$this->quota->reserveWindow($date,$hour,hash('sha256',json_encode($config,JSON_THROW_ON_ERROR)),$records,(int)$config['campaign_id'],$asapExpires,($config['execution_mode']??'')==='asap_industry'?$key:NULL);
      if($reservation['duplicate'])return $this->quota->result($key)+['duplicate'=>TRUE];
      foreach($records as $i=>$record){
        $current=$this->time->getCurrentTime();$clock=AcquisitionWindowQuota::local($current);
        if($clock->format('Y-m-d')!==$date||($asap?$current>=$asapExpires:(int)$clock->format('G')!==$hour))throw new \RuntimeException('acquisition_window_elapsed');
        if($i>0){if($pace)$pace((int)$config['pace_seconds']);$current=$this->time->getCurrentTime();$clock=AcquisitionWindowQuota::local($current);if($clock->format('Y-m-d')!==$date||($asap?$current>=$asapExpires:(int)$clock->format('G')!==$hour))throw new \RuntimeException('acquisition_window_elapsed');}
        $read=$capacity($binding);$available=$this->capacity($read,$this->time->getCurrentTime(),$account['account_sha256']);
        if(min($available['available_today'],$available['available_hour'])<1)throw new \RuntimeException('acquisition_provider_capacity_unavailable');
        // Current native consent/inbound/reply/purchase stops are also reconciled
        // by the helper and then again by the exact adapter immediately before SMTP.
        $prepared=$prepare($record,$binding);
        $path=(string)($prepared['packet_path']??'');
        if($path==='')throw new \RuntimeException('acquisition_window_packet_required');
        $state=$operator('prepare',$path);$id=(int)($state['message_id']??0);
        if(!$id)throw new \RuntimeException('acquisition_window_preparation_failed');
        $this->quota->bindPrepared($reservation['slots'][$i],$id);
        $sent=$operator('dispatch',$path);
        if(($sent['message_status']??'')!=='sent'||($sent['duplicate']??FALSE))throw new \RuntimeException('acquisition_window_dispatch_not_new_acceptance');
        $this->quota->accepted($id);
      }
      $this->quota->finish($key);return $this->quota->result($key)+['duplicate'=>FALSE];
    }catch(\Throwable $error){$this->quota->halt($key,$error->getMessage());return array_replace($this->quota->result($key),['status'=>'halted','inbox_delivery_proved'=>FALSE]);}
  }
  private function capacity(array $value,int $now,string $account): array {
    $mode=$value['capacity_mode']??'';
    $conservative=$mode==='published_limit_with_reserved_budget';
    if(!in_array($mode,['provider_reported_remaining','verified_usage_lower_bound','published_limit_with_reserved_budget'],TRUE)||(!$conservative&&($value['verified']??FALSE)!==TRUE)||($value['sender_account_sha256']??'')!==$account||(int)($value['checked_at']??0)>$now||(int)($value['checked_at']??0)<$now-300||!is_int($value['available_today']??NULL)||!is_int($value['available_hour']??NULL)||$value['available_today']<0||$value['available_hour']<0||empty($value['reference'])||!preg_match('/^[a-f0-9]{64}$/D',(string)($value['sha256']??'')))throw new \RuntimeException('acquisition_fresh_capacity_evidence_required');
    if($conservative&&(($value['budget_kind']??'')!=='conservative_budget'||($value['outside_usage_unknown']??FALSE)!==TRUE||($value['published_mailbox_day_limit']??0)!==500||($value['published_account_hour_limit']??0)!==500||($value['transactional_unobserved_day_reserve']??0)<250||($value['transactional_unobserved_hour_reserve']??0)<400||!is_int($value['observed_today']??NULL)||!is_int($value['observed_hour']??NULL)||$value['observed_today']<0||$value['observed_hour']<0||$value['available_today']>max(0,min(200,500-$value['transactional_unobserved_day_reserve']-$value['observed_today']))||$value['available_hour']>max(0,min(50,500-$value['transactional_unobserved_hour_reserve']-$value['observed_hour']))))throw new \RuntimeException('acquisition_conservative_budget_evidence_required');
    return $value;
  }
  private function validateConfig(array $config,string $date,bool $preview=FALSE): array {
    if(($config['schema']??'')!=='famtastic.acquisition-window-config.v1'||($config['timezone']??'')!==AcquisitionWindowQuota::ZONE||($config['hours']??[])!==AcquisitionWindowQuota::HOURS||($config['window_cap']??0)!==50||($config['day_cap']??0)!==200||($config['enabled']??FALSE)!==TRUE||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',(string)($config['starts_on']??''))||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',(string)($config['ends_on']??''))||(!$preview&&$date<$config['starts_on'])||$date>$config['ends_on']||$config['starts_on']>$config['ends_on']||(int)($config['campaign_id']??0)<1||(int)($config['pace_seconds']??0)<1||(int)$config['pace_seconds']>60)throw new \RuntimeException('acquisition_window_config_invalid');
    $account=$this->sequences->senderAccount();if(!hash_equals($account['account_sha256'],(string)($config['sender_account_sha256']??'')))throw new \RuntimeException('acquisition_window_sender_changed');return $account;
  }
  private function binding(array $config,string $account): array {
    $fields=array_intersect_key($config,array_flip(['timezone','day_cap','window_cap','capacity_mode','transactional_unobserved_day_reserve','transactional_unobserved_hour_reserve']));
    return array_replace((array)($config['binding_config']??[]),$fields,['campaign_id'=>(int)$config['campaign_id'],'release_commit'=>$config['release_commit']??'','sender_account_sha256'=>$account]);
  }
  private function validateMode(array $config,int $now,bool $asap): ?int {
    $mode=$config['execution_mode']??'scheduled';
    if(!$asap){if($mode!=='scheduled'||isset($config['asap_authorization']))throw new \RuntimeException('acquisition_asap_explicit_mode_required');return NULL;}
    $a=$config['asap_authorization']??[];$date=AcquisitionWindowQuota::local($now)->format('Y-m-d');
    if(!in_array($mode,['asap_initial','asap_industry'],TRUE)||($config['campaign_id']??0)!==5||($config['starts_on']??'')!==$date||($config['ends_on']??'')!==$date||($a['schema']??'')!=='famtastic.acquisition-asap-authorization.v1'||($a['campaign_id']??0)!==5||($a['cap']??0)!==50||($a['owner_authorized']??FALSE)!==TRUE||($a['local_date']??'')!==$date||!is_int($a['issued_at']??NULL)||!is_int($a['expires']??NULL)||$a['issued_at']>$now||$a['issued_at']<$now-3600||$a['expires']<=$now||$a['expires']>$a['issued_at']+3600||AcquisitionWindowQuota::local($a['expires'])->format('Y-m-d')!==$date||!preg_match('/^[a-zA-Z0-9:_.-]{8,128}$/D',(string)($a['approval_ref']??''))||empty($a['reference'])||!preg_match('/^[a-f0-9]{64}$/D',(string)($a['sha256']??'')))throw new \RuntimeException('acquisition_asap_authorization_invalid');
    if($mode==='asap_industry'&&(($a['window_key']??'')!=='asap-industry-'.AcquisitionWindowQuota::local($now)->format('Y-m-d-H')||AcquisitionWindowQuota::local($a['expires']-1)->format('Y-m-d-H')!==AcquisitionWindowQuota::local($now)->format('Y-m-d-H')||($a['reference']??'')!=='docs/research/acquisition-199/SAME-DAY-INDUSTRY-AUTHORIZATION-20261006.json'))throw new \RuntimeException('acquisition_industry_asap_binding_required');
    return $a['expires'];
  }
  private function validateQueue(array $queue): void {
    $seen=[];$contacts=[];
    foreach($queue as $row){
      if(!is_array($row)||!preg_match('/^[a-zA-Z0-9_-]{1,80}$/D',(string)($row['queue_key']??''))||isset($seen[$row['queue_key']]))throw new \RuntimeException('acquisition_window_queue_identity_invalid');$seen[$row['queue_key']]=TRUE;
      $email=mb_strtolower(trim((string)($row['email']??'')));
      if(!filter_var($email,FILTER_VALIDATE_EMAIL)||($row['kind']??'')!=='customer'||isset($contacts[$email]))throw new \RuntimeException('acquisition_window_stored_contact_required');$contacts[$email]=TRUE;
      if(isset($row['schedule_date'])&&!preg_match('/^\d{4}-\d{2}-\d{2}$/D',(string)$row['schedule_date']))throw new \RuntimeException('acquisition_window_queue_schedule_invalid');
      if(isset($row['schedule_hour'])&&(!is_int($row['schedule_hour'])||!in_array($row['schedule_hour'],AcquisitionWindowQuota::HOURS,TRUE)))throw new \RuntimeException('acquisition_window_queue_schedule_invalid');
    }
  }
  /** The signed bounded clock authorizes this one exact dispatch, not a worker. */
  private static function exactOperator(string $mode,string $packet): array {
    if($mode!=='dispatch')return \AcquisitionExactOperator::run($mode,$packet);
    $names=['FAMTASTIC_ALLOW_REAL_OUTREACH','FAMTASTIC_ALLOW_ACQUISITION_REAL_OUTREACH'];$before=[];
    foreach($names as $name){$before[$name]=getenv($name);putenv($name.'=1');}
    try{return \AcquisitionExactOperator::run('dispatch',$packet);}
    finally{foreach($before as $name=>$value)putenv($value===FALSE?$name:$name.'='.$value);}
  }
}
