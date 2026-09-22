<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit\Fixtures;

/** Actual SQLite, with deterministic observation/fault seams; NOT concurrency proof. */
class WorkerMutexConnection extends \Drupal\sqlite\Driver\Database\sqlite\Connection {
  public array $observed = [];
  public ?\Closure $afterMutex = NULL;
  public ?\Closure $afterBudgetRead = NULL;

  public function query($query, array $args = [], $options = []) {
    $result = parent::query($query, $args, $options);
    if (str_starts_with($query, 'INSERT INTO {famtastic_worker_mutex}')) {
      $this->observed[] = ['sql' => $query, 'locking' => TRUE];
      if ($callback = $this->afterMutex) { $this->afterMutex = NULL; $callback(); }
    }
    return $result;
  }

  public function select($table, $alias = NULL, array $options = []) {
    return new WorkerMutexSelect($this, $table, $alias, $options);
  }
}

class WorkerMutexSelect extends \Drupal\sqlite\Driver\Database\sqlite\Select {
  private bool $lockingRequested = FALSE;
  public function forUpdate($set = TRUE) {
    $this->lockingRequested = $set;
    return parent::forUpdate($set);
  }
  public function execute() {
    $sql = (string) $this;
    $this->connection->observed[] = ['sql' => $sql, 'locking' => $this->lockingRequested];
    $result = parent::execute();
    if (str_contains($sql, 'famtastic_worker_budget') && ($callback = $this->connection->afterBudgetRead)) {
      $this->connection->afterBudgetRead = NULL;
      $callback();
    }
    return $result;
  }
}
