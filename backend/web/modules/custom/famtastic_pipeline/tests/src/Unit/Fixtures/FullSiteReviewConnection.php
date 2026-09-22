<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit\Fixtures;

/** Real SQLite with observed lock intent; deterministic races, not DB contention. */
final class FullSiteReviewConnection extends \Drupal\sqlite\Driver\Database\sqlite\Connection {
  public array $reads = [];
  public bool $maskNextDraftHint = FALSE;
  public ?\Closure $beforeRead = NULL;
  public function select($table, $alias = NULL, array $options = []) {
    return new FullSiteReviewSelect($this, $table, $alias, $options);
  }
}

final class FullSiteReviewSelect extends \Drupal\sqlite\Driver\Database\sqlite\Select {
  private bool $locking = FALSE;
  public function forUpdate($set = TRUE) {
    $this->locking = $set;
    return parent::forUpdate($set);
  }
  public function execute() {
    $sql = (string) $this;
    $this->connection->reads[] = ['sql' => $sql, 'locking' => $this->locking, 'transaction' => $this->connection->inTransaction()];
    if ($hook = $this->connection->beforeRead) $hook($sql, $this->locking);
    if (!$this->locking && $this->connection->maskNextDraftHint && str_contains($sql, 'famtastic_event')) {
      $this->connection->maskNextDraftHint = FALSE;
      return $this->connection->query('SELECT NULL AS payload WHERE 0=1');
    }
    return parent::execute();
  }
}
