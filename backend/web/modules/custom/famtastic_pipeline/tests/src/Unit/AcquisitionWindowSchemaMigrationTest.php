<?php

declare(strict_types=1);

namespace Drupal\Tests\famtastic_pipeline\Unit;

use Drupal\famtastic_pipeline\Service\AcquisitionWindowSchema;
use Drupal\sqlite\Driver\Database\sqlite\Connection;
use Drupal\Tests\UnitTestCase;

/** @coversDefaultClass \Drupal\famtastic_pipeline\Service\AcquisitionWindowSchema */
final class AcquisitionWindowSchemaMigrationTest extends UnitTestCase {

  private Connection $database;

  protected function setUp(): void {
    parent::setUp();
    $options = ['database' => ':memory:', 'prefix' => '', 'driver' => 'sqlite', 'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite'];
    $this->database = new Connection(Connection::open($options), $options);
    $definitions = AcquisitionWindowSchema::tables();
    foreach (['famtastic_acquisition_window', 'famtastic_acquisition_slot'] as $table) {
      $definition = $definitions[$table];
      $definition['fields']['window_key']['length'] = 13;
      $this->database->schema()->createTable($table, $definition);
    }
    $this->database->insert('famtastic_acquisition_window')->fields([
      'window_key' => 'asap-first-50', 'config_hash' => str_repeat('a', 64), 'status' => 'complete', 'reason' => '', 'expires' => 0, 'created' => 100, 'changed' => 200,
    ])->execute();
    $this->database->insert('famtastic_acquisition_slot')->fields([
      'slot_key' => str_repeat('b', 64), 'queue_hash' => str_repeat('c', 64), 'local_date' => '2026-10-06', 'window_key' => 'asap-first-50', 'campaign_id' => 5, 'recipient_hash' => str_repeat('d', 64), 'message_id' => NULL, 'status' => 'accepted', 'created' => 100, 'changed' => 200,
    ])->execute();
  }

  /** @covers ::upgradeWindowKeyColumns */
  public function testMigrationWidensKeysAndPreservesAcceptedWindowJournal(): void {
    $this->assertSame('varchar(13)', strtolower((string) $this->columnType('famtastic_acquisition_window', 'window_key')));
    $this->assertSame('varchar(13)', strtolower((string) $this->columnType('famtastic_acquisition_slot', 'window_key')));

    AcquisitionWindowSchema::upgradeWindowKeyColumns($this->database);

    $this->assertSame('varchar(32)', strtolower((string) $this->columnType('famtastic_acquisition_window', 'window_key')));
    $this->assertSame('varchar(32)', strtolower((string) $this->columnType('famtastic_acquisition_slot', 'window_key')));
    $this->assertTrue($this->database->schema()->indexExists('famtastic_acquisition_window', 'identity'));
    foreach (['identity', 'queue', 'message', 'day', 'window'] as $index) $this->assertTrue($this->database->schema()->indexExists('famtastic_acquisition_slot', $index));

    // The old first-fifty journal survives and a current ASAP industry key of
    // 28 characters can now be represented in both reservation tables.
    $this->assertSame('complete', $this->database->select('famtastic_acquisition_window', 'w')->fields('w', ['status'])->condition('window_key', 'asap-first-50')->execute()->fetchField());
    $this->assertSame('accepted', $this->database->select('famtastic_acquisition_slot', 's')->fields('s', ['status'])->condition('slot_key', str_repeat('b', 64))->execute()->fetchField());
    $longKey = 'asap-industry-2026-10-06-17';
    $this->assertGreaterThan(13, strlen($longKey));
    $this->database->insert('famtastic_acquisition_window')->fields([
      'window_key' => $longKey, 'config_hash' => str_repeat('e', 64), 'status' => 'running', 'reason' => '', 'expires' => 300, 'created' => 250, 'changed' => 250,
    ])->execute();
    $this->database->insert('famtastic_acquisition_slot')->fields([
      'slot_key' => str_repeat('f', 64), 'queue_hash' => str_repeat('1', 64), 'local_date' => '2026-10-06', 'window_key' => $longKey, 'campaign_id' => 5, 'recipient_hash' => str_repeat('2', 64), 'message_id' => NULL, 'status' => 'reserved', 'created' => 250, 'changed' => 250,
    ])->execute();
    $this->assertSame($longKey, $this->database->select('famtastic_acquisition_window', 'w')->fields('w', ['window_key'])->condition('window_key', $longKey)->execute()->fetchField());
    $this->assertSame($longKey, $this->database->select('famtastic_acquisition_slot', 's')->fields('s', ['window_key'])->condition('slot_key', str_repeat('f', 64))->execute()->fetchField());

    // Reapplying the schema conversion is safe and does not drop data/indexes.
    AcquisitionWindowSchema::upgradeWindowKeyColumns($this->database);
    $this->assertSame(2, (int) $this->database->select('famtastic_acquisition_window', 'w')->countQuery()->execute()->fetchField());
    $this->assertSame(2, (int) $this->database->select('famtastic_acquisition_slot', 's')->countQuery()->execute()->fetchField());
    $this->assertTrue($this->database->schema()->indexExists('famtastic_acquisition_slot', 'window'));
  }

  private function columnType(string $table, string $column): ?string {
    foreach ($this->database->query('PRAGMA table_info(' . $table . ')') as $row) {
      if ($row->name === $column) return $row->type;
    }
    return NULL;
  }

}
