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
use Symfony\Component\Yaml\Yaml;

$config_dir = dirname(__DIR__) . '/config/site';
$entity_type_manager = \Drupal::entityTypeManager();
$install_config_entity = static function (string $entity_type, string $id, string $file) use ($config_dir, $entity_type_manager): void {
  $storage = $entity_type_manager->getStorage($entity_type);
  if ($storage->load($id)) {
    return;
  }
  $path = $config_dir . '/' . $file;
  if (!is_file($path)) {
    throw new RuntimeException("Required demand configuration is missing: $path");
  }
  $data = Yaml::parseFile($path);
  unset($data['uuid'], $data['_core']);
  $storage->create($data)->save();
};

foreach (['blog_post', 'faq_item'] as $bundle) {
  $install_config_entity('node_type', $bundle, "node.type.$bundle.yml");
}
foreach (['blog_categories', 'blog_series', 'blog_tags', 'faq_categories'] as $vocabulary) {
  $install_config_entity('taxonomy_vocabulary', $vocabulary, "taxonomy.vocabulary.$vocabulary.yml");
}

$bundle_fields = [
  'blog_post' => [
    'field_blog_category', 'field_blog_series', 'field_blog_tags',
    'field_capability_keys', 'field_content_key', 'field_cta_link',
    'field_cta_text', 'field_excerpt', 'field_meta_description',
    'field_meta_title', 'field_related_faqs', 'field_series_order',
    'field_seo_brief', 'field_word_count',
  ],
  'faq_item' => [
    'field_answer', 'field_content_key', 'field_faq_category',
    'field_sort_order',
  ],
];
foreach ($bundle_fields as $bundle => $field_names) {
  foreach ($field_names as $field_name) {
    $install_config_entity(
      'field_storage_config',
      "node.$field_name",
      "field.storage.node.$field_name.yml",
    );
    $install_config_entity(
      'field_config',
      "node.$bundle.$field_name",
      "field.field.node.$bundle.$field_name.yml",
    );
  }
}

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
