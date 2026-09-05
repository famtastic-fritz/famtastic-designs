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
 *   "series": "The FAMtastic Website Packages Explained Series",
 *   "series_order": 9,
 *   "allow_create_series": false,
 *   "tags": [{"key": "pricing", "label": "Pricing"}, ...],
 *   "author_uid": 1,
 *   "meta_title": "...",
 *   "meta_description": "...",
 *   "word_count": 374,
 *   "status": 1
 * }
 *
 * Series handling (added 2026-09-04 to fix a real defect): this blog is
 * series-first. blog_post carries field_blog_series ("Ordered learning journey
 * containing this post") and field_series_order ("Position of this post inside
 * its series"); frontend/src/pages/BlogPostPage.jsx filters siblings by series,
 * sorts them by seriesOrder for the prev/next `blog-series-nav`, and puts the
 * series into the BreadcrumbList JSON-LD. The first version of this script set
 * neither field, so every post it published (nid 156/157/158) was orphaned from
 * that architecture while all 80 seeded posts had it. So:
 *
 *   - `series` and `series_order` are REQUIRED. A payload missing either is
 *     rejected before anything is written. Publishing an orphan is not an
 *     option this script offers.
 *   - The series term is RESOLVED BY NAME in the blog_series vocabulary. It is
 *     never created implicitly — the ten real series were seeded by
 *     seed-demand-content.php and a typo must not spawn a near-duplicate term.
 *     Creating one requires "allow_create_series": true in the payload, and
 *     that path prints a loud SERIES-CREATED warning to stderr and reports
 *     "series_created": true in the JSON result.
 *   - `series_order` is checked against every OTHER post already in that
 *     series. A collision is refused, because two posts sharing an order makes
 *     the prev/next nav ordering non-deterministic. Re-publishing the same
 *     post at the order it already holds is fine (it is excluded from the
 *     check by content_key).
 *
 * Idempotency: looked up by field_content_key first (exact machine key match,
 * same lookup seed-demand-content.php uses), falling back to an exact title
 * match. A second run with the same content_key updates the existing node
 * instead of creating a duplicate — never creates twice.
 *
 * Prints one JSON line to stdout: {"action": "created|updated|deleted|not_found",
 * "nid": ..., "uuid": ..., "path": ..., "series": ..., "series_order": ...} so
 * the calling Python script can parse the result without scraping Drush's
 * human-readable output.
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
  'field_blog_category', 'field_blog_series', 'field_blog_tags', 'field_author',
  'field_excerpt', 'field_meta_description', 'field_meta_title',
  'field_series_order', 'field_word_count', 'field_content_key',
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

  // --- Series: required, resolved by name, never implicitly created. ---
  $series_name = $payload['series'] ?? NULL;
  if (!is_string($series_name) || trim($series_name) === '') {
    throw new RuntimeException(
      "Payload for '$content_key' has no 'series'. This blog is series-first: " .
      'field_blog_series and field_series_order drive the on-page series nav and ' .
      'the BreadcrumbList JSON-LD. Publishing a series orphan is refused. Add a ' .
      "series to the draft's DRAFT_CLASSIFICATION row in scripts/publish-blog-draft.py."
    );
  }
  $series_name = trim($series_name);

  $series_order = $payload['series_order'] ?? NULL;
  if (!is_int($series_order) && !(is_string($series_order) && ctype_digit($series_order))) {
    throw new RuntimeException(
      "Payload for '$content_key' has no usable 'series_order' (got " .
      var_export($series_order, TRUE) . '). Every post needs an explicit integer ' .
      'position inside its series — the frontend sorts siblings by it.'
    );
  }
  $series_order = (int) $series_order;
  if ($series_order < 1) {
    throw new RuntimeException("series_order for '$content_key' must be >= 1, got $series_order.");
  }

  $series_matches = $term_storage->loadByProperties(['vid' => 'blog_series', 'name' => $series_name]);
  $series_term = $series_matches ? reset($series_matches) : NULL;
  $series_created = FALSE;
  if (!$series_term) {
    if (empty($payload['allow_create_series'])) {
      $existing = $term_storage->loadByProperties(['vid' => 'blog_series']);
      $names = array_map(fn ($t) => $t->label(), $existing);
      sort($names);
      throw new RuntimeException(
        "No blog_series term named '$series_name'. This script never creates a series " .
        'implicitly — a typo would spawn a near-duplicate alongside a real series. ' .
        "Fix the name, or set \"new_series\": true on the draft's row to create it " .
        "deliberately.\nExisting series:\n  - " . implode("\n  - ", $names)
      );
    }
    $series_term = $term_storage->create(['vid' => 'blog_series', 'name' => $series_name]);
    $series_term->save();
    $series_created = TRUE;
    fwrite(STDERR, "SERIES-CREATED: a new blog_series term '$series_name' (tid " .
      $series_term->id() . ") was created because the payload set allow_create_series. " .
      "A one-post series renders a degenerate 'Article 1 of 1' nav — confirm this is intended.\n");
  }

  // Refuse a series_order already held by a DIFFERENT post. Re-publishing this
  // same post at the order it already holds is excluded by content_key.
  $siblings = $node_storage->loadByProperties([
    'type' => 'blog_post',
    'field_blog_series' => $series_term->id(),
  ]);
  $taken = [];
  foreach ($siblings as $sibling) {
    $sibling_key = $sibling->get('field_content_key')->value;
    if ($sibling_key === $content_key) {
      continue;
    }
    $sibling_order = $sibling->get('field_series_order');
    if (!$sibling_order->isEmpty()) {
      $taken[(int) $sibling_order->value] = ['nid' => (int) $sibling->id(), 'key' => $sibling_key];
    }
  }
  if (isset($taken[$series_order])) {
    $holder = $taken[$series_order];
    $next_free = $taken ? max(array_keys($taken)) + 1 : 1;
    throw new RuntimeException(
      "series_order $series_order in '$series_name' is already held by nid{$holder['nid']} " .
      "({$holder['key']}). Two posts sharing an order makes the prev/next series nav " .
      "ordering non-deterministic. Pick a free order — next unused is $next_free."
    );
  }

  $node = $load_by_key($content_key, $payload['title'] ?? '');
  $created = !$node;
  $node = $node ?: Node::create(['type' => 'blog_post']);

  $node->setTitle($payload['title']);
  $node->set('field_content_key', $content_key);
  $node->set('body', ['value' => $payload['body_html'], 'format' => 'basic_html']);
  $node->set('field_excerpt', $payload['excerpt']);
  $node->set('field_blog_category', $category_term->id());
  $node->set('field_blog_series', $series_term->id());
  $node->set('field_series_order', $series_order);
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
    'series' => $series_name,
    'series_tid' => (int) $series_term->id(),
    'series_order' => $series_order,
    'series_created' => $series_created,
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
else {
  throw new RuntimeException("Unknown action: $action");
}
