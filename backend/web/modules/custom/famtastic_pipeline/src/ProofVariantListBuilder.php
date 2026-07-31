<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for proof variants.
 */
class ProofVariantListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'id' => $this->t('ID'),
      'direction' => $this->t('Direction'),
      'direction_id' => $this->t('Key'),
      'campaign' => $this->t('Campaign'),
      'preview_url' => $this->t('Preview URL'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\famtastic_pipeline\Entity\ProofVariant $entity */
    $campaign_id = (int) ($entity->get('campaign_id')->target_id ?? 0);
    return [
      'id' => $entity->id(),
      'direction' => $entity->toLink($entity->label() ?: '(no name)'),
      'direction_id' => $entity->get('direction_id')->value,
      'campaign' => $campaign_id ?: '—',
      'preview_url' => $entity->get('preview_url')->value,
    ] + parent::buildRow($entity);
  }

}
