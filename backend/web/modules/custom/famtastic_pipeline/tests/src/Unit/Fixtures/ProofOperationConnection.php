<?php
declare(strict_types=1);
namespace Drupal\Tests\famtastic_pipeline\Unit\Fixtures;

use Drupal\Core\Database\Transaction\TransactionManagerInterface;

require_once __DIR__ . '/WorkerMutexConnection.php';

/** Actual SQLite commits/rollbacks; faults surround, never replace, Drupal APIs. */
class ProofOperationConnection extends WorkerMutexConnection {
  public ?string $commitFault = NULL;
  protected function driverTransactionManager(): TransactionManagerInterface {
    return new ProofOperationTransactionManager($this);
  }
}

class ProofOperationTransactionManager extends \Drupal\sqlite\Driver\Database\sqlite\TransactionManager {
  protected function processRootCommit(): void {
    if ($this->connection->commitFault === 'before_commit') {
      $this->connection->commitFault = NULL;
      throw new \RuntimeException('Synthetic commit failure before SQLite commit.');
    }
    parent::processRootCommit();
  }
  public function unpile(string $name, string $id): void {
    $loseAcknowledgement = $this->stackDepth() === 1 && $this->connection->commitFault === 'after_commit';
    parent::unpile($name, $id);
    if ($loseAcknowledgement) {
      $this->connection->commitFault = NULL;
      throw new \RuntimeException('Synthetic acknowledgement loss after SQLite commit.');
    }
  }
}
