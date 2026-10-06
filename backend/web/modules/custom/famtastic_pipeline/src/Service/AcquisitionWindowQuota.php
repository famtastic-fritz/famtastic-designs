<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/** Atomic conservative reservations. Slots are never refunded after interruption. */
final class AcquisitionWindowQuota {
  public const ZONE = 'America/New_York';
  public const HOURS = [9,10,11,12];
  public const WINDOW_CAP = 50;
  public const DAY_CAP = 200;
  public const ASAP_KEY = 'asap-first-50';
  public function __construct(private readonly Connection $db, private readonly TimeInterface $time) {}
  public static function local(int $now): \DateTimeImmutable { return (new \DateTimeImmutable('@'.$now))->setTimezone(new \DateTimeZone(self::ZONE)); }
  public function clockStatus(): string { return (string)($this->db->select('famtastic_acquisition_clock','c')->fields('c',['status'])->condition('id',1)->execute()->fetchField()?:'absent'); }
  private function now(): int { return $this->time->getCurrentTime(); }
  private function lock(string $date): void {
    // Unique insertion arbitrates the first-ever initialization; subsequent work
    // serializes on this one global acquisition row (not a broad mail lock).
    $this->db->merge('famtastic_acquisition_clock')->insertFields(['status'=>'active','reason'=>'','changed'=>$this->now()])->key('id',1)->execute();
    $clock=$this->db->select('famtastic_acquisition_clock','c')->fields('c')->condition('id',1)->forUpdate()->execute()->fetchAssoc();
    if($clock['status']!=='active')throw new \RuntimeException('acquisition_clock_halted_manual_review');
    $this->db->merge('famtastic_acquisition_day')->insertFields(['created'=>$this->now()])->key('local_date',$date)->execute();
    $this->db->select('famtastic_acquisition_day','d')->fields('d')->condition('local_date',$date)->forUpdate()->execute()->fetchAssoc();
  }
  private function external(int $start,int $end): int {
    $q=$this->db->select('famtastic_acquisition_message','a');
    $q->join('famtastic_email_message','m','m.id=a.message_id');
    $q->leftJoin('famtastic_acquisition_dispatch','d','d.message_id=m.id');
    $q->leftJoin('famtastic_acquisition_slot','s','s.message_id=m.id');
    $sent=$q->andConditionGroup()->condition('m.provider','smtp')->condition('m.sent_at',$start,'>=')->condition('m.sent_at',$end,'<');
    $reserved=$q->andConditionGroup()->condition('d.status',['reserved','uncertain','accepted'],'IN')->condition('d.created',$start,'>=')->condition('d.created',$end,'<');
    $q->isNull('s.message_id')->condition($q->orConditionGroup()->condition($sent)->condition($reserved));
    $q->addExpression('COUNT(DISTINCT m.id)','total');
    return (int)$q->execute()->fetchField();
  }
  public function usage(string $date,int $hour): array {
    $day=new \DateTimeImmutable($date.' 00:00:00',new \DateTimeZone(self::ZONE));
    $start=$day->getTimestamp();$end=$day->modify('+1 day')->getTimestamp();
    $h=$day->setTime($hour,0);$key=$date.'-'.sprintf('%02d',$hour);
    $hourStart=$h->getTimestamp();$hourEnd=$h->modify('+1 hour')->getTimestamp();$slots=$this->db->select('famtastic_acquisition_slot','s');$slots->leftJoin('famtastic_email_message','m','m.id=s.message_id');
    $slots->condition($slots->orConditionGroup()->condition('s.window_key',$key)->condition($slots->andConditionGroup()->condition('s.created',$hourStart,'>=')->condition('s.created',$hourEnd,'<'))->condition($slots->andConditionGroup()->condition('m.sent_at',$hourStart,'>=')->condition('m.sent_at',$hourEnd,'<')));
    return ['day'=>(int)$this->db->select('famtastic_acquisition_slot','s')->condition('local_date',$date)->countQuery()->execute()->fetchField()+$this->external($start,$end),'window'=>(int)$slots->countQuery()->execute()->fetchField()+$this->external($hourStart,$hourEnd)];
  }
  public function usedQueue(string $key): bool { return (bool)$this->db->select('famtastic_acquisition_slot','s')->condition('queue_hash',hash('sha256',$key))->countQuery()->execute()->fetchField(); }
  public function assertNoInterruptedRun(string $current): void {
    $old=$this->db->select('famtastic_acquisition_window','w')->fields('w',['window_key'])->condition('status','running')->condition('window_key',$current,'<>')->execute()->fetchField();
    if($old){$this->halt((string)$old,'acquisition_interrupted_window_manual_review');throw new \RuntimeException('acquisition_interrupted_window_manual_review');}
  }
  public function reserveWindow(string $date,int $hour,string $configHash,array $records,int $campaign,?int $asapExpires=NULL,?string $immediateKey=NULL,int $sharedDayCap=self::DAY_CAP): array {
    $todayException=$sharedDayCap===251&&$campaign===5&&$date==='2026-10-06'&&$asapExpires!==NULL&&$immediateKey==='asap-industry-'.$date.'-'.sprintf('%02d',$hour);
    if($sharedDayCap!==self::DAY_CAP&&!$todayException)throw new \RuntimeException('acquisition_shared_day_cap_invalid');
    $tx=$this->db->startTransaction();
    try {
      $this->lock($date);$key=$asapExpires===NULL?$date.'-'.sprintf('%02d',$hour):($immediateKey??self::ASAP_KEY);
      if($immediateKey!==NULL&&($asapExpires===NULL||$immediateKey!=='asap-industry-'.$date.'-'.sprintf('%02d',$hour)||self::local($this->now())->format('G')!==(string)$hour||self::local($asapExpires-1)->format('Y-m-d-H')!==$date.'-'.sprintf('%02d',$hour)))throw new \RuntimeException('acquisition_industry_window_binding_invalid');
      if($asapExpires!==NULL&&($campaign!==5||$asapExpires<=$this->now()||$asapExpires>$this->now()+3600||self::local($this->now())->format('Y-m-d')!==$date||self::local($asapExpires)->format('Y-m-d')!==$date))throw new \RuntimeException('acquisition_asap_reservation_invalid');
      $old=$this->db->select('famtastic_acquisition_window','w')->fields('w')->condition('window_key',$key)->execute()->fetchAssoc();
      if($old){if(!hash_equals($old['config_hash'],$configHash))throw new \RuntimeException('acquisition_window_config_changed');return ['duplicate'=>TRUE,'window_key'=>$key,'status'=>$old['status'],'slots'=>[]];}
      $usage=$this->usage($date,$hour);$count=count($records);
      if(!$count||$count>self::WINDOW_CAP||$usage['day']+$count>$sharedDayCap||$usage['window']+$count>self::WINDOW_CAP)throw new \RuntimeException('acquisition_window_capacity_exhausted');
      $this->db->insert('famtastic_acquisition_window')->fields(['window_key'=>$key,'config_hash'=>$configHash,'status'=>'running','reason'=>'','expires'=>$asapExpires,'created'=>$this->now(),'changed'=>$this->now()])->execute();
      $slots=[];
      foreach($records as $record){$slot=hash('sha256',$key.':'.$record['queue_key']);$this->db->insert('famtastic_acquisition_slot')->fields(['slot_key'=>$slot,'queue_hash'=>hash('sha256',$record['queue_key']),'local_date'=>$date,'window_key'=>$key,'campaign_id'=>$campaign,'recipient_hash'=>$record['recipient_hash'],'status'=>'reserved','created'=>$this->now(),'changed'=>$this->now()])->execute();$slots[]=$slot;}
      return ['duplicate'=>FALSE,'window_key'=>$key,'status'=>'running','slots'=>$slots];
    }catch(\Throwable $e){$tx->rollBack();throw $e;}
  }
  public function bindPrepared(string $slot,int $messageId): void {
    $tx=$this->db->startTransaction();
    try {
      $entry=$this->db->select('famtastic_acquisition_slot','s')->fields('s')->condition('slot_key',$slot)->execute()->fetchAssoc();
      if(!$entry)throw new \RuntimeException('acquisition_slot_required');$this->lock($entry['local_date']);
      $message=$this->db->select('famtastic_email_message','m')->fields('m',['campaign_id','recipient_hash'])->condition('id',$messageId)->execute()->fetchAssoc();
      if(!$message||(int)$message['campaign_id']!==(int)$entry['campaign_id']||!hash_equals($message['recipient_hash'],$entry['recipient_hash'])||($entry['message_id']!==NULL&&(int)$entry['message_id']!==$messageId))throw new \RuntimeException('acquisition_window_contact_binding_invalid');
      $this->db->update('famtastic_acquisition_slot')->fields(['message_id'=>$messageId,'status'=>'prepared','changed'=>$this->now()])->condition('slot_key',$slot)->condition('status','reserved')->execute();
    }catch(\Throwable $e){$tx->rollBack();throw $e;}
  }
  /** Called inside the exact adapter's transaction, before its SMTP reservation. */
  public function reserveMessage(int $messageId): void {
    $local=self::local($this->now());$date=$local->format('Y-m-d');$hour=(int)$local->format('G');$this->lock($date);
    $old=$this->db->select('famtastic_acquisition_slot','s')->fields('s')->condition('message_id',$messageId)->execute()->fetchAssoc();
    if($old&&self::immediateSlot($old)){$this->assertAsapSlot($old);if($old['status']!=='prepared')throw new \RuntimeException('acquisition_window_reservation_invalid');return;}
    if(!in_array($hour,self::HOURS,TRUE))throw new \RuntimeException('acquisition_outside_approved_window');
    if($old){if($old['local_date']!==$date||$old['window_key']!==$date.'-'.sprintf('%02d',$hour)||$old['status']!=='prepared')throw new \RuntimeException('acquisition_window_reservation_invalid');return;}
    $usage=$this->usage($date,$hour);if($usage['day']>=self::DAY_CAP||$usage['window']>=self::WINDOW_CAP)throw new \RuntimeException('acquisition_window_capacity_exhausted');
    $message=$this->db->select('famtastic_email_message','m')->fields('m',['campaign_id','recipient_hash'])->condition('id',$messageId)->execute()->fetchAssoc();
    if(!$message)throw new \RuntimeException('acquisition_message_required');
    $this->db->insert('famtastic_acquisition_slot')->fields(['slot_key'=>hash('sha256','native:'.$messageId),'local_date'=>$date,'window_key'=>$date.'-'.sprintf('%02d',$hour),'campaign_id'=>$message['campaign_id'],'recipient_hash'=>$message['recipient_hash'],'message_id'=>$messageId,'status'=>'prepared','created'=>$this->now(),'changed'=>$this->now()])->execute();
  }
  public function accepted(int $messageId): void { $this->db->update('famtastic_acquisition_slot')->fields(['status'=>'accepted','changed'=>$this->now()])->condition('message_id',$messageId)->execute(); }
  public function assertTransportWindow(int $messageId): void {
    $clock=$this->db->select('famtastic_acquisition_clock','c')->fields('c',['status'])->condition('id',1)->execute()->fetchField();
    $entry=$this->db->select('famtastic_acquisition_slot','s')->fields('s')->condition('message_id',$messageId)->execute()->fetchAssoc();
    $local=self::local($this->now());
    if($clock==='active'&&$entry&&self::immediateSlot($entry)){$this->assertAsapSlot($entry);return;}
    if($clock!=='active'||!$entry||$entry['local_date']!==$local->format('Y-m-d')||$entry['window_key']!==$local->format('Y-m-d-H'))throw new \RuntimeException('acquisition_window_elapsed_or_halted');
  }
  private static function immediateSlot(array $slot): bool { return ($slot['window_key']??'')===self::ASAP_KEY||preg_match('/^asap-industry-\d{4}-\d{2}-\d{2}-\d{2}$/D',(string)($slot['window_key']??''))===1; }
  private function assertAsapSlot(array $slot): void {
    $window=$this->db->select('famtastic_acquisition_window','w')->fields('w')->condition('window_key',$slot['window_key'])->execute()->fetchAssoc();
    if($slot['window_key']!==self::ASAP_KEY&&$slot['window_key']!=='asap-industry-'.self::local($this->now())->format('Y-m-d-H'))throw new \RuntimeException('acquisition_industry_window_elapsed');
    if(!$window||$window['status']!=='running'||(int)$slot['campaign_id']!==5||$slot['local_date']!==self::local($this->now())->format('Y-m-d')||(int)$window['expires']<=$this->now()||(int)$window['expires']>(int)$window['created']+3600)throw new \RuntimeException('acquisition_asap_expired_or_unbound');
  }
  public function finish(string $key): void { $this->db->update('famtastic_acquisition_window')->fields(['status'=>'complete','changed'=>$this->now()])->condition('window_key',$key)->condition('status','running')->execute(); }
  public function halt(string $key,string $code): void {
    $reason=preg_match('/^[a-z][a-z0-9_]{1,99}$/D',$code)?$code:'acquisition_private_failure_review';
    $this->db->merge('famtastic_acquisition_clock')->fields(['status'=>'halted','reason'=>$reason,'changed'=>$this->now()])->key('id',1)->execute();
    $this->db->update('famtastic_acquisition_window')->fields(['status'=>'halted','reason'=>$reason,'changed'=>$this->now()])->condition('window_key',$key)->execute();
  }
  public function result(string $key): array {
    $w=$this->db->select('famtastic_acquisition_window','w')->fields('w',['status','reason'])->condition('window_key',$key)->execute()->fetchAssoc();
    $q=$this->db->select('famtastic_acquisition_slot','s')->fields('s',['status'])->condition('window_key',$key);$q->addExpression('COUNT(*)','total');$q->groupBy('status');$counts=[];foreach($q->execute() as $row)$counts[$row->status]=(int)$row->total;
    return ['window_key'=>$key,'status'=>$w['status']??'absent','reason'=>$w['reason']??'','counts'=>$counts,'inbox_delivery_proved'=>FALSE];
  }
}
