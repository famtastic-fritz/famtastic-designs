#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Rehearse migration 8041 against a disposable MariaDB database.
 *
 * This is deliberately not a Drush command: it exercises the real Drupal
 * MySQL Schema API while keeping the test outside a customer database. It
 * refuses every database name except famtastic_preview_rehearsal.
 *
 * Required environment:
 * - FAMTASTIC_MIGRATION_REHEARSAL_CONFIRM=DISPOSABLE_DB
 * - FAMTASTIC_MIGRATION_REHEARSAL_DB_USER
 * - FAMTASTIC_MIGRATION_REHEARSAL_DB_PASSWORD
 *
 * Optional environment:
 * - FAMTASTIC_MIGRATION_REHEARSAL_DB_HOST (default 127.0.0.1)
 * - FAMTASTIC_MIGRATION_REHEARSAL_DB_PORT (default 3306)
 * - FAMTASTIC_REHEARSAL_AUTOLOAD (useful when vendor lives elsewhere)
 * - FAMTASTIC_REHEARSAL_MYSQL_DRIVER_ROOT (paired external Drupal core path)
 */

use Drupal\Core\Utility\UpdateException;
use Drupal\mysql\Driver\Database\mysql\Connection;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$root = dirname(__DIR__);
$autoload = getenv('FAMTASTIC_REHEARSAL_AUTOLOAD') ?: $root . '/vendor/autoload.php';
$driver_root = getenv('FAMTASTIC_REHEARSAL_MYSQL_DRIVER_ROOT') ?: $root . '/web/core/modules/mysql/src';
if (getenv('FAMTASTIC_MIGRATION_REHEARSAL_CONFIRM') !== 'DISPOSABLE_DB') {
  throw new RuntimeException('Refusing migration rehearsal. Set FAMTASTIC_MIGRATION_REHEARSAL_CONFIRM=DISPOSABLE_DB for an isolated database only.');
}
if (!is_file($autoload) || !is_dir($driver_root)) {
  throw new RuntimeException('Drupal vendor/MySQL driver is unavailable. Run composer install first or provide the documented rehearsal paths.');
}

$database = 'famtastic_preview_rehearsal';
$username = getenv('FAMTASTIC_MIGRATION_REHEARSAL_DB_USER') ?: '';
$password = getenv('FAMTASTIC_MIGRATION_REHEARSAL_DB_PASSWORD') ?: '';
if ($username === '' || $password === '') {
  throw new RuntimeException('Refusing migration rehearsal without disposable database credentials.');
}

$loader = require $autoload;
$loader->addPsr4('Drupal\\mysql\\', $driver_root);
if (!function_exists('t')) {
  /** Minimal translation stub for update-hook return values. */
  function t(string $string, array $args = []): string {
    return strtr($string, $args);
  }
}
require_once $root . '/web/modules/custom/famtastic_pipeline/famtastic_pipeline.install';

/** @throws \RuntimeException */
function rehearsal_assert(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

/** @throws \Throwable */
function rehearsal_expect_update_exception(callable $callback, string $needle): void {
  try {
    $callback();
  }
  catch (UpdateException $exception) {
    rehearsal_assert(str_contains($exception->getMessage(), $needle), 'UpdateException did not identify ' . $needle . '.');
    return;
  }
  throw new RuntimeException('Expected update 8041 to fail closed.');
}

$options = [
  'driver' => 'mysql',
  'database' => $database,
  'username' => $username,
  'password' => $password,
  'host' => getenv('FAMTASTIC_MIGRATION_REHEARSAL_DB_HOST') ?: '127.0.0.1',
  'port' => (int) (getenv('FAMTASTIC_MIGRATION_REHEARSAL_DB_PORT') ?: 3306),
  'prefix' => '',
];
$pdo = Connection::open($options);
$connection = new Connection($pdo, $options);
$container = new ContainerBuilder();
$container->set('database', $connection);
\Drupal::setContainer($container);
$schema = $connection->schema();
$table = 'famtastic_preview_delivery';
$definition = _famtastic_pipeline_preview_delivery_schema()[$table];
$sandbox = [];

$reset = static function () use ($schema, $table): void {
  if ($schema->tableExists($table)) {
    $schema->dropTable($table);
  }
};

try {
  // Actual production branch when the table does not exist.
  famtastic_pipeline_update_8041($sandbox);
  rehearsal_assert($schema->fieldExists($table, 'research_report_snapshot'), 'Clean create omitted a required field.');
  rehearsal_assert($schema->indexExists($table, 'public_id'), 'Clean create omitted public_id uniqueness.');
  rehearsal_assert($schema->indexExists($table, 'delivery_key'), 'Clean create omitted delivery_key uniqueness.');
  print "8041 clean-create: PASS\n";
  $reset();

  // A nonempty partial table with ownership identifiers can be completed.
  $partial = [
    'fields' => [
      'id' => ['type' => 'serial', 'unsigned' => TRUE, 'not null' => TRUE],
      'public_id' => ['type' => 'varchar', 'length' => 36, 'not null' => TRUE],
      'delivery_key' => ['type' => 'varchar', 'length' => 191, 'not null' => TRUE],
      'prospect_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
    ],
    'primary key' => ['id'],
  ];
  $schema->createTable($table, $partial);
  $connection->insert($table)->fields([
    'public_id' => '550e8400-e29b-41d4-a716-446655440000',
    'delivery_key' => 'rehearsal:partial',
    'prospect_id' => 1,
  ])->execute();
  famtastic_pipeline_update_8041($sandbox);
  rehearsal_assert($schema->fieldExists($table, 'text_snapshot'), 'Partial upgrade omitted text_snapshot.');
  rehearsal_assert($schema->indexExists($table, 'prospect_state'), 'Partial upgrade omitted an index.');
  rehearsal_assert($schema->indexExists($table, 'public_id'), 'Partial upgrade omitted public_id uniqueness.');
  print "8041 nonempty partial: PASS\n";
  $reset();

  // Missing ownership must not be invented or partially repaired.
  unset($partial['fields']['prospect_id']);
  $schema->createTable($table, $partial);
  $connection->insert($table)->fields([
    'public_id' => '550e8400-e29b-41d4-a716-446655440000',
    'delivery_key' => 'rehearsal:unsafe',
  ])->execute();
  rehearsal_expect_update_exception(static fn () => famtastic_pipeline_update_8041($sandbox), 'prospect_id');
  rehearsal_assert(!$schema->fieldExists($table, 'text_snapshot'), 'Unsafe partial table was mutated before failure.');
  print "8041 unsafe partial: PASS (fail closed)\n";
  $reset();

  // Duplicate public identities must also fail before index/DDL changes.
  $duplicate = $definition;
  unset($duplicate['unique keys'], $duplicate['indexes']);
  $schema->createTable($table, $duplicate);
  foreach ([1, 2] as $prospect_id) {
    $connection->insert($table)->fields([
      'public_id' => '550e8400-e29b-41d4-a716-446655440000',
      'delivery_key' => 'rehearsal:duplicate:' . $prospect_id,
      'prospect_id' => $prospect_id,
      'text_snapshot' => '',
    ])->execute();
  }
  rehearsal_expect_update_exception(static fn () => famtastic_pipeline_update_8041($sandbox), 'public_id');
  rehearsal_assert(!$schema->indexExists($table, 'prospect_state'), 'Duplicate partial table was mutated before failure.');
  print "8041 duplicate partial: PASS (fail closed)\n";
}
finally {
  $reset();
}

printf("MariaDB version: %s\n", $connection->query('SELECT VERSION()')->fetchField());
print "Preview-delivery migration rehearsal: PASS\n";
