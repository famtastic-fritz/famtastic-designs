<?php

/**
 * @file
 * One-time setup script for the Proof Artifact content type and fields.
 *
 * Run with: drush php:scripts scripts/create-proof-artifact.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

// Ensure required modules are installed.
\Drupal::service('module_installer')->install(['link', 'jsonapi'], TRUE);

// 1. Create the content type (idempotent).
$node_type = NodeType::load('proof_artifact');
if (!$node_type) {
  $node_type = NodeType::create([
    'type' => 'proof_artifact',
    'name' => 'Proof Artifact',
    'description' => 'A before/after transformation proof used in the gallery and campaigns.',
    'title_label' => 'Client / Project name',
    'new_revision' => FALSE,
  ]);
  $node_type->save();
}

// Helper to create a field storage + instance.
function create_field(string $field_name, string $entity_type, string $bundle, string $type, string $label, string $widget, array $settings = []): void {
  $field_storage = FieldStorageConfig::loadByName($entity_type, $field_name);
  if (!$field_storage) {
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => $type,
      'cardinality' => 1,
      'settings' => $settings['storage'] ?? [],
    ]);
    $field_storage->save();
  }

  $field_config = FieldConfig::loadByName($entity_type, $bundle, $field_name);
  if (!$field_config) {
    $field_config = FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => $label,
      'settings' => $settings['field'] ?? [],
    ]);
    $field_config->save();
  }

  // Manage form display.
  $form_display = \Drupal::service('entity_display.repository')->getFormDisplay($entity_type, $bundle);
  $form_display->setComponent($field_name, ['type' => $widget]);
  $form_display->save();

  // Manage view display (default formatter).
  $view_display = \Drupal::service('entity_display.repository')->getViewDisplay($entity_type, $bundle);
  $view_display->setComponent($field_name);
  $view_display->save();
}

$bundle = 'proof_artifact';

// Ensure the body field storage + instance exists.
$body_storage = FieldStorageConfig::loadByName('node', 'body');
if (!$body_storage) {
  FieldStorageConfig::create([
    'field_name' => 'body',
    'entity_type' => 'node',
    'type' => 'text_with_summary',
    'cardinality' => 1,
  ])->save();
}
$body = FieldConfig::loadByName('node', $bundle, 'body');
if (!$body) {
  FieldConfig::create([
    'field_name' => 'body',
    'entity_type' => 'node',
    'bundle' => $bundle,
    'label' => 'Detailed case study',
  ])->save();
}

// Industry (plain text).
create_field('field_industry', 'node', $bundle, 'string', 'Industry', 'string_textfield');

// Before URL.
create_field('field_before_url', 'node', $bundle, 'link', 'Before URL', 'link_default');

// After URL.
create_field('field_after_url', 'node', $bundle, 'link', 'After URL', 'link_default');

// Story summary.
create_field('field_story_summary', 'node', $bundle, 'text_long', 'Story summary', 'text_textarea');

// Metrics (JSON stored as plain text for now).
create_field('field_metrics', 'node', $bundle, 'string_long', 'Metrics (JSON)', 'string_textarea');

// Public flag.
create_field('field_is_public', 'node', $bundle, 'boolean', 'Public in gallery', 'boolean_checkbox');

// Published at timestamp.
create_field('field_published_at', 'node', $bundle, 'timestamp', 'Published at', 'datetime_timestamp');

print "Proof Artifact content type ready.\n";
