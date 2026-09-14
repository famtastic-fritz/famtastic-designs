<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Reversible staff list placement; never a project or payment transition. */
final class ProspectListService {

  public const LABELS = ['active' => 'Active', 'completed' => 'Completed', 'archived' => 'Archived'];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entities,
    private readonly TimeInterface $time,
    private readonly UuidInterface $uuid,
    private readonly OperationalLedger $ledger,
  ) {}

  public static function state(mixed $value): string {
    return is_string($value) && isset(self::LABELS[$value]) ? $value : 'active';
  }

  /** Compare-and-set keeps a stale staff form from overwriting a newer move. */
  public function move(int $id, string $target, string $expected, AccountInterface $actor): bool {
    if (!$actor->isAuthenticated() || !$actor->hasPermission('administer famtastic pipeline')) throw new AccessDeniedHttpException();
    if (!isset(self::LABELS[$target], self::LABELS[$expected])) throw new \InvalidArgumentException('Choose a valid prospect list.');
    $row = $this->database->select('famtastic_prospect', 'p')->fields('p', ['id', 'staff_list_state'])
      ->condition('id', $id)->execute()->fetchAssoc();
    if (!$row) throw new \InvalidArgumentException('Prospect not found.');
    $current = self::state($row['staff_list_state']);
    if ($current === $target) return FALSE;
    if ($current !== $expected) throw new \RuntimeException('This prospect moved to another list. Reload the record before trying again.');
    $transaction = $this->database->startTransaction();
    try {
      $update = $this->database->update('famtastic_prospect')->fields([
        'staff_list_state' => $target, 'staff_list_changed_at' => $this->time->getRequestTime(),
        'staff_list_changed_by' => (int) $actor->id(),
      ])->condition('id', $id);
      if ($row['staff_list_state'] === NULL) $update->isNull('staff_list_state');
      else $update->condition('staff_list_state', $row['staff_list_state']);
      if ($update->execute() !== 1) throw new \RuntimeException('This prospect changed while you were moving it. Reload and try again.');
      $this->ledger->recordEvent('prospect-list:' . $id . ':' . $this->uuid->generate(), 'prospect.list_changed', [
        'from' => $current, 'to' => $target, 'actor_uid' => (int) $actor->id(),
      ], $id, provider: 'staff');
      unset($transaction);
    }
    catch (\Throwable $error) {
      if (isset($transaction)) $transaction->rollBack();
      throw $error;
    }
    $this->entities->getStorage('famtastic_prospect')->resetCache([$id]);
    Cache::invalidateTags(['famtastic_prospect:' . $id, 'famtastic_prospect_list']);
    return TRUE;
  }

}
