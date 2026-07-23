<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for intakes.
 */
class IntakeListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'id' => $this->t('ID'),
      'prospect' => $this->t('Prospect'),
      'submitted' => $this->t('Submitted'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\famtastic_pipeline\Entity\Intake $entity */
    $prospect = $entity->get('prospect_ref')->entity;
    return [
      'id' => $entity->toLink($entity->id()),
      'prospect' => $prospect ? $prospect->label() : '—',
      'submitted' => $entity->get('submitted_at')->value ? $this->t('Yes') : $this->t('No'),
    ] + parent::buildRow($entity);
  }

}
