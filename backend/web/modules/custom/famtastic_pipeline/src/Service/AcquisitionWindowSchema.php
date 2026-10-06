<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;

/** Campaign-only durable capacity and interruption journal. */
final class AcquisitionWindowSchema {
  public static function tables(): array {
    $v = static fn(int $n, bool $required = TRUE): array => ['type'=>'varchar','length'=>$n,'not null'=>$required];
    $i = ['type'=>'int','not null'=>TRUE,'default'=>0];
    return [
      'famtastic_acquisition_clock' => ['fields'=>['id'=>$i,'status'=>$v(16),'reason'=>$v(100),'changed'=>$i], 'primary key'=>['id'], 'unique keys'=>['identity'=>['id']]],
      'famtastic_acquisition_day' => ['fields'=>['local_date'=>$v(10),'created'=>$i], 'primary key'=>['local_date'], 'unique keys'=>['identity'=>['local_date']]],
      'famtastic_acquisition_window' => ['fields'=>['window_key'=>$v(32),'config_hash'=>$v(64),'status'=>$v(16),'reason'=>$v(100),'expires'=>['type'=>'int','not null'=>FALSE],'created'=>$i,'changed'=>$i], 'primary key'=>['window_key'], 'unique keys'=>['identity'=>['window_key']]],
      'famtastic_acquisition_slot' => ['fields'=>['slot_key'=>$v(64),'queue_hash'=>$v(64,FALSE),'local_date'=>$v(10),'window_key'=>$v(32),'campaign_id'=>$i,'recipient_hash'=>$v(64),'message_id'=>['type'=>'int','not null'=>FALSE],'status'=>$v(16),'created'=>$i,'changed'=>$i], 'primary key'=>['slot_key'], 'unique keys'=>['identity'=>['slot_key'],'queue'=>['queue_hash'],'message'=>['message_id']], 'indexes'=>['day'=>['local_date'],'window'=>['window_key']]],
    ];
  }

  /** Widen persisted window identity columns without rebuilding journal data. */
  public static function upgradeWindowKeyColumns(Connection $database): void {
    $schema = $database->schema();
    $definitions = self::tables();
    foreach ([
      ['famtastic_acquisition_window', 'window_key'],
      ['famtastic_acquisition_slot', 'window_key'],
    ] as [$table, $field]) {
      if (!$schema->tableExists($table) || !$schema->fieldExists($table, $field)) {
        throw new \RuntimeException('Acquisition window key schema is partial: ' . $table . '.' . $field);
      }
      // changeField is safe to rerun at length 32 and delegates index handling
      // to Drupal's database driver, including the primary and lookup keys.
      $schema->changeField($table, $field, $field, $definitions[$table]['fields'][$field]);
      if (!$schema->fieldExists($table, $field)) throw new \RuntimeException('Acquisition window key migration did not retain: ' . $table . '.' . $field);
    }
    foreach (['famtastic_acquisition_window' => ['identity'], 'famtastic_acquisition_slot' => ['identity', 'queue', 'message', 'day', 'window']] as $table => $indexes) {
      foreach ($indexes as $index) if (!$schema->indexExists($table, $index)) throw new \RuntimeException('Acquisition window key migration lost index: ' . $table . '.' . $index);
    }
  }
}
