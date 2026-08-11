<?php

/**
 * @file
 * Idempotently installs the demand library's editorial fields.
 *
 * This production-safe installer avoids Drupal partial config import, whose
 * dependency validator can fail when the source intentionally contains only a
 * subset of an entity form display's dependencies.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Entity\Entity\EntityFormDisplay;

$ensure_storage = static function (string $name, string $type, array $settings = []): void {
  if (!FieldStorageConfig::loadByName('node', $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $type,
      'settings' => $settings,
      'cardinality' => 1,
      'translatable' => FALSE,
    ])->save();
  }
};

$ensure_field = static function (string $name, string $label, string $description, array $settings = []): void {
  if (!FieldConfig::loadByName('node', 'blog_post', $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => 'blog_post',
      'label' => $label,
      'description' => $description,
      'required' => FALSE,
      'translatable' => FALSE,
      'settings' => $settings,
    ])->save();
  }
};

$ensure_storage('field_seo_brief', 'string_long');
$ensure_storage('field_word_count', 'integer', ['unsigned' => TRUE, 'size' => 'normal']);
$ensure_field(
  'field_seo_brief',
  'SEO and editorial brief',
  'Primary and secondary keywords, intent, template, evidence boundary, canonical URL, schema, sources, and review status from the demand manifest.',
);
$ensure_field(
  'field_word_count',
  'Word count',
  'Validated body word count from the canonical demand manifest.',
  ['min' => 0, 'max' => NULL, 'prefix' => '', 'suffix' => ''],
);

$display = EntityFormDisplay::load('node.blog_post.default');
if (!$display) {
  $display = EntityFormDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'blog_post',
    'mode' => 'default',
    'status' => TRUE,
  ]);
}
$display
  ->setComponent('field_seo_brief', [
    'type' => 'string_textarea',
    'weight' => 17,
    'region' => 'content',
    'settings' => ['rows' => 14, 'placeholder' => ''],
  ])
  ->setComponent('field_word_count', [
    'type' => 'number',
    'weight' => 18,
    'region' => 'content',
    'settings' => ['placeholder' => ''],
  ])
  ->save();

echo "Demand editorial fields are installed.\n";
