<?php
declare(strict_types=1);

// Standalone test bootstrap. Never load site settings, kernel, Drush or a DSN env.
const PROOF_ROOT = __DIR__ . '/../../..';
function proofNeed(bool $ok, string $message): void {
  if (!$ok) throw new RuntimeException($message);
}
function proofConfig(string $path): array {
  $real = realpath($path);
  proofNeed($real !== FALSE && !is_link($path) && basename($real) === 'connection.json', 'invalid_connection_file');
  $root = dirname($real); $stat = stat($real);
  proofNeed(dirname($root) === realpath(sys_get_temp_dir()) && preg_match('/^famtastic-worker-mariadb-[A-Za-z0-9]{6}$/', basename($root)) === 1, 'not_owned_tmp_root');
  proofNeed((fileperms($root) & 0077) === 0 && ($stat['mode'] & 0077) === 0 && $stat['size'] < 8192, 'connection_file_not_private');
  proofNeed(function_exists('posix_geteuid') && $stat['uid'] === posix_geteuid(), 'connection_file_foreign_owner');
  $c = json_decode(file_get_contents($real), TRUE, flags: JSON_THROW_ON_ERROR);
  $l = json_decode(file_get_contents(__DIR__ . '/lineage.json'), TRUE, flags: JSON_THROW_ON_ERROR);
  proofNeed($c['schema'] === $l['schema'] && $c['image'] === $l['image'] && $c['source_commit'] === $l['source_commit'], 'connection_lineage_mismatch');
  proofNeed(preg_match('/^[a-f0-9]{24}$/', $c['run']) === 1 && $c['database'] === 'wc_' . $c['run']
    && $c['username'] === 'wc_' . substr($c['run'], 0, 12) && preg_match('/^[a-f0-9]{48}$/', $c['password']) === 1, 'foreign_database_identity');
  proofNeed($c['host'] === '127.0.0.1' && is_int($c['port']) && $c['port'] > 1024 && $c['port'] <= 65535
    && !in_array($c['port'], [3306, 3400], TRUE) && $c['hostname'] === 'fwc-' . $c['run']
    && preg_match('/^[a-f0-9]{64}$/', $c['container_id']) === 1, 'nonisolated_database_endpoint');
  foreach ($l['files'] as $file => $hash) proofNeed(hash_file('sha256', PROOF_ROOT . '/' . $file) === $hash, 'frozen_source_changed:' . $file);
  return $c;
}
function proofLoad(string $mode): void {
  proofNeed(in_array($mode, ['current', 'baseline'], TRUE), 'invalid_mode');
  $vendor = getenv('FAMTASTIC_BACKEND_VENDOR');
  proofNeed(is_string($vendor) && is_file($vendor . '/autoload.php'), 'matching_vendor_required');
  foreach (['composer.json', 'composer.lock'] as $file) {
    proofNeed(hash_file('sha256', dirname($vendor) . '/' . $file) === hash_file('sha256', PROOF_ROOT . '/backend/' . $file), 'vendor_manifest_mismatch');
  }
  require PROOF_ROOT . '/scripts/automation-test-bootstrap.php';
  $loader->addPsr4('Drupal\\mysql\\', $core . '/modules/mysql/src', TRUE);
  require_once $core . '/includes/bootstrap.inc';
  require_once PROOF_ROOT . '/backend/web/modules/custom/famtastic_pipeline/famtastic_pipeline.install';
  if ($mode === 'baseline') {
    $l = json_decode(file_get_contents(__DIR__ . '/lineage.json'), TRUE, flags: JSON_THROW_ON_ERROR);
    $file = __DIR__ . '/' . $l['baseline_fixture'];
    proofNeed(!is_link($file) && filesize($file) === $l['baseline_bytes'] && filesize($file) < 32768
      && hash_file('sha256', $file) === $l['baseline_sha256'], 'invalid_frozen_baseline');
    proofNeed(!class_exists(Drupal\famtastic_pipeline\Service\WorkerCoordinator::class, FALSE), 'coordinator_already_loaded');
    require $file;
  }
  require_once __DIR__ . '/inputs.php';
}
function proofConnection(array $c): Drupal\mysql\Driver\Database\mysql\Connection {
  proofNeed(extension_loaded('pdo_mysql'), 'pdo_mysql_required');
  $options = ['driver' => 'mysql', 'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql', 'prefix' => '',
    'host' => $c['host'], 'port' => $c['port'], 'database' => $c['database'], 'username' => $c['username'], 'password' => $c['password'],
    'isolation_level' => 'REPEATABLE READ', 'pdo' => [PDO::ATTR_TIMEOUT => 3]];
  $db = new Drupal\mysql\Driver\Database\mysql\Connection(Drupal\mysql\Driver\Database\mysql\Connection::open($options), $options);
  // Read-only identity checks precede ALL schema/fixture writes. No fallback DSN.
  $owner = $db->query('SELECT * FROM proof_harness_owner')->fetchAll(PDO::FETCH_ASSOC);
  proofNeed(count($owner) === 1 && $owner[0]['run_id'] === $c['run'] && $owner[0]['image_id'] === $c['image']
    && $owner[0]['source_commit'] === $c['source_commit'], 'server_owner_marker_mismatch');
  $facts = $db->query('SELECT DATABASE() AS db, @@hostname AS host, @@tx_isolation AS isolation, VERSION() AS version')->fetchAssoc();
  proofNeed($facts['db'] === $c['database'] && $facts['host'] === $c['hostname'] && $facts['isolation'] === 'REPEATABLE-READ'
    && str_contains($facts['version'], '10.11.') && str_contains($facts['version'], 'MariaDB'), 'server_identity_or_isolation_mismatch');
  $db->query('SET SESSION innodb_lock_wait_timeout = 45');
  $db->query('SET SESSION default_storage_engine = InnoDB');
  return $db;
}
