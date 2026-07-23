<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Order entity — one purchase attempt for a prospect.
 *
 * Kept separate from the prospect so payment state (and Stripe idempotency) is
 * an immutable transactional record. Payment status is only ever advanced by a
 * signature-verified webhook or a server-side Stripe retrieve — never by the
 * browser success redirect.
 *
 * @ContentEntityType(
 *   id = "famtastic_order",
 *   label = @Translation("Order"),
 *   label_collection = @Translation("Orders"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\famtastic_pipeline\OrderListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *   },
 *   base_table = "famtastic_order",
 *   admin_permission = "administer famtastic pipeline",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *   },
 *   links = {
 *     "collection" = "/admin/famtastic/order",
 *     "canonical" = "/admin/famtastic/order/{famtastic_order}",
 *     "add-form" = "/admin/famtastic/order/add",
 *     "edit-form" = "/admin/famtastic/order/{famtastic_order}/edit",
 *     "delete-form" = "/admin/famtastic/order/{famtastic_order}/delete",
 *   },
 * )
 */
class Order extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if ($this->get('payment_status')->isEmpty()) {
      $this->set('payment_status', 'pending');
    }
    $this->set('label', sprintf('Order #%s — %s (%s)', $this->id() ?? 'new', $this->get('package')->value ?: 'basic_199', $this->get('payment_status')->value));
  }

  /**
   * Returns TRUE when this order has been verified as paid.
   */
  public function isPaid(): bool {
    return $this->get('payment_status')->value === 'paid';
  }

  /**
   * Records a processed Stripe event id; returns FALSE if already processed.
   */
  public function markEventProcessed(string $event_id): bool {
    $raw = $this->get('stripe_event_ids')->value;
    $ids = $raw ? (array) json_decode($raw, TRUE) : [];
    if (in_array($event_id, $ids, TRUE)) {
      return FALSE;
    }
    $ids[] = $event_id;
    $this->set('stripe_event_ids', json_encode(array_values($ids)));
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel('Label')
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['prospect_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Prospect')
      ->setSetting('target_type', 'famtastic_prospect')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['package'] = BaseFieldDefinition::create('string')
      ->setLabel('Package')
      ->setSetting('max_length', 64)
      ->setDefaultValue('basic_199')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['amount'] = BaseFieldDefinition::create('integer')
      ->setLabel('Amount (minor units / cents)')
      ->setDefaultValue(19900)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['currency'] = BaseFieldDefinition::create('string')
      ->setLabel('Currency')
      ->setSetting('max_length', 8)
      ->setDefaultValue('usd')
      ->setDisplayConfigurable('form', TRUE);

    $fields['stripe_checkout_session_id'] = BaseFieldDefinition::create('string')
      ->setLabel('Stripe Checkout Session ID')
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['stripe_payment_intent_id'] = BaseFieldDefinition::create('string')
      ->setLabel('Stripe PaymentIntent ID')
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['payment_status'] = BaseFieldDefinition::create('string')
      ->setLabel('Payment status')
      ->setSetting('max_length', 32)
      ->setDefaultValue('pending')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    // JSON array of processed Stripe event ids (webhook idempotency).
    $fields['stripe_event_ids'] = BaseFieldDefinition::create('string_long')
      ->setLabel('Processed Stripe event IDs (JSON)');

    $fields['paid_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Paid at')
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')->setLabel('Created');
    $fields['changed'] = BaseFieldDefinition::create('changed')->setLabel('Changed');

    return $fields;
  }

}
