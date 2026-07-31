<?php

declare(strict_types=1);

namespace Drupal\famtastic_pipeline\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Project entity — the fulfillment / delivery record.
 *
 * Holds the generated Site Studio request (human brief + machine JSON) and the
 * fields an administrator records manually in V1: Site Studio job id, repo,
 * proof URL, revision notes, approval status, live URL, delivery status.
 *
 * @ContentEntityType(
 *   id = "famtastic_project",
 *   label = @Translation("Project"),
 *   label_collection = @Translation("Projects"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\famtastic_pipeline\ProjectListBuilder",
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
 *   base_table = "famtastic_project",
 *   admin_permission = "administer famtastic pipeline",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *   },
 *   links = {
 *     "collection" = "/admin/famtastic/project",
 *     "canonical" = "/admin/famtastic/project/{famtastic_project}",
 *     "add-form" = "/admin/famtastic/project/add",
 *     "edit-form" = "/admin/famtastic/project/{famtastic_project}/edit",
 *     "delete-form" = "/admin/famtastic/project/{famtastic_project}/delete",
 *   },
 * )
 */
class Project extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if ($this->get('delivery_status')->isEmpty()) {
      $this->set('delivery_status', 'draft');
    }
    if ($this->get('approval_status')->isEmpty()) {
      $this->set('approval_status', 'pending');
    }
    $this->set('label', sprintf('Project #%s', $this->id() ?? 'new'));
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $short = static function (string $label) {
      return BaseFieldDefinition::create('string')
        ->setLabel($label)->setSetting('max_length', 255)
        ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);
    };

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel('Label')->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['prospect_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Prospect')->setSetting('target_type', 'famtastic_prospect')
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);
    $fields['order_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Order')->setSetting('target_type', 'famtastic_order')
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);
    $fields['intake_ref'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Intake')->setSetting('target_type', 'famtastic_intake')
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    // Generated Site Studio request.
    $fields['studio_brief'] = BaseFieldDefinition::create('string_long')
      ->setLabel('Site Studio brief (human-readable)')
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);
    $fields['studio_json'] = BaseFieldDefinition::create('string_long')
      ->setLabel('Site Studio request (machine JSON)')
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);

    // Administrator-recorded delivery fields (deliverable 17).
    $fields['studio_job_id'] = $short('Site Studio job ID');
    $fields['repo_url'] = $short('Repository URL');
    $fields['proof_url'] = $short('Proof URL');
    $fields['live_url'] = $short('Live URL');
    $fields['delivery_status'] = $short('Delivery status')
      ->setDefaultValue('draft')
      ->setDescription(t('draft, request_generated, submitted, proof_delivered, revision, approved, launched.'));
    $fields['revision_notes'] = BaseFieldDefinition::create('string_long')
      ->setLabel('Revision notes')
      ->setDisplayConfigurable('form', TRUE)->setDisplayConfigurable('view', TRUE);
    $fields['revision_count'] = BaseFieldDefinition::create('integer')
      ->setLabel('Revision rounds used')
      ->setDefaultValue(0)
      ->setDisplayConfigurable('view', TRUE);
    $fields['revision_limit'] = BaseFieldDefinition::create('integer')
      ->setLabel('Included revision rounds')
      ->setDefaultValue(1)
      ->setDisplayConfigurable('view', TRUE);
    $fields['release_sha'] = $short('Approved release SHA');
    $fields['artifact_checksum'] = $short('Approved artifact checksum');
    $fields['approval_status'] = $short('Approval status')
      ->setDefaultValue('pending')
      ->setDescription(t('pending, revision_requested, approved.'));
    $fields['approved_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Approved at')->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')->setLabel('Created');
    $fields['changed'] = BaseFieldDefinition::create('changed')->setLabel('Changed');

    return $fields;
  }

}
