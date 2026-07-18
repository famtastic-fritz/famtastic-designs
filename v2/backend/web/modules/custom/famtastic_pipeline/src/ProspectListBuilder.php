<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for prospects.
 */
class ProspectListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'id' => $this->t('ID'),
      'business' => $this->t('Business'),
      'status' => $this->t('Status'),
      'campaign' => $this->t('Campaign'),
      'authorized' => $this->t('Authorized'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\famtastic_pipeline\Entity\Prospect $entity */
    return [
      'id' => $entity->id(),
      'business' => $entity->toLink($entity->label() ?: '(no name)'),
      'status' => $entity->get('status')->value,
      'campaign' => $entity->get('campaign')->value,
      'authorized' => $entity->get('authorized')->value ? $this->t('Yes') : $this->t('No'),
    ] + parent::buildRow($entity);
  }

}
