<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Prospect entity.
 *
 * A prospect is a business discovered from public information and contacted via
 * a secure, personalized link. The prospect authenticates with a link token
 * (hashed at rest) — never a Drupal user account. Discovered fields are treated
 * as unverified until the owner confirms them.
 *
 * @ContentEntityType(
 *   id = "famtastic_prospect",
 *   label = @Translation("Prospect"),
 *   label_collection = @Translation("Prospects"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\famtastic_pipeline\ProspectListBuilder",
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
 *   base_table = "famtastic_prospect",
 *   admin_permission = "administer famtastic pipeline",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "business_name",
 *   },
 *   links = {
 *     "collection" = "/admin/famtastic/prospect",
 *     "canonical" = "/admin/famtastic/prospect/{famtastic_prospect}",
 *     "add-form" = "/admin/famtastic/prospect/add",
 *     "edit-form" = "/admin/famtastic/prospect/{famtastic_prospect}/edit",
 *     "delete-form" = "/admin/famtastic/prospect/{famtastic_prospect}/delete",
 *   },
 * )
 */
class Prospect extends ContentEntityBase {

  /**
   * Fields that the owner may confirm/correct, and that are safe to expose.
   */
  public const PUBLIC_BUSINESS_FIELDS = [
    'business_name',
    'business_category',
    'business_description',
    'address',
    'service_area',
    'public_phone',
    'public_email',
    'website_url',
    'hours',
    'social_links',
  ];

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if ($this->get('status')->isEmpty()) {
      $this->set('status', 'new');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $string = static function (string $label, bool $long = FALSE) {
      $field = BaseFieldDefinition::create($long ? 'string_long' : 'string')
        ->setLabel($label)
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);
      if (!$long) {
        $field->setSetting('max_length', 255);
      }
      return $field;
    };

    // Discovered business information (source = public, unverified).
    $fields['business_name'] = $string('Business name')->setRequired(TRUE);
    $fields['business_category'] = $string('Business category');
    $fields['business_description'] = $string('Business description', TRUE);
    $fields['address'] = $string('Address', TRUE);
    $fields['service_area'] = $string('Service area');
    $fields['public_phone'] = $string('Public phone');
    $fields['public_email'] = $string('Public email');
    $fields['website_url'] = $string('Existing website URL');
    $fields['hours'] = $string('Hours', TRUE);
    $fields['social_links'] = $string('Social links (JSON)', TRUE);

    // Outreach / campaign provenance.
    $fields['campaign'] = $string('Campaign');
    $fields['source'] = $string('Discovery source')
      ->setDescription(t('e.g. google, directory, referral, social.'));
    $fields['discovered_at'] = BaseFieldDefinition::create('created')
      ->setLabel('Discovered at');
    // INTERNAL: never serialized into a prospect-facing payload.
    $fields['discovery_notes'] = $string('Internal discovery notes', TRUE)
      ->setDescription(t('Internal only. Never shown to the prospect.'));

    // Secure link token (only the hash is stored).
    $fields['token_hash'] = $string('Token hash')
      ->setDescription(t('SHA-256 of the raw link token. The raw token is never stored.'));
    $fields['token_expires'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Token expires')
      ->setDisplayConfigurable('form', TRUE);
    $fields['token_revoked'] = BaseFieldDefinition::create('boolean')
      ->setLabel('Token revoked')
      ->setDefaultValue(FALSE)
      ->setDisplayConfigurable('form', TRUE);

    // Confirmation / lead capture.
    $fields['contact_name'] = $string('Contact name');
    $fields['contact_method'] = $string('Contact method')
      ->setDescription(t('email, phone, or text.'));
    $fields['contact_value'] = $string('Contact value');
    $fields['authorized'] = BaseFieldDefinition::create('boolean')
      ->setLabel('Authorized to represent business')
      ->setDefaultValue(FALSE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
    $fields['confirmed_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Confirmed at');
    $fields['confirmed_fields'] = $string('Confirmed fields (JSON)', TRUE)
      ->setDescription(t('Map of which business fields the owner confirmed/corrected.'));

    // Lifecycle.
    $fields['status'] = $string('Status')
      ->setDefaultValue('new')
      ->setDescription(t('new, viewed, confirmed, lead, paid, intake_started, intake_complete, submitted_to_studio, proof_ready, revision_requested, approved, launched.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel('Created');
    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel('Changed');

    return $fields;
  }

}
