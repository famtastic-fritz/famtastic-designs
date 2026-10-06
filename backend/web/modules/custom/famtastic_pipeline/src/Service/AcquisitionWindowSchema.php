<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Campaign-only durable capacity and interruption journal. */
final class AcquisitionWindowSchema {
  public static function tables(): array {
    $v = static fn(int $n, bool $required = TRUE): array => ['type'=>'varchar','length'=>$n,'not null'=>$required];
    $i = ['type'=>'int','not null'=>TRUE,'default'=>0];
    return [
      'famtastic_acquisition_clock' => ['fields'=>['id'=>$i,'status'=>$v(16),'reason'=>$v(100),'changed'=>$i], 'primary key'=>['id'], 'unique keys'=>['identity'=>['id']]],
      'famtastic_acquisition_day' => ['fields'=>['local_date'=>$v(10),'created'=>$i], 'primary key'=>['local_date'], 'unique keys'=>['identity'=>['local_date']]],
      'famtastic_acquisition_window' => ['fields'=>['window_key'=>$v(13),'config_hash'=>$v(64),'status'=>$v(16),'reason'=>$v(100),'expires'=>['type'=>'int','not null'=>FALSE],'created'=>$i,'changed'=>$i], 'primary key'=>['window_key'], 'unique keys'=>['identity'=>['window_key']]],
      'famtastic_acquisition_slot' => ['fields'=>['slot_key'=>$v(64),'queue_hash'=>$v(64,FALSE),'local_date'=>$v(10),'window_key'=>$v(13),'campaign_id'=>$i,'recipient_hash'=>$v(64),'message_id'=>['type'=>'int','not null'=>FALSE],'status'=>$v(16),'created'=>$i,'changed'=>$i], 'primary key'=>['slot_key'], 'unique keys'=>['identity'=>['slot_key'],'queue'=>['queue_hash'],'message'=>['message_id']], 'indexes'=>['day'=>['local_date'],'window'=>['window_key']]],
    ];
  }
}
