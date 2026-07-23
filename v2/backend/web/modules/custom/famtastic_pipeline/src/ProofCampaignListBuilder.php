<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for proof campaigns.
 */
class ProofCampaignListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'id' => $this->t('ID'),
      'campaign' => $this->t('Campaign'),
      'business' => $this->t('Business'),
      'status' => $this->t('Status'),
      'expires_at' => $this->t('Expires'),
      'selected' => $this->t('Selected'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\famtastic_pipeline\Entity\ProofCampaign $entity */
    $expires = (int) ($entity->get('expires_at')->value ?? 0);
    $selected_variant = $entity->get('selected_variant')->value;
    $selected_package = $entity->get('selected_package')->value;
    $selected = $selected_variant
      ? sprintf('%s / %s', $selected_variant, $selected_package ?: '—')
      : '—';
    return [
      'id' => $entity->id(),
      'campaign' => $entity->toLink($entity->get('campaign_id')->value ?: '(no id)'),
      'business' => $entity->get('business_name')->value,
      'status' => $entity->get('status')->value,
      'expires_at' => $expires ? \Drupal::service('date.formatter')->format($expires, 'short') : '—',
      'selected' => $selected,
    ] + parent::buildRow($entity);
  }

}
