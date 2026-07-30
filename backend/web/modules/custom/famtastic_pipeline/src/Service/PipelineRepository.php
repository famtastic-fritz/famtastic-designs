<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\famtastic_pipeline\Entity\Intake;
use Drupal\famtastic_pipeline\Entity\Order;
use Drupal\famtastic_pipeline\Entity\Project;
use Drupal\famtastic_pipeline\Entity\Prospect;

/**
 * Loads pipeline entities and resolves a prospect from a link token.
 *
 * All lookups are scoped to a single prospect, which is how the system
 * guarantees one prospect can never read or write another prospect's data.
 */
class PipelineRepository {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TokenManager $tokenManager,
    protected TimeInterface $time,
  ) {}

  /**
   * Resolves a valid, non-expired, non-revoked prospect from a raw token.
   */
  public function loadProspectByToken(string $raw): ?Prospect {
    if ($raw === '') {
      return NULL;
    }
    $hash = $this->tokenManager->hash($raw);
    $storage = $this->entityTypeManager->getStorage('famtastic_prospect');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('token_hash', $hash)
      ->range(0, 1)
      ->execute();
    if (!$ids) {
      return NULL;
    }
    /** @var \Drupal\famtastic_pipeline\Entity\Prospect $prospect */
    $prospect = $storage->load(reset($ids));
    if (!$prospect) {
      return NULL;
    }
    if ((bool) $prospect->get('token_revoked')->value) {
      return NULL;
    }
    $expires = (int) $prospect->get('token_expires')->value;
    if ($expires > 0 && $this->time->getRequestTime() > $expires) {
      return NULL;
    }
    return $prospect;
  }

  /**
   * Returns the most recent order for a prospect, if any.
   */
  public function getOrder(Prospect $prospect): ?Order {
    return $this->latestReferencing('famtastic_order', $prospect);
  }

  /**
   * Returns the most recent intake for a prospect, if any.
   */
  public function getIntake(Prospect $prospect): ?Intake {
    return $this->latestReferencing('famtastic_intake', $prospect);
  }

  /**
   * Returns the most recent project for a prospect, if any.
   */
  public function getProject(Prospect $prospect): ?Project {
    return $this->latestReferencing('famtastic_project', $prospect);
  }

  /**
   * Loads the newest entity of a type whose prospect_ref points at $prospect.
   */
  protected function latestReferencing(string $entityTypeId, Prospect $prospect) {
    $storage = $this->entityTypeManager->getStorage($entityTypeId);
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('prospect_ref', $prospect->id())
      ->sort('id', 'DESC')
      ->range(0, 1)
      ->execute();
    return $ids ? $storage->load(reset($ids)) : NULL;
  }

}
