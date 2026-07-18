<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Intake entity — the post-payment website questionnaire + assets.
 *
 * Only writable after the owning order is verified paid (enforced by the
 * controller). Holds every field the Site Studio request needs beyond the
 * business basics already on the prospect.
 *
 * @ContentEntityType(
 *   id = "famtastic_intake",
 *   label = @Translation("Intake"),
 *   label_collection = @Translation("Intakes"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\famtastic_pipeline\IntakeListBuilder",
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
 *   base_table = "famtastic_intake",
 *   admin_permission = "administer famtastic pipeline",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *   },
 *   links = {
 *     "collection" = "/admin/famtastic/intake",
 *     "canonical" = "/admin/famtastic/intake/{famtastic_intake}",
 *     "add-form" = "/admin/famtastic/intake/add",
 *     "edit-form" = "/admin/famtastic/intake/{famtastic_intake}/edit",
 *     "delete-form" = "/admin/famtastic/intake/{famtastic_intake}/delete",
 *   },
 * )
 */
class Intake extends ContentEntityBase {

  /**
   * The intake text fields, in Site Studio order (for the builder + forms).
   */
  public const TEXT_FIELDS = [
    'ideal_customer',
    'customer_problem',
    'desired_outcome',
    'primary_goal',
    'primary_cta',
    'secondary_cta',
    'services',
    'about',
    'differentiators',
    'credentials',
    'testimonials',
    'faqs',
    'required_sections',
    'info_to_avoid',
    'brand_colors',
    'style_preferences',
    'reference_sites',
    'existing_domain',
    'domain_registrar',
    'existing_website',
  ];

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    $this->set('label', sprintf('Intake #%s', $this->id() ?? 'new'));
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $long = static function (string $label) {
      return BaseFieldDefinition::create('string_long')
        ->setLabel($label)
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);
    };
    $short = static function (string $label) {
      return BaseFieldDefinition::create('string')
        ->setLabel($label)
        ->setSetting('max_length', 255)
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE);
    };

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel('Label')->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['prospect_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Prospect')->setSetting('target_type', 'famtastic_prospect')
      ->setRequired(TRUE)->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);
    $fields['order_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Order')->setSetting('target_type', 'famtastic_order')
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    // Positioning.
    $fields['ideal_customer'] = $long('Ideal customer');
    $fields['customer_problem'] = $long('Customer problem');
    $fields['desired_outcome'] = $long('Desired customer outcome');
    $fields['primary_goal'] = $short('Primary website goal');
    $fields['primary_cta'] = $short('Primary call to action');
    $fields['secondary_cta'] = $short('Secondary call to action');

    // Content.
    $fields['services'] = $long('Services');
    $fields['about'] = $long('About');
    $fields['differentiators'] = $long('Differentiators');
    $fields['credentials'] = $long('Credentials');
    $fields['testimonials'] = $long('Testimonials');
    $fields['faqs'] = $long('FAQs');
    $fields['required_sections'] = $long('Required website sections');
    $fields['info_to_avoid'] = $long('Information or claims to avoid');

    // Brand / style.
    $fields['brand_colors'] = $short('Brand colors');
    $fields['style_preferences'] = $long('Style preferences');
    $fields['reference_sites'] = $long('Reference websites');

    // Domain.
    $fields['existing_domain'] = $short('Existing domain');
    $fields['domain_registrar'] = $short('Domain registrar');
    $fields['existing_website'] = $short('Existing website');

    // Assets (private managed files) + ownership confirmation.
    $fields['asset_refs'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Uploaded assets')
      ->setSetting('target_type', 'file')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('view', TRUE);
    $fields['asset_ownership_confirmed'] = BaseFieldDefinition::create('boolean')
      ->setLabel('Asset ownership confirmed')
      ->setDefaultValue(FALSE)
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    $fields['submitted_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Submitted at')->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')->setLabel('Created');
    $fields['changed'] = BaseFieldDefinition::create('changed')->setLabel('Changed');

    return $fields;
  }

}
