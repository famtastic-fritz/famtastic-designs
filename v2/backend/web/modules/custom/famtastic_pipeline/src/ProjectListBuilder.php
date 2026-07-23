<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for projects.
 */
class ProjectListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'id' => $this->t('ID'),
      'prospect' => $this->t('Prospect'),
      'delivery' => $this->t('Delivery'),
      'approval' => $this->t('Approval'),
      'proof' => $this->t('Proof URL'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\famtastic_pipeline\Entity\Project $entity */
    $prospect = $entity->get('prospect_ref')->entity;
    return [
      'id' => $entity->toLink($entity->id()),
      'prospect' => $prospect ? $prospect->label() : '—',
      'delivery' => $entity->get('delivery_status')->value,
      'approval' => $entity->get('approval_status')->value,
      'proof' => $entity->get('proof_url')->value ?: '—',
    ] + parent::buildRow($entity);
  }

}
