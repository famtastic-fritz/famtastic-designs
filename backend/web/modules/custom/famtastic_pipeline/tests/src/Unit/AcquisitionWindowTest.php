<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\famtastic_pipeline\Service\{AcquisitionWindowSchema,AcquisitionWindowQuota,AcquisitionWindowExecutor,AcquisitionWindowSchedule,AcquisitionSampleSchema,AcquisitionSampleSequenceService,OperationalLedger};
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;
require_once dirname(__DIR__,3).'/famtastic_pipeline.install';

/** Real SQLite reservations and controlled callbacks; never a provider connection. */
final class AcquisitionWindowTest extends UnitTestCase {
  private Connection $db;private AcquisitionWindowQuota $quota;private AcquisitionWindowExecutor $executor;private OperationalLedger $ledger;
  private int $now;private array $config;private int $prepared=0;private int $sent=0;private array $states=[];
  protected function setUp():void {
    parent::setUp();$this->at('2026-10-07 09:00:00');
    $options=['database'=>':memory:','prefix'=>'','driver'=>'sqlite','namespace'=>'Drupal\\sqlite\\Driver\\Database\\sqlite'];$this->db=new Connection(Connection::open($options),$options);
    $schemas=AcquisitionWindowSchema::tables()+AcquisitionSampleSchema::tables()+_famtastic_pipeline_automation_schema();
    foreach(AcquisitionWindowSchema::tables() as $table=>$definition)$this->db->schema()->createTable($table,$definition);
    foreach(['famtastic_acquisition_message','famtastic_acquisition_dispatch','famtastic_email_message','famtastic_event'] as $table)$this->db->schema()->createTable($table,$schemas[$table]);
    $time=$this->createMock(TimeInterface::class);$time->method('getCurrentTime')->willReturnCallback(fn():int=>$this->now);$time->method('getRequestTime')->willReturnCallback(fn():int=>$this->now);
    $smtp=$this->createMock(\Drupal\Core\Config\ImmutableConfig::class);$smtp->method('get')->willReturnCallback(static fn(string $key):mixed=>['smtp_on'=>TRUE,'smtp_host'=>'smtp.synthetic.invalid','smtp_port'=>587,'smtp_username'=>'sender@example.test','smtp_from'=>'sender@example.test','smtp_protocol'=>'tls'][$key]??NULL);
    $factory=$this->createMock(\Drupal\Core\Config\ConfigFactoryInterface::class);$factory->method('get')->willReturn($smtp);
    $sequences=new AcquisitionSampleSequenceService($this->db,$time,new OperationalLedger($this->db,$time),$factory);$this->ledger=new OperationalLedger($this->db,$time);
    $this->quota=new AcquisitionWindowQuota($this->db,$time);$this->executor=new AcquisitionWindowExecutor($time,$this->quota,$sequences,$this->ledger);
    $this->config=['schema'=>'famtastic.acquisition-window-config.v1','enabled'=>TRUE,'timezone'=>'America/New_York','hours'=>[9,10,11,12],'window_cap'=>50,'day_cap'=>200,'starts_on'=>'2026-10-01','ends_on'=>'2026-12-31','campaign_id'=>1,'sender_account_sha256'=>$sequences->senderAccount()['account_sha256'],'pace_seconds'=>1,'release_commit'=>str_repeat('a',40)];
  }
  private function at(string $clock):void{$this->now=(new \DateTimeImmutable($clock,new \DateTimeZone('America/New_York')))->getTimestamp();}
  private function queue(int $n):array{return array_map(static fn(int $i):array=>['queue_key'=>'beauty-fixture-'.$i,'kind'=>'customer','email'=>'fixture-'.$i.'@example.test'],range(1,$n));}
  private function capacity():array{return ['capacity_mode'=>'published_limit_with_reserved_budget','budget_kind'=>'conservative_budget','outside_usage_unknown'=>TRUE,'sender_account_sha256'=>$this->config['sender_account_sha256'],'checked_at'=>$this->now,'available_today'=>200,'available_hour'=>50,'reference'=>'synthetic-budget','sha256'=>str_repeat('b',64),'published_mailbox_day_limit'=>500,'published_account_hour_limit'=>500,'transactional_unobserved_day_reserve'=>250,'transactional_unobserved_hour_reserve'=>400,'observed_today'=>0,'observed_hour'=>0];}
  private function message(string $email, int $day=0, bool $outside=FALSE):int {
    $id=(int)$this->db->insert('famtastic_email_message')->fields(['message_key'=>'fixture:'.bin2hex(random_bytes(8)),'recipient_hash'=>$this->ledger->contactHash($email),'recipient_address'=>$email,'campaign_id'=>$this->config['campaign_id'],'prospect_id'=>1,'template_key'=>AcquisitionSampleSequenceService::MESSAGE_KIND,'template_version'=>1,'subject'=>'Fixture','body_snapshot'=>'Fixture','status'=>$outside?'sent':'held','provider'=>'smtp','sent_at'=>$outside?$this->now:NULL,'tracking_key'=>bin2hex(random_bytes(24)),'unsubscribe_key'=>bin2hex(random_bytes(24)),'created'=>$this->now,'changed'=>$this->now])->execute();
    $this->db->insert('famtastic_acquisition_message')->fields(['message_id'=>$id,'invitation_id'=>$id,'day'=>$day,'content_id'=>'fixture:'.$day,'content_hash'=>str_repeat('c',64),'draft_hash'=>str_repeat('d',64),'snapshot'=>'{}'])->execute();return $id;
  }
  private function prepare(array $record,array $binding):array { $this->assertSame($this->config['campaign_id'],$binding['campaign_id']);$this->assertSame($this->config['sender_account_sha256'],$binding['sender_account_sha256']);$this->prepared++;$path='/synthetic/private/'.$record['queue_key'].'.json';$this->states[$path]=['message_id'=>$this->message($record['email'])];return ['packet_path'=>$path]; }
  private function operator(string $mode,string $path):array {
    $state=$this->states[$path];if($mode==='prepare')return $state;
    $tx=$this->db->startTransaction();try{$this->quota->reserveMessage($state['message_id']);}catch(\Throwable $e){$tx->rollBack();throw $e;}unset($tx);
    $this->quota->assertTransportWindow($state['message_id']);$this->sent++;
    $this->db->update('famtastic_email_message')->fields(['status'=>'sent','sent_at'=>$this->now])->condition('id',$state['message_id'])->execute();return $state+['message_status'=>'sent','duplicate'=>FALSE];
  }
  private function executeWindow(array $queue,?callable $dispatch=NULL,?callable $capacity=NULL,?callable $pace=NULL):array{return $this->executor->execute($this->config,$queue,fn(array $r,array $b):array=>$this->prepare($r,$b),$dispatch??fn(string $m,string $p):array=>$this->operator($m,$p),$capacity??fn(array $b):array=>$this->capacity(),$pace??static fn(int $s)=>NULL);}
  private function asapConfig():void {
    $this->at('2026-10-07 15:15:00');$this->config['campaign_id']=5;$this->config['execution_mode']='asap_initial';$this->config['starts_on']=$this->config['ends_on']='2026-10-07';
    $this->config['asap_authorization']=['schema'=>'famtastic.acquisition-asap-authorization.v1','approval_ref'=>'synthetic_first_fifty','issued_at'=>$this->now,'expires'=>$this->now+3300,'local_date'=>'2026-10-07','campaign_id'=>5,'cap'=>50,'owner_authorized'=>TRUE,'reference'=>'synthetic-owner-approval','sha256'=>str_repeat('e',64)];
  }
  private function executeAsap(array $queue,?callable $dispatch=NULL,?callable $pace=NULL):array{return $this->executor->execute($this->config,$queue,fn(array $r,array $b):array=>$this->prepare($r,$b),$dispatch??fn(string $m,string $p):array=>$this->operator($m,$p),fn(array $b):array=>$this->capacity(),$pace??static fn(int $s)=>NULL,TRUE);}
  private function industryAsap(string $clock):void {
    $this->at($clock);$date=AcquisitionWindowQuota::local($this->now)->format('Y-m-d');$hour=AcquisitionWindowQuota::local($this->now)->format('H');
    $this->config['campaign_id']=5;$this->config['execution_mode']='asap_industry';$this->config['starts_on']=$this->config['ends_on']=$date;
    $this->config['asap_authorization']=['schema'=>'famtastic.acquisition-asap-authorization.v1','approval_ref'=>'owner_industry_today','issued_at'=>$this->now,'expires'=>min($this->now+3300,AcquisitionWindowQuota::local($this->now)->setTime((int)$hour,59,59)->getTimestamp()),'local_date'=>$date,'campaign_id'=>5,'cap'=>50,'owner_authorized'=>TRUE,'reference'=>'docs/research/acquisition-199/SAME-DAY-INDUSTRY-AUTHORIZATION-20261006.json','sha256'=>str_repeat('e',64),'window_key'=>'asap-industry-'.$date.'-'.$hour];
  }
  private function todayIndustryAsap(string $clock):void {
    $this->industryAsap($clock);
    $reference='docs/research/acquisition-199/TODAY-250-AUTHORIZATION-20261006.json';$sha='09fd8bdd36350d24bc6c9d354816e448573ce11c91bbb4b538550649293ee9a5';
    $this->config['today_exception']=['schema'=>'famtastic.acquisition-today-customer-target.v1','local_date'=>'2026-10-06','timezone'=>AcquisitionWindowQuota::ZONE,'customer_target'=>250,'shared_acquisition_cap'=>251,'existing_owned_probe_count'=>1,'window_cap'=>50,'normal_day_cap_after_today'=>200,'owner_authorized'=>TRUE,'approved_by'=>'Fritz Medine','sender'=>'hello@famtasticdesigns.com','reference'=>$reference,'sha256'=>$sha];
    $this->config['asap_authorization']['reference']=$reference;$this->config['asap_authorization']['sha256']=$sha;
  }
  public function testIndustryImmediateHoursShareDailyCapAndExcludePriorAcceptances():void {
    $this->config['campaign_id']=5;$this->at('2026-10-07 10:00:00');for($i=0;$i<51;$i++)$this->message('prior-'.$i.'@example.test',0,TRUE);
    $queue=$this->queue(900);
    foreach(['17:15:00','18:00:00','19:00:00'] as $clock){$this->industryAsap('2026-10-07 '.$clock);$r=$this->executeAsap($queue);$this->assertSame('complete',$r['status']);$this->assertLessThanOrEqual(50,$r['counts']['accepted']);$this->assertTrue($this->executeAsap($queue)['duplicate']);}
    $this->assertSame(149,$this->sent);$this->assertSame(200,$this->quota->usage('2026-10-07',19)['day']);$this->assertSame(149,$this->prepared);
    $this->industryAsap('2026-10-07 20:00:00');$this->assertSame('capacity_exhausted',$this->executeAsap($queue)['status']);$this->assertSame(149,$this->sent);
  }
  public function testIndustryImmediateRejectsWrongHourOrCrossHourExpiry():void {
    $this->industryAsap('2026-10-07 17:15:00');$this->config['asap_authorization']['window_key']='asap-industry-2026-10-07-18';
    try{$this->executeAsap($this->queue(1));$this->fail('Wrong-hour window accepted');}catch(\RuntimeException $e){$this->assertSame('acquisition_industry_asap_binding_required',$e->getMessage());}
    $this->config['asap_authorization']['window_key']='asap-industry-2026-10-07-17';$this->config['asap_authorization']['expires']=$this->now+3300;
    try{$this->executeAsap($this->queue(1));$this->fail('Cross-hour window accepted');}catch(\RuntimeException $e){$this->assertSame('acquisition_industry_asap_binding_required',$e->getMessage());}$this->assertSame(0,$this->sent);
  }
  public function testSignedTodayCustomerTargetReaches251ThenTomorrowReturnsTo200():void {
    $this->at('2026-10-06 09:00:00');$this->config['campaign_id']=5;
    for($i=0;$i<199;$i++)$this->message('customer-before-today-'.$i.'@example.test',0,TRUE);
    $this->message('owned-probe-before-today@example.test',0,TRUE);
    $this->todayIndustryAsap('2026-10-06 17:15:00');$first=$this->executeAsap($this->queue(50));
    $this->assertSame('complete',$first['status']);$this->assertSame(50,$first['counts']['accepted']);$this->assertSame(250,$this->quota->usage('2026-10-06',17)['day']);$this->assertSame(50,$this->quota->usage('2026-10-06',17)['window']);
    $this->todayIndustryAsap('2026-10-06 18:00:00');$remainder=[['queue_key'=>'today-final-customer','kind'=>'customer','email'=>'today-final-customer@example.test']];$second=$this->executeAsap($remainder);
    $this->assertSame('complete',$second['status']);$this->assertSame(1,$second['counts']['accepted']);$this->assertSame(251,$this->quota->usage('2026-10-06',18)['day']);$this->assertSame(51,$this->sent);
    $this->todayIndustryAsap('2026-10-06 19:00:00');$blocked=$this->executeAsap([['queue_key'=>'today-over-target','kind'=>'customer','email'=>'today-over-target@example.test']]);
    $this->assertSame('capacity_exhausted',$blocked['status']);$this->assertSame(51,$this->sent);$this->assertSame(251,$this->quota->usage('2026-10-06',19)['day']);$this->assertSame('active',$this->quota->clockStatus());

    $this->at('2026-10-07 09:00:00');$this->config['execution_mode']='scheduled';$this->config['starts_on']=$this->config['ends_on']='2026-10-07';unset($this->config['asap_authorization'],$this->config['today_exception']);
    $tomorrow=array_map(static fn(int $i):array=>['queue_key'=>'tomorrow-customer-'.$i,'kind'=>'customer','email'=>'tomorrow-customer-'.$i.'@example.test'],range(1,200));
    foreach([9,10,11,12] as $hour){$this->at('2026-10-07 '.sprintf('%02d',$hour).':00:00');$result=$this->executeWindow($tomorrow);$this->assertSame('complete',$result['status']);$this->assertSame(50,$result['counts']['accepted']);}
    $this->assertSame(251,$this->sent);$this->assertSame(200,$this->quota->usage('2026-10-07',12)['day']);
  }
  public function testTodayCustomerTargetExceptionRejectsRoutineOtherDateAndTampering():void {
    $this->todayIndustryAsap('2026-10-06 17:15:00');$base=$this->config['today_exception'];
    foreach([
      'wrong_schema'=>static function(array &$x):void{$x['schema']='other';},
      'wrong_target'=>static function(array &$x):void{$x['customer_target']=251;},
      'wrong_shared_cap'=>static function(array &$x):void{$x['shared_acquisition_cap']=252;},
      'wrong_probe_count'=>static function(array &$x):void{$x['existing_owned_probe_count']=0;},
      'wrong_owner'=>static function(array &$x):void{$x['owner_authorized']=FALSE;},
      'wrong_proof_hash'=>static function(array &$x):void{$x['sha256']=str_repeat('f',64);},
    ] as $case=>$mutate){$this->todayIndustryAsap('2026-10-06 17:15:00');$mutate($this->config['today_exception']);try{$this->executeAsap($this->queue(1));$this->fail('Tampered today exception accepted: '.$case);}catch(\RuntimeException $error){$this->assertSame('acquisition_today_exception_invalid',$error->getMessage(),$case);}$this->assertSame(0,$this->prepared);$this->assertSame(0,$this->sent);}

    $this->todayIndustryAsap('2026-10-06 17:15:00');$this->config['asap_authorization']['sha256']=str_repeat('f',64);
    try{$this->executeAsap($this->queue(1));$this->fail('Different ASAP proof accepted');}catch(\RuntimeException $error){$this->assertSame('acquisition_industry_asap_binding_required',$error->getMessage());}
    $this->todayIndustryAsap('2026-10-07 17:15:00');
    try{$this->executeAsap($this->queue(1));$this->fail('Previous-date exception accepted');}catch(\RuntimeException $error){$this->assertSame('acquisition_today_exception_invalid',$error->getMessage());}
    $this->todayIndustryAsap('2026-10-06 09:00:00');$this->config['execution_mode']='scheduled';unset($this->config['asap_authorization']);
    try{$this->executeWindow($this->queue(1));$this->fail('Routine exception accepted');}catch(\RuntimeException $error){$this->assertSame('acquisition_today_exception_invalid',$error->getMessage());}
    foreach([
      ['2026-10-07',5,'asap-industry-2026-10-07-17',251],
      ['2026-10-06',1,'asap-industry-2026-10-06-17',251],
      ['2026-10-06',5,'asap-industry-2026-10-06-17',252],
      ['2026-10-06',5,NULL,251],
    ] as [$date,$campaign,$key,$cap]){try{$this->quota->reserveWindow($date,17,str_repeat('a',64),[],(int)$campaign,$this->now+600,$key,(int)$cap);$this->fail('Quota accepted an unauthorized shared cap');}catch(\RuntimeException $error){$this->assertSame('acquisition_shared_day_cap_invalid',$error->getMessage());}}
    $this->assertSame(0,$this->prepared);$this->assertSame(0,$this->sent);
  }
  public function testIndustryImmediateFailureHaltsNextHourAndKeepsReservedSlots():void {
    $this->industryAsap('2026-10-07 17:15:00');$r=$this->executeAsap($this->queue(3),function(string $m,string $p):array{if($m==='dispatch')throw new \RuntimeException('synthetic_uncertain');return $this->operator($m,$p);});$this->assertSame('halted',$r['status']);$this->assertSame(3,$this->quota->usage('2026-10-07',17)['day']);
    $this->industryAsap('2026-10-07 18:00:00');$this->assertSame('halted',$this->executeAsap($this->queue(5))['status']);$this->assertSame(0,$this->sent);
  }
  public function testAsapFirstFiftyOffHoursIsOneTimeAndExcludedFromRoutineNextDay():void {
    $this->asapConfig();$queue=$this->queue(99);$r=$this->executeAsap($queue);$this->assertSame('complete',$r['status']);$this->assertSame('asap-first-50',$r['window_key']);$this->assertSame(50,$r['counts']['accepted']);$this->assertSame(50,$this->quota->usage('2026-10-07',15)['day']);$this->assertSame(50,$this->quota->usage('2026-10-07',15)['window']);
    $this->assertTrue($this->executeAsap($queue)['duplicate']);$this->assertSame(50,$this->sent);
    unset($this->config['asap_authorization']);$this->config['execution_mode']='scheduled';$this->config['ends_on']='2026-10-08';$this->at('2026-10-08 09:00:00');$r=$this->executeWindow($queue);$this->assertSame('complete',$r['status']);$this->assertSame(49,$r['counts']['accepted']);$this->assertSame(99,$this->sent);
  }
  public function testAsapCannotBeExecutedByRoutineClock():void {
    $this->asapConfig();$this->assertSame('outside_window',$this->executeWindow($this->queue(1))['status']);$this->assertSame(0,$this->sent);
  }
  public function testAsapMissingOrWrongCampaignAuthorizationFailsBeforePreparation():void {
    $this->asapConfig();$this->config['asap_authorization']['campaign_id']=1;try{$this->executeAsap($this->queue(1));$this->fail('Wrong campaign authorized');}catch(\RuntimeException $e){$this->assertSame('acquisition_asap_authorization_invalid',$e->getMessage());}$this->assertSame(0,$this->prepared);
  }
  public function testAsapOldDateAndOverHourExpiryFailClosed():void {
    $this->asapConfig();$this->config['asap_authorization']['expires']=$this->now+3601;try{$this->executeAsap($this->queue(1));$this->fail('Long-lived exception authorized');}catch(\RuntimeException $e){$this->assertSame('acquisition_asap_authorization_invalid',$e->getMessage());}
    $this->config['asap_authorization']['expires']=$this->now+3300;$this->config['asap_authorization']['local_date']='2026-10-06';try{$this->executeAsap($this->queue(1));$this->fail('Old date authorized');}catch(\RuntimeException $e){$this->assertSame('acquisition_asap_authorization_invalid',$e->getMessage());}$this->assertSame(0,$this->sent);
  }
  public function testAsapExpiryDuringPacingStopsAndNeverResumes():void {
    $this->asapConfig();$r=$this->executeAsap($this->queue(2),NULL,function(int $n):void{$this->now+=3300;});$this->assertSame('halted',$r['status']);$this->assertSame(1,$this->sent);$this->assertSame(2,$this->quota->usage('2026-10-07',16)['day']);
  }
  public function testAsapFailureRetainsSlotsAndHaltsRoutineFutureRuns():void {
    $this->asapConfig();$r=$this->executeAsap($this->queue(2),function(string $mode,string $path):array{if($mode==='dispatch')throw new \RuntimeException('synthetic_uncertain');return $this->operator($mode,$path);});$this->assertSame('halted',$r['status']);$this->assertSame(0,$this->sent);
    unset($this->config['asap_authorization']);$this->config['execution_mode']='scheduled';$this->config['ends_on']='2026-10-08';$this->at('2026-10-08 09:00:00');$this->assertSame('halted',$this->executeWindow($this->queue(3))['status']);$this->assertSame(1,$this->prepared);
  }
  public function testAsapIsBoundedByTwoHundredDailyAndCountsEarlierNativeSends():void {
    $this->asapConfig();for($i=0;$i<175;$i++){ $this->at('2026-10-07 10:00:00');$this->message('earlier-'.$i.'@example.test',3,TRUE); }$this->at('2026-10-07 15:15:00');$r=$this->executeAsap($this->queue(50));$this->assertSame('complete',$r['status']);$this->assertSame(25,$this->sent);$this->assertSame(200,$this->quota->usage('2026-10-07',15)['day']);
  }
  public function testAsapCannotBeRenewedAsAnotherInitialBatchOnAnotherDate():void {
    $this->asapConfig();$this->assertSame('complete',$this->executeAsap($this->queue(1))['status']);$this->at('2026-10-08 15:15:00');$this->config['starts_on']=$this->config['ends_on']='2026-10-08';$this->config['asap_authorization']['local_date']='2026-10-08';$this->config['asap_authorization']['issued_at']=$this->now;$this->config['asap_authorization']['expires']=$this->now+3300;
    $this->assertTrue($this->executeAsap($this->queue(2))['duplicate']);$this->assertSame(1,$this->sent);
  }
  public function testInterruptedAsapNeverResumes():void {
    $this->asapConfig();$records=$this->queue(2);foreach($records as &$row)$row['recipient_hash']=$this->ledger->contactHash($row['email']);unset($row);
    $this->quota->reserveWindow('2026-10-07',15,hash('sha256',json_encode($this->config)),$records,5,$this->now+3300);$this->assertSame('running',$this->executeAsap($this->queue(2))['status']);$this->assertSame(0,$this->prepared);
  }
  public function testFourWindowsCapTwoHundredIncludingOutsideFollowups():void {
    for($i=0;$i<3;$i++)$this->message('followup-'.$i.'@example.test',7,TRUE);
    $queue=$this->queue(250);
    foreach([9,10,11,12] as $hour){$this->at('2026-10-07 '.$hour.':00:00');$r=$this->executeWindow($queue);$this->assertSame('complete',$r['status']);$this->assertLessThanOrEqual(50,$r['counts']['accepted']);}
    $this->assertSame(197,$this->sent);$this->assertSame(200,$this->quota->usage('2026-10-07',12)['day']);
    $before=$this->prepared;$this->at('2026-10-07 13:00:00');$this->assertSame('outside_window',$this->executeWindow($queue)['status']);$this->assertSame($before,$this->prepared);
  }
  public function testNoCatchupAndTimezoneAcrossDst():void {
    foreach(['2026-10-07 08:59:59','2026-10-07 09:05:00','2026-10-07 14:00:00'] as $clock){$this->at($clock);$this->assertSame('outside_window',$this->executeWindow($this->queue(1))['status']);}
    $this->assertSame(0,$this->prepared);$this->at('2026-11-02 09:00:00');$this->assertSame('14:00',gmdate('H:i',$this->now));$this->assertSame('complete',$this->executeWindow($this->queue(1))['status']);
  }
  public function testAcceptedWindowReplayDoesNotCallAnyPreparation():void {
    $r=$this->executeWindow($this->queue(2));$repeat=$this->executeWindow($this->queue(2));$this->assertSame('complete',$r['status']);$this->assertTrue($repeat['duplicate']);$this->assertSame(2,$this->sent);$this->assertSame(2,$this->prepared);
  }
  public function testFailureHaltsAllLaterWindowsAndKeepsReservations():void {
    $r=$this->executeWindow($this->queue(4),function(string $m,string $p):array{if($m==='dispatch'&&$this->sent===1)throw new \RuntimeException('synthetic_uncertain');return $this->operator($m,$p);});
    $this->assertSame('halted',$r['status']);$this->assertSame(1,$this->sent);$this->assertSame(2,$this->prepared);$this->assertSame(4,$this->quota->usage('2026-10-07',9)['day']);
    $this->at('2026-10-07 10:00:00');$later=$this->executeWindow($this->queue(5));$this->assertSame('halted',$later['status']);$this->assertSame(2,$this->prepared);
  }
  public function testInterruptedRunNeverResumesAndHaltsNextWindow():void {
    $r=$this->queue(2);foreach($r as &$row)$row['recipient_hash']=$this->ledger->contactHash($row['email']);unset($row);
    $this->quota->reserveWindow('2026-10-07',9,hash('sha256',json_encode($this->config)),$r,1);
    $this->assertSame('running',$this->executeWindow($this->queue(2))['status']);$this->assertSame(0,$this->prepared);
    $this->at('2026-10-07 10:00:00');$this->assertSame('halted',$this->executeWindow($this->queue(3))['status']);$this->assertSame(0,$this->sent);
  }
  public function testMissingAndStaleCapacityFailBeforePreparation():void {
    $r=$this->executeWindow($this->queue(1),NULL,fn(array $b):array=>array_replace($this->capacity(),['checked_at'=>$this->now-301]));$this->assertSame('halted',$r['status']);$this->assertSame(0,$this->prepared);$this->assertSame(0,$this->sent);
  }
  public function testInventedBudgetAndUnknownRemainingDoNotBecomeVerified():void {
    $r=$this->executeWindow($this->queue(1),NULL,fn(array $b):array=>array_replace($this->capacity(),['transactional_unobserved_day_reserve'=>249]));$this->assertSame('halted',$r['status']);$this->assertSame(0,$this->sent);
  }
  public function testZeroFreshProviderBudgetDefersWithoutLatchAndLaterWindowUsesRemainingDailyCapacity():void {
    $this->at('2026-10-07 08:00:00');
    for($i=0;$i<150;$i++)$this->message('already-sent-'.$i.'@example.test',0,TRUE);
    $this->at('2026-10-07 09:00:00');
    $this->db->insert('famtastic_acquisition_clock')->fields(['id'=>1,'status'=>'active','reason'=>'','changed'=>$this->now])->execute();
    $queue=$this->queue(50);
    $zero=fn(array $binding):array=>array_replace($this->capacity(),['available_today'=>0,'available_hour'=>0,'observed_today'=>251,'observed_hour'=>101]);

    $deferred=$this->executeWindow($queue,NULL,$zero);
    $this->assertSame('capacity_exhausted',$deferred['status']);
    $this->assertSame('provider_capacity_unavailable',$deferred['reason']);
    $this->assertSame('active',$this->quota->clockStatus());
    $this->assertSame(0,(int)$this->db->select('famtastic_acquisition_window','w')->countQuery()->execute()->fetchField());
    $this->assertSame(0,(int)$this->db->select('famtastic_acquisition_slot','s')->countQuery()->execute()->fetchField());
    $this->assertSame(0,$this->prepared);$this->assertSame(0,$this->sent);
    foreach($queue as $record)$this->assertFalse($this->quota->usedQueue($record['queue_key']));

    // A fresh approved hour with 50 of the campaign's 200 daily slots left can
    // consume the unchanged queue; the zero-budget check did not latch failure.
    $this->at('2026-10-07 10:00:00');
    $laterCapacity=fn(array $binding):array=>array_replace($this->capacity(),['available_today'=>50,'available_hour'=>50]);
    $later=$this->executeWindow($queue,NULL,$laterCapacity);
    $this->assertSame('complete',$later['status']);
    $this->assertSame(50,$later['counts']['accepted']);
    $this->assertSame('active',$this->quota->clockStatus());
    $this->assertSame(50,$this->prepared);$this->assertSame(50,$this->sent);
    $this->assertSame(200,$this->quota->usage('2026-10-07',10)['day']);
    $this->assertSame(50,$this->quota->usage('2026-10-07',10)['window']);
  }
  public function testFreshCapacityCallbackCanCrossASecondWithoutFalseFutureRejection():void {
    $r=$this->executeWindow($this->queue(1),NULL,function(array $binding):array{$this->now++;return $this->capacity();});$this->assertSame('complete',$r['status']);$this->assertSame(1,$this->sent);
  }
  public function testMissingCapacityFieldsNeverPrepare():void {
    $r=$this->executeWindow($this->queue(1),NULL,static fn(array $b):array=>[]);$this->assertSame('halted',$r['status']);$this->assertSame(0,$this->prepared);
  }
  public function testDuplicateContactUnderDifferentQueueKeysIsRejectedBeforePreparation():void {
    $queue=$this->queue(2);$queue[1]['email']=$queue[0]['email'];try{$this->executeWindow($queue);$this->fail('Duplicate recipient accepted');}catch(\RuntimeException $e){$this->assertSame('acquisition_window_stored_contact_required',$e->getMessage());}$this->assertSame(0,$this->prepared);
  }
  public function testWrongSenderAccountFailsBeforeAuditAndPreparation():void {
    $this->config['sender_account_sha256']=str_repeat('f',64);try{$this->executeWindow($this->queue(1));$this->fail('Changed sender accepted');}catch(\RuntimeException $e){$this->assertSame('acquisition_window_sender_changed',$e->getMessage());}$this->assertSame(0,$this->prepared);
  }
  public function testFreshClockAfterPacingStopsBeforeNextTransport():void {
    $r=$this->executeWindow($this->queue(2),NULL,NULL,function(int $seconds):void{$this->at('2026-10-07 10:00:00');});$this->assertSame('halted',$r['status']);$this->assertSame(1,$this->sent);$this->assertSame(1,$this->prepared);
  }
  public function testNativeAdapterReservationsCannotSendOutsideApprovedHours():void {
    $id=$this->message('direct@example.test');$this->at('2026-10-07 14:00:00');$tx=$this->db->startTransaction();try{$this->quota->reserveMessage($id);$this->fail('Outside send allowed');}catch(\RuntimeException $e){$this->assertSame('acquisition_outside_approved_window',$e->getMessage());$tx->rollBack();}
  }
  public function testUniqueQueueAndMessageReservationsAreNotReassigned():void {
    $r=$this->queue(1);$r[0]['recipient_hash']=$this->ledger->contactHash($r[0]['email']);$a=$this->quota->reserveWindow('2026-10-07',9,str_repeat('a',64),$r,1);$this->assertTrue($this->quota->reserveWindow('2026-10-07',9,str_repeat('a',64),$r,1)['duplicate']);
    $wrong=$this->message('wrong@example.test');$this->expectExceptionMessage('acquisition_window_contact_binding_invalid');$this->quota->bindPrepared($a['slots'][0],$wrong);
  }
  public function testNativeOutsideReservationsAndUncertainAttemptCountAgainstDailyQuota():void {
    $id=$this->message('uncertain@example.test',3);$this->db->insert('famtastic_acquisition_dispatch')->fields(['message_id'=>$id,'content_hash'=>str_repeat('a',64),'manifest_hash'=>str_repeat('b',64),'approval_ref'=>'fixture_uncertain','status'=>'uncertain','created'=>$this->now,'changed'=>$this->now])->execute();$this->assertSame(1,$this->quota->usage('2026-10-07',9)['day']);
  }
  public function testMarkerOwnedSchedulePreservesOtherClocksAndRejectsAlteration():void {
    $home='/home/synthetic';$input=$home.'/private/acquisition-199/window.json';$before="# unrelated\n*/5 * * * * /usr/bin/true\n";$installed=AcquisitionWindowSchedule::install($before,$home,$input);
    $this->assertStringStartsWith($before,$installed);$this->assertTrue(AcquisitionWindowSchedule::inspect($installed,$home,$input));$this->assertSame($installed,AcquisitionWindowSchedule::install($installed,$home,$input));$this->assertStringNotContainsString('php:script',$installed);$this->assertStringNotContainsString('cron.php',$installed);$this->expectExceptionMessage('acquisition_clock_altered');AcquisitionWindowSchedule::inspect(str_replace('* * * * * cd','*/5 * * * * cd',$installed),$home,$input);
  }
}
