<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Proof Campaign entity.
 *
 * A proof campaign presents a prospect with three AI-generated design
 * directions (ProofVariant entities) and lets them pick one plus a package
 * before a 7-day expiry. Stripe conversion metadata lives on the campaign so
 * the webhook can mark it converted without touching the existing intake flow.
 *
 * @ContentEntityType(
 *   id = "proof_campaign",
 *   label = @Translation("Proof Campaign"),
 *   label_collection = @Translation("Proof Campaigns"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\famtastic_pipeline\ProofCampaignListBuilder",
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
 *   base_table = "proof_campaign",
 *   admin_permission = "administer famtastic pipeline",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "campaign_id",
 *   },
 *   links = {
 *     "collection" = "/admin/famtastic/proof-campaigns",
 *     "canonical" = "/admin/famtastic/proof-campaigns/{proof_campaign}",
 *     "add-form" = "/admin/famtastic/proof-campaigns/add",
 *     "edit-form" = "/admin/famtastic/proof-campaigns/{proof_campaign}/edit",
 *     "delete-form" = "/admin/famtastic/proof-campaigns/{proof_campaign}/delete",
 *   },
 * )
 */
class ProofCampaign extends ContentEntityBase {

  /**
   * Allowed campaign statuses.
   */
  public const STATUSES = ['active', 'expired', 'converted', 'archived'];

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if ($this->get('status')->isEmpty()) {
      $this->set('status', 'active');
    }
    if ($this->get('expires_at')->isEmpty()) {
      // Default expiry: 7 days from now.
      $this->set('expires_at', \Drupal::time()->getRequestTime() + 7 * 24 * 60 * 60);
    }
  }

  /**
   * Returns TRUE when the campaign has passed its expiry timestamp.
   */
  public function isExpired(): bool {
    $expires = (int) ($this->get('expires_at')->value ?? 0);
    return $expires > 0 && $expires < \Drupal::time()->getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['campaign_id'] = BaseFieldDefinition::create('string')
      ->setLabel('Campaign ID')
      ->setDescription(t('Public unique identifier used in proof URLs and API calls.'))
      ->setSetting('max_length', 64)
      ->setRequired(TRUE)
      ->addConstraint('UniqueField')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['prospect_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Prospect')
      ->setSetting('target_type', 'famtastic_prospect')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['business_name'] = BaseFieldDefinition::create('string')
      ->setLabel('Business name')
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel('Status')
      ->setSetting('allowed_values', [
        'active' => 'Active',
        'expired' => 'Expired',
        'converted' => 'Converted',
        'archived' => 'Archived',
      ])
      ->setDefaultValue('active')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['expires_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Expires at')
      ->setDescription(t('Defaults to 7 days after creation.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['selected_variant'] = BaseFieldDefinition::create('string')
      ->setLabel('Selected variant')
      ->setDescription(t('Direction id chosen by the prospect: a, b, or c.'))
      ->setSetting('max_length', 8)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['selected_package'] = BaseFieldDefinition::create('string')
      ->setLabel('Selected package')
      ->setDescription(t('essential_199 or business_499.'))
      ->setSetting('max_length', 32)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['stripe_order_id'] = BaseFieldDefinition::create('string')
      ->setLabel('Stripe order ID')
      ->setDescription(t('Checkout Session / order id used by the webhook to mark this campaign converted.'))
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['generation_status'] = BaseFieldDefinition::create('string')
      ->setLabel('Generation status')
      ->setSetting('max_length', 32)
      ->setDefaultValue('ready')
      ->setDisplayConfigurable('view', TRUE);

    $fields['studio_job_id'] = BaseFieldDefinition::create('string')
      ->setLabel('Site Studio job ID')
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['callback_event_ids'] = BaseFieldDefinition::create('string_long')
      ->setLabel('Processed callback event IDs');

    $fields['dispatched_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Dispatched at')
      ->setDisplayConfigurable('view', TRUE);

    $fields['ready_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Proofs ready at')
      ->setDisplayConfigurable('view', TRUE);

    $fields['selected_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Selected at')
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel('Created');

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel('Changed');

    return $fields;
  }

}
