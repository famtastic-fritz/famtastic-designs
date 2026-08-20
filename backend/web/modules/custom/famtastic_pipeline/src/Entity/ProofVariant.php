<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Proof Variant entity.
 *
 * One core or optional showcase direction generated for a proof campaign.
 * Each variant points at a static HTML proof artifact on disk
 * (backend/web/proofs/<campaign_id>/<direction>/index.html) and carries the
 * design DNA (JSON) that produced it.
 *
 * @ContentEntityType(
 *   id = "proof_variant",
 *   label = @Translation("Proof Variant"),
 *   label_collection = @Translation("Proof Variants"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\famtastic_pipeline\ProofVariantListBuilder",
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
 *   base_table = "proof_variant",
 *   admin_permission = "administer famtastic pipeline",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "direction_name",
 *   },
 *   links = {
 *     "collection" = "/admin/famtastic/proof-variants",
 *     "canonical" = "/admin/famtastic/proof-variants/{proof_variant}",
 *     "add-form" = "/admin/famtastic/proof-variants/add",
 *     "edit-form" = "/admin/famtastic/proof-variants/{proof_variant}/edit",
 *     "delete-form" = "/admin/famtastic/proof-variants/{proof_variant}/delete",
 *   },
 * )
 */
class ProofVariant extends ContentEntityBase {

  /**
   * Allowed direction ids.
   */
  public const DIRECTIONS = ['a', 'b', 'c', 'd', 'e', 'f'];

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['campaign_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Proof campaign')
      ->setSetting('target_type', 'proof_campaign')
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['direction_id'] = BaseFieldDefinition::create('list_string')
      ->setLabel('Direction ID')
      ->setSetting('allowed_values', [
        'a' => 'A',
        'b' => 'B',
        'c' => 'C',
        'd' => 'D',
        'e' => 'E',
        'f' => 'F',
      ])
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['direction_name'] = BaseFieldDefinition::create('string')
      ->setLabel('Direction name')
      ->setSetting('max_length', 255)
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['artifact_path'] = BaseFieldDefinition::create('string')
      ->setLabel('Artifact path')
      ->setDescription(t('Filesystem path to the proof index.html.'))
      ->setSetting('max_length', 512)
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['design_dna'] = BaseFieldDefinition::create('text_long')
      ->setLabel('Design DNA (JSON)')
      ->setDescription(t('JSON payload describing the generated design system for this direction.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['thumbnail_path'] = BaseFieldDefinition::create('string')
      ->setLabel('Thumbnail path')
      ->setSetting('max_length', 512)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['preview_url'] = BaseFieldDefinition::create('string')
      ->setLabel('Preview URL')
      ->setDescription(t('<host>/proofs/<campaign_id>/<direction>/'))
      ->setSetting('max_length', 512)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel('Created');

    return $fields;
  }

}
