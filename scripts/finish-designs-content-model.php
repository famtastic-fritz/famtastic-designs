<?php

/**
 * @file
 * Create the remaining Designs content types: SiteBlock, ServiceOffering,
 * CampaignPage, and Testimonial.
 *
 * Run with: ./vendor/bin/drush php:script scripts/finish-designs-content-model.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

// Required field-type modules.
\Drupal::service('module_installer')->install(['options', 'path'], TRUE);

function ensure_node_type(string $type, string $name, string $description, string $title_label = 'Title'): void {
  $node_type = NodeType::load($type);
  if (!$node_type) {
    NodeType::create([
      'type' => $type,
      'name' => $name,
      'description' => $description,
      'title_label' => $title_label,
      'new_revision' => FALSE,
    ])->save();
  }
}

function ensure_body_storage(): void {
  $storage = FieldStorageConfig::loadByName('node', 'body');
  if (!$storage) {
    FieldStorageConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'type' => 'text_with_summary',
      'cardinality' => 1,
    ])->save();
  }
}

function ensure_body_instance(string $bundle, string $label = 'Body'): void {
  $body = FieldConfig::loadByName('node', $bundle, 'body');
  if (!$body) {
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => $label,
    ])->save();
  }
}

function create_field(
  string $field_name,
  string $bundle,
  string $type,
  string $label,
  string $widget,
  ?string $formatter = NULL,
  array $settings = [],
  int $cardinality = 1
): void {
  $storage = FieldStorageConfig::loadByName('node', $field_name);
  if (!$storage) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $type,
      'cardinality' => $cardinality,
      'settings' => $settings['storage'] ?? [],
    ])->save();
  }

  $config = FieldConfig::loadByName('node', $bundle, $field_name);
  if (!$config) {
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => $label,
      'settings' => $settings['field'] ?? [],
    ])->save();
  }

  $form_display = \Drupal::service('entity_display.repository')->getFormDisplay('node', $bundle);
  $form_display->setComponent($field_name, ['type' => $widget, 'settings' => $settings['widget'] ?? []]);
  $form_display->save();

  $view_display = \Drupal::service('entity_display.repository')->getViewDisplay('node', $bundle);
  if ($formatter) {
    $view_display->setComponent($field_name, ['type' => $formatter, 'settings' => $settings['view'] ?? []]);
  }
  else {
    $view_display->setComponent($field_name);
  }
  $view_display->save();
}

// ---------------------------------------------------------------------------
// 1. SiteBlock (editable copy snippets).
// ---------------------------------------------------------------------------
ensure_node_type('site_block', 'Site Block', 'Editable copy blocks for pages and sections.', 'Label');
create_field('field_page', 'site_block', 'string', 'Page key', 'string_textfield');
create_field('field_section', 'site_block', 'string', 'Section key', 'string_textfield');
create_field('field_key_name', 'site_block', 'string', 'Key name', 'string_textfield');
create_field('field_value_text', 'site_block', 'text_long', 'Value', 'text_textarea', 'text_default');
create_field('field_value_type', 'site_block', 'list_string', 'Value type', 'options_select', 'list_default', [
  'storage' => ['allowed_values' => [
    'text' => 'Plain text',
    'html' => 'HTML',
    'markdown' => 'Markdown',
    'url' => 'URL',
    'image_url' => 'Image URL',
  ]],
]);
create_field('field_updated_by', 'site_block', 'string', 'Updated by', 'string_textfield', 'string');

// ---------------------------------------------------------------------------
// 2. ServiceOffering.
// ---------------------------------------------------------------------------
ensure_node_type('service_offering', 'Service Offering', 'Services listed on /services and in campaigns.', 'Service name');
ensure_body_instance('service_offering', 'Description');
create_field('field_slug', 'service_offering', 'string', 'URL slug', 'string_textfield');
create_field('field_short_pitch', 'service_offering', 'text_long', 'Short pitch', 'text_textarea', 'text_default');
create_field('field_starting_price', 'service_offering', 'integer', 'Starting price (cents)', 'number');
create_field('field_deliverables', 'service_offering', 'string_long', 'Deliverables (JSON)', 'string_textarea');
create_field('field_duration_days', 'service_offering', 'integer', 'Duration (days)', 'number');
create_field('field_icon', 'service_offering', 'string', 'Icon name / path', 'string_textfield');
create_field('field_is_active', 'service_offering', 'boolean', 'Active', 'boolean_checkbox');
create_field('field_sort_order', 'service_offering', 'integer', 'Sort order', 'number');

// ---------------------------------------------------------------------------
// 3. CampaignPage.
// ---------------------------------------------------------------------------
ensure_node_type('campaign_page', 'Campaign Page', 'Landing pages for verticals and promotions.', 'Campaign title');
create_field('field_campaign_key', 'campaign_page', 'string', 'Campaign key', 'string_textfield');
create_field('field_slug', 'campaign_page', 'string', 'URL slug', 'string_textfield');
create_field('field_accent_color', 'campaign_page', 'string', 'Accent color (hex)', 'string_textfield');
create_field('field_hero_headline', 'campaign_page', 'text_long', 'Hero headline', 'text_textarea', 'text_default');
create_field('field_hero_body', 'campaign_page', 'text_long', 'Hero body', 'text_textarea', 'text_default');
create_field('field_cta_label', 'campaign_page', 'string', 'CTA label', 'string_textfield');
create_field('field_cta_action', 'campaign_page', 'list_string', 'CTA action', 'options_select', 'list_default', [
  'storage' => ['allowed_values' => [
    'book_call' => 'Book a call',
    'pay_now' => 'Pay now',
    'claim_proof' => 'Claim proof',
  ]],
]);
create_field('field_social_proof', 'campaign_page', 'entity_reference', 'Social proof', 'entity_reference_autocomplete', 'entity_reference_label', [
  'storage' => ['target_type' => 'node'],
  'field' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['proof_artifact' => 'proof_artifact']]],
], -1);
create_field('field_pricing_snapshot', 'campaign_page', 'string_long', 'Pricing snapshot (JSON)', 'string_textarea');

// ---------------------------------------------------------------------------
// 4. Testimonial.
// ---------------------------------------------------------------------------
ensure_node_type('testimonial', 'Testimonial', 'Quotes and social proof snippets.', 'Author name');
create_field('field_role', 'testimonial', 'string', 'Role / title', 'string_textfield');
create_field('field_quote', 'testimonial', 'text_long', 'Quote', 'text_textarea', 'text_default');
create_field('field_source', 'testimonial', 'list_string', 'Source', 'options_select', 'list_default', [
  'storage' => ['allowed_values' => [
    'video' => 'Video',
    'email' => 'Email',
    'survey' => 'Survey',
    'linkedin' => 'LinkedIn',
  ]],
]);
create_field('field_proof_reference', 'testimonial', 'entity_reference', 'Related proof', 'entity_reference_autocomplete', 'entity_reference_label', [
  'storage' => ['target_type' => 'node'],
  'field' => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => ['proof_artifact' => 'proof_artifact']]],
]);
create_field('field_is_active', 'testimonial', 'boolean', 'Active', 'boolean_checkbox');

print "Designs content model complete.\n";
