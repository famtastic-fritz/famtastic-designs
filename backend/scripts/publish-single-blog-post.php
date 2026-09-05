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
 *   "seo_brief": {"primary_keyword": "...", "visual": {"src": "...", "alt": "..."}, ...},
 *   "capability_keys": ["website-discovery", "product-onboarding", "commerce-lifecycle"],
 *   "cta_text": "Find the right next step",
 *   "cta_link_uri": "internal:/start?source=blog&series=...&article=...",
 *   "related_faq_keys": ["website-packages-explained-fit", ...],
 *   "status": 1
 * }
 *
 * Field parity (added 2026-09-04 after a second field-loss audit): a
 * blog_post can carry 19 fields. This script writes 17 of them. The two it
 * does not — field_featured_image and field_published_date — are unused by
 * every one of the 83 published posts, and are left alone deliberately rather
 * than by omission. The hero image is field_seo_brief.visual, NOT
 * field_featured_image; that name is a trap and has never held a value on
 * this bundle. The five fields added in this pass (seo_brief, related_faqs,
 * cta_link, cta_text, capability_keys) were each populated on 80 of 83 posts,
 * and missing on exactly the three this pipeline had published.
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
  'field_seo_brief', 'field_related_faqs', 'field_cta_link', 'field_cta_text',
  'field_capability_keys',
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

  // --- The five fields nid 156/157/158 shipped without (added 2026-09-04). ---
  // A corpus audit of all 83 published posts found five fields the 80 seeded
  // posts populate and this script never set. field_seo_brief carries the hero
  // image (frontend BlogPostPage.jsx renders post.visual.src, BlogHubPage.jsx
  // uses it for the card thumbnail, and it becomes Article.image in the JSON-LD)
  // plus the keywords that become Article.keywords; field_related_faqs renders
  // the whole on-page FAQ section AND the FAQPage JSON-LD node, so its absence
  // silently removed both. Same fail-loud contract as series: a payload missing
  // any of them is refused rather than written half-populated.
  $seo_brief = $payload['seo_brief'] ?? NULL;
  if (!is_array($seo_brief) || empty($seo_brief['visual']['src']) || empty($seo_brief['primary_keyword'])) {
    throw new RuntimeException(
      "Payload for '$content_key' has no usable 'seo_brief'. It must carry at least " .
      'visual.src (the hero image and the Article JSON-LD image) and primary_keyword. ' .
      'Publishing without it is what left nid 156/157/158 with no hero and no image ' .
      'in their structured data. Note field_featured_image is NOT the hero — no post ' .
      'on this bundle has ever used it.'
    );
  }

  $capability_keys = $payload['capability_keys'] ?? NULL;
  if (!is_array($capability_keys) || !$capability_keys) {
    throw new RuntimeException(
      "Payload for '$content_key' has no 'capability_keys'. All 80 seeded posts carry " .
      "their series' capability-registry keys."
    );
  }

  $cta_text = $payload['cta_text'] ?? NULL;
  $cta_link_uri = $payload['cta_link_uri'] ?? NULL;
  if (!is_string($cta_text) || trim($cta_text) === '' || !is_string($cta_link_uri) || trim($cta_link_uri) === '') {
    throw new RuntimeException(
      "Payload for '$content_key' is missing 'cta_text' or 'cta_link_uri'. The frontend " .
      "falls back to a generic 'Explore your options' -> /start, which silently drops " .
      'the per-article attribution query string the CTA link exists to carry.'
    );
  }

  // Resolve the FAQ nodes by content key, exactly the way seed-demand-content.php
  // created them (bundle faq_item, keyed by field_content_key). Never created
  // implicitly — an unresolvable key means the manifest and the live site have
  // drifted, and that must be seen, not papered over with a shorter FAQ list.
  $related_faq_keys = $payload['related_faq_keys'] ?? NULL;
  if (!is_array($related_faq_keys) || !$related_faq_keys) {
    throw new RuntimeException(
      "Payload for '$content_key' has no 'related_faq_keys'. field_related_faqs renders " .
      'the on-page FAQ section and the FAQPage JSON-LD node; publishing without it drops ' .
      'both with no error, which is exactly what happened to nid 156/157/158.'
    );
  }
  $faq_ids = [];
  $missing_faqs = [];
  foreach ($related_faq_keys as $faq_key) {
    $matches = $node_storage->loadByProperties(['type' => 'faq_item', 'field_content_key' => $faq_key]);
    if (!$matches) {
      $missing_faqs[] = $faq_key;
      continue;
    }
    $faq_ids[] = ['target_id' => (int) reset($matches)->id()];
  }
  if ($missing_faqs) {
    throw new RuntimeException(
      "No faq_item node found for content key(s): " . implode(', ', $missing_faqs) .
      ". These come from backend/config/famtastic-content-series.json and were seeded by " .
      'seed-demand-content.php. A missing one means the manifest and production have ' .
      'drifted — fix that rather than publishing with a truncated FAQ set.'
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
  // Same encoding flags as seed-demand-content.php so a pipeline-published
  // brief is byte-comparable with a seeded one.
  $node->set('field_seo_brief', json_encode($seo_brief, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  $node->set('field_capability_keys', array_map(fn ($key) => ['value' => $key], $capability_keys));
  $node->set('field_cta_text', $cta_text);
  $node->set('field_cta_link', ['uri' => $cta_link_uri, 'title' => $cta_text]);
  $node->set('field_related_faqs', $faq_ids);
  if (!empty($payload['author_uid']) && $node->hasField('field_author')) {
    $node->set('field_author', (int) $payload['author_uid']);
  }
  // Set the base field explicitly rather than relying on the bundle default —
  // same reasoning as seed-demand-content.php: lean local installs can
  // override Node::setPublished(FALSE).
  $node->set('status', (int) !empty($payload['status']));
  $node->set('path', ['alias' => '/blog/' . ($payload['slug'] ?? $content_key), 'pathauto' => 0]);

  // --- Field-parity gate (added 2026-09-04). ---
  // Two separate field-loss defects shipped from this pipeline before anyone
  // diffed it against the incumbent writer: first field_blog_series +
  // field_series_order, then field_seo_brief, field_related_faqs,
  // field_cta_link, field_cta_text and field_capability_keys. Both were
  // invisible — nothing errored, and the posts looked fine in isolation. The
  // loss was only visible by comparing a new post against an old one.
  //
  // So do exactly that, mechanically, on every publish: take a reference post
  // that the seeder created, and refuse to save if this node leaves empty any
  // field the reference populates. This runs BEFORE save, so a third dropped
  // field is caught with nothing written rather than discovered weeks later.
  // Fields the reference itself leaves empty (field_featured_image,
  // field_published_date — unused by all 83 posts) never enter the comparison,
  // and extra fields this pipeline sets but the seeder does not (field_author)
  // are not penalised: the check is one-directional.
  // Pick the reference with a bounded query rather than loading all 83 posts:
  // any published post that carries a brief is a complete seeded node, and one
  // is all the comparison needs.
  $reference = NULL;
  $reference_ids = $node_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'blog_post')
    ->condition('field_content_key', $content_key, '<>')
    ->exists('field_seo_brief')
    ->range(0, 1)
    ->execute();
  if ($reference_ids) {
    $reference = $node_storage->load(reset($reference_ids));
  }
  if ($reference) {
    $dropped = [];
    foreach ($reference->getFields() as $field_name => $field) {
      if (strpos($field_name, 'field_') !== 0 || $field->isEmpty()) {
        continue;
      }
      if ($node->hasField($field_name) && $node->get($field_name)->isEmpty()) {
        $dropped[] = $field_name;
      }
    }
    if ($dropped) {
      throw new RuntimeException(
        "FIELD PARITY FAILURE: this post would be saved with " . count($dropped) .
        ' field(s) empty that reference post nid' . $reference->id() . ' (' .
        $reference->get('field_content_key')->value . ") populates:\n  - " .
        implode("\n  - ", $dropped) .
        "\nNothing was written. This is the check that would have caught the series " .
        'and seo_brief losses. Either set the field in the payload, or — if it is ' .
        'genuinely optional for this post — say so explicitly rather than letting it ' .
        'default to empty.'
      );
    }
  }

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
    'field_parity_reference_nid' => $reference ? (int) $reference->id() : NULL,
    'related_faq_count' => count($faq_ids),
    'hero_visual_src' => $seo_brief['visual']['src'],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
else {
  throw new RuntimeException("Unknown action: $action");
}
