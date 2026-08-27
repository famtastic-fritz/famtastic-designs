<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;

/**
 * Releases only due, explicitly owner-approved verified-cold invitations.
 *
 * This is deliberately not registered with the generic lifecycle runner. Its
 * only send boundary is PublicPreviewDeliveryService::dispatchApproved(),
 * which rechecks one to ten exact IDs and their held outbox rows.
 */
final class ColdProofScheduledReleaseService {

  public function __construct(
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly PublicPreviewDeliveryService $previews,
    private readonly OperationalLedger $ledger,
  ) {}

  /** @return list<int> */
  public function dueApprovedDeliveryIds(int $limit = 10): array {
    $limit = max(1, min(10, $limit));
    // The join is intentionally only a provenance filter. The delivery's
    // state + held-outbox checks remain authoritative in dispatchApproved().
    $query = $this->database->select('famtastic_preview_delivery', 'p');
    $query->join('famtastic_cold_proof_ingress', 'i', 'i.preview_delivery_id = p.id');
    $query->fields('p', ['id'])
      ->condition('p.source_lane', 'verified_cold')
      ->condition('i.source_lane', 'verified_cold')
      ->condition('p.state', 'email_approved')
      ->condition('p.scheduled_release_at', $this->time->getRequestTime(), '<=')
      ->isNotNull('p.scheduled_release_at')
      ->isNotNull('p.email_outbox_id')
      ->orderBy('p.scheduled_release_at', 'ASC')
      ->orderBy('p.id', 'ASC')
      ->range(0, $limit);
    $ids = array_values(array_unique(array_map('intval', $query->execute()->fetchCol())));
    sort($ids, SORT_NUMERIC);
    return $ids;
  }

  /**
   * Checks due approved records and, only when requested, delegates their
   * exact IDs to the dedicated owner-gated dispatcher.
   */
  public function releaseDue(int $limit = 10, bool $dryRun = FALSE): array {
    $ids = $this->dueApprovedDeliveryIds($limit);
    $result = [
      'source_lane' => 'verified_cold',
      'dry_run' => $dryRun,
      'delivery_ids' => $ids,
      'dispatched' => NULL,
    ];
    if ($dryRun || $ids === []) {
      return $result;
    }
    // This call can never scan or release the global outbox.
    $result['dispatched'] = $this->previews->dispatchApproved($ids);
    foreach ($ids as $id) {
      $this->ledger->recordEvent(
        'cold-proof.scheduled-release:' . $id . ':' . $this->time->getRequestTime(),
        'cold_proof.scheduled_release_dispatched',
        ['preview_delivery_id' => $id, 'source_lane' => 'verified_cold'],
      );
    }
    return $result;
  }

}
