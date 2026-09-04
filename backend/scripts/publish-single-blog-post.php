<?php

/**
 * @file
 * Idempotently create/update or delete ONE blog_post node from a JSON payload.
 *
 * This is the companion to seed-demand-content.php: that script bulk-imports
 * the 64-article manifest; this one is the single-post publish primitive that
 * scripts/publish-blog-draft.py drives for the ongoing Blog Factory (recipe
 * step 6, docs/playbook/RECIPES/BLOG_FACTORY.md).
 *
 * Run via SSH against production, from backend/ (mirrors seed-demand-content.php):
 *   vendor/bin/drush php:script scripts/publish-single-blog-post.php -- /path/to/payload.json
 *
 * Payload shape (produced by publish-blog-draft.py):
 * {
 *   "action": "publish" | "delete",
 *   "content_key": "what-does-199-website-include",
 *   "slug": "what-does-199-website-include",
 *   "title": "...",
 *   "body_html": "<p>...</p>",
 *   "excerpt": "...",
 *   "category": "get-paid",
 *   "category_label": "Get Paid",
 *   "tags": [{"key": "pricing", "label": "Pricing"}, ...],
 *   "author_uid": 1,
 *   "meta_title": "...",
 *   "meta_description": "...",
 *   "word_count": 374,
 *   "status": 1
 * }
 *
 * Idempotency: looked up by field_content_key first (exact machine key match,
 * same lookup seed-demand-content.php uses), falling back to an exact title
 * match. A second run with the same content_key updates the existing node
 * instead of creating a duplicate — never creates twice.
 *
 * Prints one JSON line to stdout: {"action": "created|updated|deleted|not_found",
 * "nid": ..., "uuid": ..., "path": ...} so the calling Python script can parse
 * the result without scraping Drush's human-readable output.
 */

use Drupal\node\Entity\Node;

if (empty($extra[0])) {
  fwrite(STDERR, "Usage: drush php:script publish-single-blog-post.php -- /path/to/payload.json\n");
  exit(1);
}

$payload_path = $extra[0];
if (!is_readable($payload_path)) {
  fwrite(STDERR, "Payload not readable: $payload_path\n");
  exit(1);
}

$payload = json_decode((string) file_get_contents($payload_path), TRUE, 512, JSON_THROW_ON_ERROR);

$required_blog_fields = [
  'field_blog_category', 'field_blog_tags', 'field_author', 'field_excerpt',
  'field_meta_description', 'field_meta_title', 'field_word_count',
  'field_content_key',
];
$field_manager = \Drupal::service('entity_field.manager');
$blog_fields = $field_manager->getFieldDefinitions('node', 'blog_post');
$missing = array_values(array_filter($required_blog_fields, fn ($name) => !isset($blog_fields[$name])));
if ($missing) {
  throw new RuntimeException('Import Drupal configuration before publishing. Missing blog fields: ' . implode(', ', $missing));
}

$node_storage = \Drupal::entityTypeManager()->getStorage('node');
$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

$content_key = $payload['content_key'] ?? NULL;
if (!$content_key) {
  throw new RuntimeException('Payload missing content_key.');
}

$load_by_key = function (string $key, string $title = '') use ($node_storage) {
  $matches = $node_storage->loadByProperties(['type' => 'blog_post', 'field_content_key' => $key]);
  if ($matches) {
    return reset($matches);
  }
  if ($title !== '') {
    $matches = $node_storage->loadByProperties(['type' => 'blog_post', 'title' => $title]);
    if ($matches) {
      return reset($matches);
    }
  }
  return NULL;
};

$action = $payload['action'] ?? 'publish';

// Deliberately avoid exit() anywhere below: calling exit() from inside a
// `drush php:script` eval'd file makes Drush itself report "command
// terminated abnormally" even though the script's own output is correct —
// confirmed while proving this script out (a test node's create+delete round
// trip left a stray non-zero exit and warning despite the delete visibly
// succeeding). Structuring as if/elseif and letting the file finish reading
// naturally avoids the false warning entirely.
if ($action === 'delete') {
  $node = $load_by_key($content_key, $payload['title'] ?? '');
  if (!$node) {
    echo json_encode(['action' => 'not_found', 'content_key' => $content_key], JSON_PRETTY_PRINT) . "\n";
  }
  else {
    $nid = (int) $node->id();
    $node->delete();
    echo json_encode(['action' => 'deleted', 'nid' => $nid, 'content_key' => $content_key], JSON_PRETTY_PRINT) . "\n";
  }
}
elseif ($action === 'publish') {
  $upsert_term = function (string $vid, string $name) use ($term_storage) {
    $matches = $term_storage->loadByProperties(['vid' => $vid, 'name' => $name]);
    $term = $matches ? reset($matches) : $term_storage->create(['vid' => $vid, 'name' => $name]);
    if ($term->isNew()) {
      $term->save();
    }
    return $term;
  };

  $category_term = $upsert_term('blog_categories', $payload['category_label']);
  $tag_terms = array_map(fn ($tag) => $upsert_term('blog_tags', $tag['label']), $payload['tags'] ?? []);

  $node = $load_by_key($content_key, $payload['title'] ?? '');
  $created = !$node;
  $node = $node ?: Node::create(['type' => 'blog_post']);

  $node->setTitle($payload['title']);
  $node->set('field_content_key', $content_key);
  $node->set('body', ['value' => $payload['body_html'], 'format' => 'basic_html']);
  $node->set('field_excerpt', $payload['excerpt']);
  $node->set('field_blog_category', $category_term->id());
  $node->set('field_blog_tags', array_map(fn ($term) => ['target_id' => $term->id()], $tag_terms));
  $node->set('field_meta_title', $payload['meta_title']);
  $node->set('field_meta_description', $payload['meta_description']);
  $node->set('field_word_count', (int) $payload['word_count']);
  if (!empty($payload['author_uid']) && $node->hasField('field_author')) {
    $node->set('field_author', (int) $payload['author_uid']);
  }
  // Set the base field explicitly rather than relying on the bundle default —
  // same reasoning as seed-demand-content.php: lean local installs can
  // override Node::setPublished(FALSE).
  $node->set('status', (int) !empty($payload['status']));
  $node->set('path', ['alias' => '/blog/' . ($payload['slug'] ?? $content_key), 'pathauto' => 0]);
  $node->save();

  echo json_encode([
    'action' => $created ? 'created' : 'updated',
    'nid' => (int) $node->id(),
    'uuid' => $node->uuid(),
    'status' => (int) $node->isPublished(),
    'path' => '/blog/' . ($payload['slug'] ?? $content_key),
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
else {
  throw new RuntimeException("Unknown action: $action");
}
