<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Core\Database\Connection;

/** One transaction-owned mutex across all worker capabilities and budget months. */
final class WorkerCoordinatorMutex {

  /**
   * Caller owns the transaction. Never release/delete/cache ownership in PHP.
   * Lock request/account/asset authority BEFORE this row, and jobs AFTER it.
   * The empty table is installed by schema/update 8066, never created at runtime.
   */
  public static function acquire(Connection $database): void {
    if (!$database->inTransaction()) throw new \LogicException('Worker mutex requires an active transaction.');
    // Drupal key-only upsert emits INSERT IGNORE on MySQL, not an exclusive
    // duplicate UPDATE. Both explicit statements also serialize first insertion.
    $sql = match ($database->driver()) {
      'mysql' => 'INSERT INTO {famtastic_worker_mutex} (id) VALUES (1) ON DUPLICATE KEY UPDATE id = 1',
      'sqlite' => 'INSERT INTO {famtastic_worker_mutex} (id) VALUES (1) ON CONFLICT (id) DO UPDATE SET id = excluded.id',
      default => throw new \RuntimeException('Unsupported worker mutex database driver.'),
    };
    if ($database->query($sql) === NULL) throw new \RuntimeException('Worker mutex acquisition failed.');
  }

  /** Nested success releases only the savepoint, never the root transaction. */
  public static function run(Connection $database, callable $operation): mixed {
    $transaction = $database->startTransaction();
    try {
      self::acquire($database);
      $result = $operation();
      $transaction->commitOrRelease();
      return $result;
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
  }
}
