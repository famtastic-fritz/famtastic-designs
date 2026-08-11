<?php

/**
 * @file
 * Idempotently imports the canonical FAMtastic demand-content manifest.
 *
 * Run after configuration import, from backend/:
 *   vendor/bin/drush php:script scripts/seed-demand-content.php
 *
 * Draft is the fail-safe. A post can be published only when both its manifest
 * status is "published" and approval.broad_publish_approved is true.
 */

use Drupal\node\Entity\Node;

$manifest_path = dirname(__DIR__) . '/config/famtastic-content-series.json';
$manifest = json_decode((string) file_get_contents($manifest_path), TRUE, 512, JSON_THROW_ON_ERROR);
$entity_type_manager = \Drupal::entityTypeManager();
$node_storage = $entity_type_manager->getStorage('node');
$term_storage = $entity_type_manager->getStorage('taxonomy_term');
$field_manager = \Drupal::service('entity_field.manager');
$broad_publish_approved = !empty($manifest['approval']['broad_publish_approved']);
$counts = ['terms_created' => 0, 'terms_updated' => 0, 'faqs_created' => 0, 'faqs_updated' => 0, 'posts_created' => 0, 'posts_updated' => 0];

$required_blog_fields = [
  'field_blog_category', 'field_blog_series', 'field_blog_tags',
  'field_capability_keys', 'field_content_key', 'field_cta_link',
  'field_cta_text', 'field_excerpt', 'field_meta_description',
  'field_meta_title', 'field_related_faqs', 'field_series_order',
  'field_seo_brief', 'field_word_count',
];
$blog_fields = $field_manager->getFieldDefinitions('node', 'blog_post');
$missing = array_values(array_filter($required_blog_fields, fn ($name) => !isset($blog_fields[$name])));
if ($missing) {
  throw new RuntimeException('Import Drupal configuration before seeding. Missing blog fields: ' . implode(', ', $missing));
}
if (!isset($field_manager->getFieldDefinitions('node', 'faq_item')['field_content_key'])) {
  throw new RuntimeException('Import Drupal configuration before seeding. Missing faq_item.field_content_key.');
}

$upsert_term = function (string $vid, string $name, string $description = '') use ($term_storage, &$counts) {
  $matches = $term_storage->loadByProperties(['vid' => $vid, 'name' => $name]);
  $term = $matches ? reset($matches) : $term_storage->create(['vid' => $vid, 'name' => $name]);
  $created = $term->isNew();
  if ($description !== '' && $term->hasField('description')) {
    $term->set('description', ['value' => $description, 'format' => 'plain_text']);
  }
  $term->save();
  $counts[$created ? 'terms_created' : 'terms_updated']++;
  return $term;
};

$load_by_key = function (string $bundle, string $key, string $title = '') use ($node_storage) {
  $matches = $node_storage->loadByProperties(['type' => $bundle, 'field_content_key' => $key]);
  if ($matches) {
    return reset($matches);
  }
  // Version-one demand records used different machine keys for several pillar
  // articles. Reuse an exact-title match so upgrading the library does not
  // duplicate those drafts.
  if ($title !== '') {
    $matches = $node_storage->loadByProperties(['type' => $bundle, 'title' => $title]);
    if ($matches) {
      return reset($matches);
    }
  }
  return NULL;
};

$categories = [];
foreach ($manifest['categories'] as $item) {
  $categories[$item['key']] = $upsert_term('blog_categories', $item['label'], $item['description'] ?? '');
}
$tags = [];
foreach ($manifest['tags'] as $item) {
  $tags[$item['key']] = $upsert_term('blog_tags', $item['label']);
}
$series_terms = [];
foreach ($manifest['series'] as $item) {
  $series_terms[$item['key']] = $upsert_term('blog_series', $item['title'], $item['thesis'] ?? '');
}

$faq_categories = [];
$faq_nodes = [];
foreach ($manifest['faqs'] as $index => $item) {
  $category_key = $item['category'];
  if (!isset($faq_categories[$category_key])) {
    $label = $categories[$category_key]?->label() ?? ucwords(str_replace('-', ' ', $category_key));
    $faq_categories[$category_key] = $upsert_term('faq_categories', $label);
  }
  $node = $load_by_key('faq_item', $item['key'], $item['question']) ?: Node::create(['type' => 'faq_item']);
  $created = $node->isNew();
  $node->setTitle($item['question']);
  $node->set('field_content_key', $item['key']);
  $node->set('field_answer', ['value' => $item['answer_html'], 'format' => 'basic_html']);
  $node->set('field_faq_category', $faq_categories[$category_key]->id());
  if ($node->hasField('field_sort_order')) {
    $node->set('field_sort_order', $index + 1);
  }
  // Set the base field explicitly. This also behaves consistently in lean
  // local Drupal installs where Node::setPublished(FALSE) can be overridden
  // by the bundle's default publication value.
  $node->set('status', (int) ($broad_publish_approved && $item['status'] === 'published'));
  $node->set('path', ['alias' => '/faq/' . str_replace('_', '-', $item['key']), 'pathauto' => 0]);
  $node->save();
  $faq_nodes[$item['key']] = $node;
  $counts[$created ? 'faqs_created' : 'faqs_updated']++;
}

foreach ($manifest['posts'] as $item) {
  $node = $load_by_key('blog_post', $item['key'], $item['title']) ?: Node::create(['type' => 'blog_post']);
  $created = $node->isNew();
  $node->setTitle($item['title']);
  $node->set('field_content_key', $item['key']);
  $node->set('body', ['value' => $item['body_html'], 'format' => 'basic_html']);
  $node->set('field_excerpt', $item['excerpt']);
  $node->set('field_blog_category', $categories[$item['category']]->id());
  $node->set('field_blog_series', $series_terms[$item['series']]->id());
  $node->set('field_series_order', $item['sequence']);
  $node->set('field_blog_tags', array_map(fn ($key) => ['target_id' => $tags[$key]->id()], $item['tags']));
  $node->set('field_capability_keys', array_map(fn ($key) => ['value' => $key], $item['capabilities']));
  $node->set('field_meta_title', $item['meta_title']);
  $node->set('field_meta_description', $item['meta_description']);
  $node->set('field_word_count', $item['word_count']);
  $node->set('field_seo_brief', json_encode([
    'primary_keyword' => $item['primary_keyword'],
    'secondary_keywords' => $item['secondary_keywords'],
    'search_intent' => $item['search_intent'],
    'content_template' => $item['content_template'],
    'target_audience' => $item['target_audience'],
    'evidence_boundary' => $item['evidence_boundary'],
    'canonical_url' => $item['canonical_url'],
    'open_graph' => ['title' => $item['og_title'], 'description' => $item['og_description']],
    'schema_types' => $item['schema_types'],
    'sources' => $item['sources'],
    'review_status' => $item['review_status'],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  $node->set('field_cta_text', $item['cta']['label']);
  $node->set('field_cta_link', ['uri' => 'internal:' . $item['cta']['href'], 'title' => $item['cta']['label']]);
  $node->set('field_related_faqs', array_values(array_map(fn ($key) => ['target_id' => $faq_nodes[$key]->id()], $item['faqs'])));
  $node->set('status', (int) ($broad_publish_approved && $item['status'] === 'published'));
  $node->set('path', ['alias' => '/blog/' . $item['slug'], 'pathauto' => 0]);
  $node->save();
  $counts[$created ? 'posts_created' : 'posts_updated']++;
}

echo "FAMtastic demand content seed complete.\n";
echo json_encode([
  'manifest_version' => $manifest['version'],
  'broad_publish_approved' => $broad_publish_approved,
  'counts' => $counts,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
