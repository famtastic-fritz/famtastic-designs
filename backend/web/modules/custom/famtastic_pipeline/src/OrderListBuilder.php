<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * List builder for orders.
 */
class OrderListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'id' => $this->t('ID'),
      'package' => $this->t('Package'),
      'amount' => $this->t('Amount'),
      'status' => $this->t('Payment status'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\famtastic_pipeline\Entity\Order $entity */
    $amount = (int) $entity->get('amount')->value;
    return [
      'id' => $entity->toLink($entity->id()),
      'package' => $entity->get('package')->value,
      'amount' => strtoupper($entity->get('currency')->value ?: 'usd') . ' ' . number_format($amount / 100, 2),
      'status' => $entity->get('payment_status')->value,
    ] + parent::buildRow($entity);
  }

}
