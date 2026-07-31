<?php

/**
 * @file
 * Rebrands FAMtastic Designs content from solo-founder/local to
 * AGENCY/worldwide positioning.
 *
 * Run from backend/ inside a bootstrapped Drupal:
 *   vendor/bin/drush php:script scripts/rebrand-agency.php
 *
 * The script is IDEMPOTENT: every mutation is guarded by a current-state
 * check, so re-running it produces the same end state and never duplicates
 * paragraphs or menu links.
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\menu_link_content\Entity\MenuLinkContent;

$etm = \Drupal::entityTypeManager();
$node_storage = $etm->getStorage('node');
$menu_storage = $etm->getStorage('menu_link_content');

$summary = [];

echo "==============================================================\n";
echo " FAMtastic Designs — AGENCY REBRAND (idempotent)\n";
echo "==============================================================\n";

// ---------------------------------------------------------------------------
// Helpers.
// ---------------------------------------------------------------------------

/**
 * Prints the main-menu tree (parents first, children indented).
 */
$print_menu_tree = function ($label) use ($menu_storage) {
  echo "\n--- MAIN MENU TREE ($label) ---\n";
  $links = $menu_storage->loadByProperties(['menu_name' => 'main']);
  // Group by parent.
  $roots = [];
  $children = [];
  foreach ($links as $link) {
    $parent = $link->getParentId();
    if ($parent) {
      $children[$parent][] = $link;
    }
    else {
      $roots[] = $link;
    }
  }
  $by_weight = fn($a, $b) => $a->getWeight() <=> $b->getWeight();
  usort($roots, $by_weight);
  foreach ($roots as $root) {
    $expanded = $root->isExpanded() ? ' [expanded]' : '';
    echo sprintf("  w=%-2d %s -> %s%s\n", $root->getWeight(), $root->getTitle(), $root->getUrlObject()->toString(), $expanded);
    $key = 'menu_link_content:' . $root->uuid();
    if (!empty($children[$key])) {
      usort($children[$key], $by_weight);
      foreach ($children[$key] as $child) {
        echo sprintf("       w=%-2d  ↳ %s -> %s\n", $child->getWeight(), $child->getTitle(), $child->getUrlObject()->toString());
      }
    }
  }
};

/**
 * Replaces a paragraph-reference field with fresh paragraphs, idempotently.
 *
 * @param \Drupal\node\Entity\Node $node
 *   The node being edited.
 * @param string $field
 *   The entity_reference_revisions field name.
 * @param string $bundle
 *   Paragraph type to create.
 * @param array $items
 *   List of field => value maps, one per paragraph, in desired order.
 *
 * @return bool
 *   TRUE when the field was (re)built, FALSE when it already matched.
 */
$replace_paragraphs = function (Node $node, $field, $bundle, array $items) {
  // Compare current state against target; skip when identical.
  $current = [];
  foreach ($node->get($field)->referencedEntities() as $p) {
    $tuple = [];
    foreach (array_keys($items[0]) as $fname) {
      $tuple[$fname] = $p->hasField($fname) && !$p->get($fname)->isEmpty() ? (string) $p->get($fname)->value : '';
    }
    $current[] = $tuple;
  }
  $target = [];
  foreach ($items as $item) {
    $target[] = array_map('strval', $item);
  }
  if ($current == $target) {
    return FALSE;
  }

  // Detach + delete the old paragraphs, then build the new set.
  $old = $node->get($field)->referencedEntities();
  $node->set($field, []);
  foreach ($items as $item) {
    $p = Paragraph::create(['type' => $bundle] + $item);
    $p->save();
    $node->get($field)->appendItem([
      'target_id' => $p->id(),
      'target_revision_id' => $p->getRevisionId(),
    ]);
  }
  foreach ($old as $p) {
    $p->delete();
  }
  return TRUE;
};

// ===========================================================================
// 0. Menu tree BEFORE.
// ===========================================================================
$print_menu_tree('BEFORE');

// ===========================================================================
// 1. Homepage (type homepage).
// ===========================================================================
echo "\n=== 1. HOMEPAGE ===\n";
$homepage = reset($node_storage->loadByProperties(['type' => 'homepage']));
if (!$homepage) {
  echo "ERROR: no homepage node found — skipping.\n";
}
else {
  $changes = [];

  // 1a. Hero headline (already set — verify only).
  $headline = $homepage->get('field_hero_headline')->value;
  if ($headline === 'Agentic AI Business Solutions Engineering Studio') {
    echo "OK    headline verified: \"$headline\"\n";
  }
  else {
    $homepage->set('field_hero_headline', 'Agentic AI Business Solutions Engineering Studio');
    $changes[] = 'field_hero_headline (corrected)';
    echo "FIX   headline corrected to 'Agentic AI Business Solutions Engineering Studio'\n";
  }

  // 1b. Hero subheadline.
  $sub = 'We engineer intelligent systems that capture leads, automate operations, and grow businesses — worldwide.';
  if ($homepage->get('field_hero_subheadline')->value !== $sub) {
    $homepage->set('field_hero_subheadline', $sub);
    $changes[] = 'field_hero_subheadline';
    echo "SET   field_hero_subheadline\n";
  }
  else {
    echo "SKIP  field_hero_subheadline (already set)\n";
  }

  // 1c. Stats items (metric_item paragraphs).
  $stats = [
    ['field_metric_value' => '22+', 'field_metric_label' => 'Years Combined Engineering Expertise'],
    ['field_metric_value' => '100+', 'field_metric_label' => 'Systems Deployed Globally'],
    ['field_metric_value' => '40%', 'field_metric_label' => 'Average Lead Increase'],
    ['field_metric_value' => '$4.2M', 'field_metric_label' => 'Revenue Generated for Clients'],
  ];
  if ($replace_paragraphs($homepage, 'field_stats_items', 'metric_item', $stats)) {
    $changes[] = 'field_stats_items (4 metric_item)';
    echo "SET   field_stats_items (4 metric_item paragraphs replaced)\n";
  }
  else {
    echo "SKIP  field_stats_items (already up to date)\n";
  }

  // 1d. Why items (why_item paragraphs).
  $why = [
    [
      'field_why_title' => 'Engineering-Driven',
      'field_why_body' => 'A team of engineers that design. Every system is built on solid architecture, not page builders or templates.',
    ],
    [
      'field_why_title' => 'AI-First Approach',
      'field_why_body' => 'Intelligence is woven into every system from day one. Not bolted on as an afterthought.',
    ],
    [
      'field_why_title' => 'Results That Matter',
      'field_why_body' => '40% more appointments. 80% automated inquiries. 300% lead increase. Measured in your revenue.',
    ],
    [
      'field_why_title' => 'Global Reach, Personal Service',
      'field_why_body' => 'Based in Florida. Serving clients worldwide. From $199 starters to multimillion-dollar enterprise systems.',
    ],
  ];
  if ($replace_paragraphs($homepage, 'field_why_items', 'why_item', $why)) {
    $changes[] = 'field_why_items (4 why_item)';
    echo "SET   field_why_items (4 why_item paragraphs replaced)\n";
  }
  else {
    echo "SKIP  field_why_items (already up to date)\n";
  }

  // 1e. Service-area section: reposition from local cities to worldwide.
  $area_title = 'Based in Florida. Serving Clients Worldwide.';
  if ($homepage->get('field_service_area_title')->value !== $area_title) {
    $homepage->set('field_service_area_title', $area_title);
    $changes[] = 'field_service_area_title';
    echo "SET   field_service_area_title\n";
  }
  $regions = ['Florida (HQ)', 'United States', 'Canada', 'United Kingdom', 'Europe', 'Worldwide (Remote)'];
  $current_regions = array_map(fn($i) => $i['value'], $homepage->get('field_service_area_cities')->getValue());
  if ($current_regions !== $regions) {
    $homepage->set('field_service_area_cities', $regions);
    $changes[] = 'field_service_area_cities (regions)';
    echo "SET   field_service_area_cities -> worldwide regions\n";
  }

  if ($changes) {
    $homepage->save();
  }
  $summary[] = 'homepage nid ' . $homepage->id() . ': ' . ($changes ? implode(', ', $changes) : 'no changes needed');
}

// ===========================================================================
// 2. About page (type page, alias /about).
// ===========================================================================
echo "\n=== 2. ABOUT PAGE ===\n";
$about_body = <<<HTML
<p>FAMtastic Designs was founded by Fitzgerald 'Fritz' Medine, 2024 BEYA Leader in Technology, with a vision to make agentic AI accessible to businesses of every size.</p>
<h2>An Engineering Studio, Not a Design Shop</h2>
<p>We are a team of engineers that design. Where most web shops start with a template and work backward to your business, our team starts with your business and engineers forward to the system. Enterprise-grade architecture, structured content, and clean code are the baseline here — not the upsell.</p>
<p>We combine 22+ years of engineering expertise with cutting-edge AI to build systems that work while you sleep.</p>
<h3>Our Values — the FAMtastic Philosophy</h3>
<p>FAMtastic has a definition, and it is a promise: <strong>fearless deviation from established norms with a bold and unapologetic commitment to stand apart on purpose, applying mastery of craft to the point that the results are the proof, and manifesting the extraordinary from the ordinary.</strong></p>
<p>That is not a tagline. It is a design principle that runs through every decision our team makes — the dark surfaces and electric lime, the refusal to ship cookie-cutter layouts, the insistence that every system is verified before a client ever sees it.</p>
<h3>Our Process</h3>
<p>Every engagement runs through FAMtastic Site Studio, our AI-powered production pipeline that turns a conversation about your business into a verified, deployed, production system. The tooling is modern; the standards are old-school. Every build passes automated checks for layout, consistency, accessibility, and performance. Honest assessment over polished summaries, every time.</p>
<h3>Built to Scale With You</h3>
<p>From \$199 starters to multimillion-dollar enterprise systems, our process scales to the size of the problem. Based in Florida and serving clients worldwide, our team ships one project at a time — implemented and verified before moving to the next.</p>
HTML;

$about = NULL;
foreach ($node_storage->loadByProperties(['type' => 'page']) as $candidate) {
  if (\Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $candidate->id()) === '/about') {
    $about = $candidate;
    break;
  }
}
if (!$about) {
  echo "ERROR: no /about page found — skipping.\n";
}
elseif (trim((string) $about->get('body')->value) === trim($about_body)) {
  echo "SKIP  About body (already rebranded)\n";
  $summary[] = 'about nid ' . $about->id() . ': no changes needed';
}
else {
  $about->set('body', ['value' => $about_body, 'format' => 'full_html']);
  $about->save();
  echo "SET   About body rewritten (team / process / scale framing, F-A-M kept as company values)\n";
  $summary[] = 'about nid ' . $about->id() . ': body rewritten';
}

// ===========================================================================
// 3. Contact page (type page, alias /contact).
// ===========================================================================
echo "\n=== 3. CONTACT PAGE ===\n";
$contact_body = <<<HTML
<p>Tell us what you're building. We'll send you a quote within 24 hours.</p>
<h3>Start With Our 60-Second Intake</h3>
<p>Start with our 60-second intake — tell us what you need and get an instant estimate. No obligation, no sales pitch — just a clear scope and a number.</p>
<h3>Reach the Team</h3>
<ul>
  <li><strong>Email:</strong> <a href="mailto:hello@famtastic.design">hello@famtastic.design</a></li>
  <li><strong>Hours:</strong> Mon–Fri 9am–6pm ET</li>
  <li><strong>Location:</strong> Based in Florida — serving clients worldwide</li>
</ul>
<p>Prefer to talk? Schedule a 15-minute call — mention it in your message and our team will set it up. Optional, never required.</p>
HTML;

$contact = NULL;
foreach ($node_storage->loadByProperties(['type' => 'page']) as $candidate) {
  if (\Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $candidate->id()) === '/contact') {
    $contact = $candidate;
    break;
  }
}
if (!$contact) {
  echo "ERROR: no /contact page found — skipping.\n";
}
elseif (trim((string) $contact->get('body')->value) === trim($contact_body)) {
  echo "SKIP  Contact body (already rebranded)\n";
  $summary[] = 'contact nid ' . $contact->id() . ': no changes needed';
}
else {
  $contact->set('body', ['value' => $contact_body, 'format' => 'full_html']);
  $contact->save();
  echo "SET   Contact body rewritten (quote in 24h, email primary, intake mention, no 'Call Fritz')\n";
  $summary[] = 'contact nid ' . $contact->id() . ': body rewritten';
}

// ===========================================================================
// 4. Service pages: de-Fritz + de-localize testimonials, worldwide tagline.
// ===========================================================================
echo "\n=== 4. SERVICE PAGES ===\n";

// Targeted copy replacements applied to every text field of every
// service_page (node fields AND referenced paragraph fields).
$replacements = [
  // Testimonial quotes that name the founder.
  'Fritz rebuilt our site and appointment bookings went up 40% in the first month.'
    => 'FAMtastic Designs rebuilt our site and appointment bookings went up 40% in the first month.',
  "Fritz didn't just build a website — he built us a system."
    => "FAMtastic Designs didn't just build a website — the team built us a system.",
  // Attribution lines: drop city-level specificity, keep plausible business roles.
  'James R., HVAC Company, Jensen Beach' => 'James R., HVAC Company Owner',
  'Dr. Marcus J., Medical Practice, Port St. Lucie' => 'Dr. Marcus J., Medical Practice Owner',
  'Dr. Marcus J., Medical Practice Owner, Port St. Lucie' => 'Dr. Marcus J., Medical Practice Owner',
  'Cassandra T., Real Estate Broker, Stuart, FL' => 'Cassandra T., Real Estate Broker',
  'Law Firm Owner, Fort Pierce, FL' => 'Law Firm Owner',
  'Boutique Owner, Vero Beach, FL' => 'Boutique Owner',
];

$text_types = ['string', 'string_long', 'text_long', 'text_with_summary'];
$locality_pattern = '/Fritz|Treasure Coast|Jensen Beach|Port St\. Lucie|Stuart|Vero Beach|Fort Pierce|Palm City|West Palm Beach|Tradition/i';

foreach ($node_storage->loadByProperties(['type' => 'service_page']) as $service) {
  $node_changes = [];

  // 4a. Apply targeted replacements to all node text fields.
  foreach ($service->getFields() as $fname => $flist) {
    $type = $flist->getFieldDefinition()->getType();
    if (!in_array($type, $text_types, TRUE) || $flist->isEmpty()) {
      continue;
    }
    $values = $flist->getValue();
    $dirty = FALSE;
    foreach ($values as $i => $item) {
      if (!isset($item['value'])) {
        continue;
      }
      $new = strtr($item['value'], $replacements);
      if ($new !== $item['value']) {
        $values[$i]['value'] = $new;
        $dirty = TRUE;
        $node_changes[] = "$fname (de-Fritz/de-localized)";
      }
    }
    if ($dirty) {
      $service->set($fname, $values);
    }
  }

  // 4b. Apply the same replacements inside referenced paragraphs.
  foreach ($service->getFields() as $fname => $flist) {
    if ($flist->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
      continue;
    }
    foreach ($flist->referencedEntities() as $p) {
      $p_dirty = FALSE;
      foreach ($p->getFields() as $pf => $pflist) {
        $ptype = $pflist->getFieldDefinition()->getType();
        if (!in_array($ptype, $text_types, TRUE) || $pflist->isEmpty()) {
          continue;
        }
        $pvalues = $pflist->getValue();
        foreach ($pvalues as $i => $item) {
          if (!isset($item['value'])) {
            continue;
          }
          $new = strtr($item['value'], $replacements);
          if ($new !== $item['value']) {
            $pvalues[$i]['value'] = $new;
            $p_dirty = TRUE;
            $node_changes[] = "$fname.$pf (paragraph de-Fritz/de-localized)";
          }
        }
        if ($p_dirty) {
          $p->set($pf, $pvalues);
        }
      }
      if ($p_dirty) {
        $p->save();
      }
    }
  }

  // 4c. Append the worldwide tagline to the hero subheadline (closing CTA-adjacent).
  $sub = (string) $service->get('field_hero_subheadline')->value;
  if ($sub && stripos($sub, 'Serving clients worldwide') === FALSE) {
    $service->set('field_hero_subheadline', rtrim($sub) . ' Serving clients worldwide.');
    $node_changes[] = 'field_hero_subheadline (+ worldwide tagline)';
  }

  if ($node_changes) {
    $service->save();
  }
  $node_changes = array_unique($node_changes);
  echo ($node_changes ? 'SET  ' : 'SKIP ') . ' nid ' . $service->id() . ' "' . $service->label() . '"'
    . ($node_changes ? ': ' . implode('; ', $node_changes) : ' (already rebranded)') . "\n";
  $summary[] = 'service_page nid ' . $service->id() . ': ' . ($node_changes ? implode('; ', $node_changes) : 'no changes needed');

  // 4d. Report any remaining founder/local mentions for manual review.
  foreach ($service->getFields() as $fname => $flist) {
    $type = $flist->getFieldDefinition()->getType();
    if (!in_array($type, $text_types, TRUE)) {
      continue;
    }
    foreach ($flist->getValue() as $item) {
      $v = $item['value'] ?? '';
      if (preg_match($locality_pattern, $v, $m)) {
        echo "WARN  residual mention on nid {$service->id()} [$fname] ({$m[0]})\n";
      }
    }
  }
}

// ===========================================================================
// 5. Location FAQ (nid with 'Port St. Lucie' answer) -> worldwide framing.
// ===========================================================================
echo "\n=== 5. LOCATION FAQ ===\n";
foreach ($node_storage->loadByProperties(['type' => 'faq_item']) as $faq) {
  if (!$faq->hasField('field_answer')) {
    continue;
  }
  $answer = (string) $faq->get('field_answer')->value;
  if (preg_match($locality_pattern, $answer)) {
    $new_answer = "We're a fully remote-capable team based in Florida, serving clients worldwide. Most of our work happens over video calls and structured async updates — but if you're in Florida, we're always happy to meet in person.";
    if ($answer !== $new_answer) {
      $faq->set('field_answer', ['value' => $new_answer, 'format' => $faq->get('field_answer')->format]);
      $faq->save();
      echo 'SET   faq_item nid ' . $faq->id() . ' "' . $faq->label() . "\" -> worldwide answer\n";
      $summary[] = 'faq_item nid ' . $faq->id() . ': location answer rewritten';
    }
    else {
      echo "SKIP  faq_item nid {$faq->id()} (already rebranded)\n";
    }
  }
}

// ===========================================================================
// 6. Main menu restructure.
//    Home | Solutions (6 children) | Packages (7 children) | Work |
//    Insights (/blog) | About | Contact. FAQ link removed.
// ===========================================================================
echo "\n=== 6. MAIN MENU ===\n";

$load_link = function ($title, $uri = NULL) use ($menu_storage) {
  $found = $menu_storage->loadByProperties(['menu_name' => 'main', 'title' => $title]);
  if ($found) return reset($found);
  if ($uri) {
    $query = $menu_storage->getQuery()
      ->condition('menu_name', 'main')
      ->condition('link.uri', $uri)
      ->accessCheck(FALSE);
    $ids = $query->execute();
    if ($ids) return $menu_storage->load(reset($ids));
  }
  return NULL;
};

// 6a. Retitle/reposition existing top-level links.
$top_level = [
  // old title => [new title, uri, weight, expanded]
  // NOTE: no 'Home' — core provides it via the standard.front_page plugin
  // link (not an entity); creating an entity one duplicates it in the nav.
  'Services' => ['Solutions', 'internal:/services', 2, TRUE],
  'Packages' => ['Packages', 'internal:/packages', 3, TRUE],
  'Work' => ['Work', 'internal:/work', 4, FALSE],
  'Blog' => ['Insights', 'internal:/blog', 5, FALSE],
  'About' => ['About', 'internal:/about', 6, FALSE],
  'Contact' => ['Contact', 'internal:/contact', 7, FALSE],
];

$parents = [];
foreach ($top_level as $old_title => [$new_title, $uri, $weight, $expanded]) {
  $link = $load_link($old_title, $uri) ?: $load_link($new_title, $uri);
  if (!$link) {
    $link = MenuLinkContent::create([
      'menu_name' => 'main',
      'link' => ['uri' => $uri],
    ]);
    echo "CREATE menu link '$new_title'\n";
  }
  $dirty = FALSE;
  foreach ([
    'title' => $new_title,
    'weight' => $weight,
    'expanded' => $expanded,
    'enabled' => TRUE,
  ] as $prop => $val) {
    if ($link->get($prop)->value != $val) {
      $link->set($prop, $val);
      $dirty = TRUE;
    }
  }
  if ($link->get('link')->first()->uri !== $uri) {
    $link->set('link', ['uri' => $uri]);
    $dirty = TRUE;
  }
  if ($dirty || $link->isNew()) {
    $link->save();
    echo "SET   menu link '$new_title' (w=$weight" . ($expanded ? ', expanded' : '') . ")\n";
  }
  else {
    echo "SKIP  menu link '$new_title' (up to date)\n";
  }
  $parents[$new_title] = $link;
}

// 6b. Remove the FAQ top-level link (no longer in the target structure).
$faq_link = $load_link('FAQ');
if ($faq_link) {
  $faq_link->delete();
  echo "DEL   menu link 'FAQ' (removed from main nav; /faq still reachable directly)\n";
}

// 6c. Dropdown children under Solutions and Packages.
$child_sets = [
  'Solutions' => 'service_page',
  'Packages' => 'package_page',
];
foreach ($child_sets as $parent_title => $bundle) {
  $parent = $parents[$parent_title];
  $parent_mlid = 'menu_link_content:' . $parent->uuid();
  $nodes = $node_storage->loadByProperties(['type' => $bundle, 'status' => 1]);
  uasort($nodes, fn($a, $b) => ($a->get('field_sort_order')->value ?? 0) <=> ($b->get('field_sort_order')->value ?? 0));
  $weight = 1;
  foreach ($nodes as $child_node) {
    $title = $child_node->label();
    $uri = 'entity:node/' . $child_node->id();
    $link = $load_link($title);
    if (!$link) {
      $link = MenuLinkContent::create([
        'menu_name' => 'main',
        'title' => $title,
        'link' => ['uri' => $uri],
        'parent' => $parent_mlid,
        'weight' => $weight,
      ]);
      $link->save();
      echo "CREATE child '$title' under $parent_title (w=$weight)\n";
    }
    else {
      $dirty = FALSE;
      if ($link->getParentId() !== $parent_mlid) {
        $link->set('parent', $parent_mlid);
        $dirty = TRUE;
      }
      if ($link->getWeight() != $weight) {
        $link->set('weight', $weight);
        $dirty = TRUE;
      }
      if ($link->get('link')->first()->uri !== $uri) {
        $link->set('link', ['uri' => $uri]);
        $dirty = TRUE;
      }
      if ($dirty) {
        $link->save();
        echo "SET   child '$title' under $parent_title (w=$weight)\n";
      }
      else {
        echo "SKIP  child '$title' (up to date)\n";
      }
    }
    $weight++;
  }
}

// ===========================================================================
// 7. Menu tree AFTER + summary.
// ===========================================================================
$print_menu_tree('AFTER');

echo "\n==============================================================\n";
echo " NODE UPDATE SUMMARY\n";
echo "==============================================================\n";
foreach ($summary as $line) {
  echo " - $line\n";
}
echo "\nDone. Run 'vendor/bin/drush cr' to rebuild caches.\n";
