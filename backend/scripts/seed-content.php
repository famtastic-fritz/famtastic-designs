<?php

/**
 * @file
 * Seeds FAMtastic Designs with taxonomy terms, main-menu links, and
 * starter nodes (homepage, About, Contact, TV splash page).
 *
 * Run from backend/ inside a bootstrapped Drupal:
 *   vendor/bin/drush php:script scripts/seed-content.php
 *
 * The script is IDEMPOTENT: every creation is wrapped in an existence
 * check, so re-running it never creates duplicates.
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\menu_link_content\Entity\MenuLinkContent;

$etm = \Drupal::entityTypeManager();
$created = [];
$skipped = [];

/**
 * Small helpers for idempotent lookups.
 */
$term_exists = function ($vid, $name) use ($etm) {
  return (bool) $etm->getStorage('taxonomy_term')->loadByProperties([
    'vid' => $vid,
    'name' => $name,
  ]);
};

$menu_link_exists = function ($title, $uri = NULL) use ($etm) {
  // Title match OR same-URI match (titles get retitled by later scripts).
  if ($etm->getStorage('menu_link_content')->loadByProperties([
    'menu_name' => 'main',
    'title' => $title,
  ])) {
    return TRUE;
  }
  if ($uri) {
    $query = $etm->getStorage('menu_link_content')->getQuery()
      ->condition('menu_name', 'main')
      ->condition('link.uri', $uri)
      ->accessCheck(FALSE);
    return (bool) $query->execute();
  }
  return FALSE;
};

$node_exists = function ($type, $title) use ($etm) {
  return (bool) $etm->getStorage('node')->loadByProperties([
    'type' => $type,
    'title' => $title,
  ]);
};

echo "== FAMtastic Designs — content seed ==\n";

// ---------------------------------------------------------------------------
// 1. Taxonomy terms.
// ---------------------------------------------------------------------------
$vocabularies = [
  'faq_categories' => [
    'General Questions',
    'Process Questions',
    'Technical Questions',
    'Location Questions',
    'Billing & Payments',
  ],
  'blog_categories' => [
    'Pain Points',
    'Solutions',
    'Proof',
    'News',
  ],
];

foreach ($vocabularies as $vid => $names) {
  foreach ($names as $name) {
    if ($term_exists($vid, $name)) {
      $skipped[] = "term [$vid] '$name'";
      echo "SKIP  term [$vid] $name (exists)\n";
      continue;
    }
    try {
      $term = $etm->getStorage('taxonomy_term')->create([
        'vid' => $vid,
        'name' => $name,
      ]);
      $term->save();
      $created[] = "term [$vid] '$name' (tid {$term->id()})";
      echo "OK    term [$vid] $name (tid {$term->id()})\n";
    }
    catch (\Throwable $e) {
      echo "ERROR term [$vid] $name: {$e->getMessage()}\n";
    }
  }
}

// ---------------------------------------------------------------------------
// 2. Main-menu links (weights in the listed order).
// ---------------------------------------------------------------------------
$menu_items = [
  // NOTE: no 'Home' link here — core provides 'standard.front_page' in the
  // main menu; adding another creates a duplicate (fixed 2026-07).
  ['Services', 'internal:/services'],
  ['Work', 'internal:/work'],
  ['Packages', 'internal:/packages'],
  ['Blogs', 'internal:/blog'],
  ['FAQ', 'internal:/faq'],
  ['About', 'internal:/about'],
  ['Contact', 'internal:/contact'],
];

$weight = 0;
foreach ($menu_items as [$title, $uri]) {
  $weight++;
  if ($menu_link_exists($title, $uri)) {
    $skipped[] = "menu link '$title'";
    echo "SKIP  menu link $title (exists)\n";
    continue;
  }
  try {
    $link = MenuLinkContent::create([
      'title' => $title,
      'menu_name' => 'main',
      'link' => ['uri' => $uri],
      'weight' => $weight,
      'enabled' => 1,
    ]);
    $link->save();
    $created[] = "menu link '$title' (mlid {$link->id()})";
    echo "OK    menu link $title -> $uri (weight $weight)\n";
  }
  catch (\Throwable $e) {
    echo "ERROR menu link $title: {$e->getMessage()}\n";
  }
}

// ---------------------------------------------------------------------------
// 3a. Homepage node.
// ---------------------------------------------------------------------------
$make_paragraph = function ($type, array $values) {
  $values['type'] = $type;
  $p = Paragraph::create($values);
  $p->save();
  return [
    'target_id' => $p->id(),
    'target_revision_id' => $p->getRevisionId(),
  ];
};

if ($node_exists('homepage', 'Home')) {
  $skipped[] = "node homepage 'Home'";
  echo "SKIP  node homepage 'Home' (exists)\n";
  $homepage_id = NULL;
}
else {
  try {
    $why_items = [
      $make_paragraph('why_item', [
        'field_why_title' => 'Design That Refuses to Blend In',
        'field_why_body' => [
          'value' => 'FAMtastic means fearless deviation from established norms. Your website will not look like the template shop down the street — it will look like it was built on purpose, because it was. Dark surfaces, electric lime, and a layout your competitors will quietly envy.',
          'format' => 'basic_html',
        ],
      ]),
      $make_paragraph('why_item', [
        'field_why_title' => 'An Engineer, Not a Reseller',
        'field_why_body' => [
          'value' => 'Fritz is a Drupal developer with enterprise CMS depth who builds every site on an AI-powered production pipeline he designed himself. You get the speed of modern tooling with the rigor of a senior engineer who cares about effect, not shortcuts.',
          'format' => 'basic_html',
        ],
      ]),
      $make_paragraph('why_item', [
        'field_why_title' => 'Results Are the Proof',
        'field_why_body' => [
          'value' => 'Every FAMtastic site ships with real verification: layout checks, accessibility passes, mobile responsiveness, and performance review — before you ever see it. The work speaks because it was tested to speak.',
          'format' => 'basic_html',
        ],
      ]),
      $make_paragraph('why_item', [
        'field_why_title' => 'Built to Keep Earning',
        'field_why_body' => [
          'value' => 'Your site is not a one-time deliverable. It is a recurring-revenue product — maintained, improved, and managed so it keeps working for your business month after month.',
          'format' => 'basic_html',
        ],
      ]),
    ];

    $process_steps = [
      $make_paragraph('process_step', [
        'field_step_number' => '01',
        'field_step_title' => 'Discover',
        'field_step_description' => [
          'value' => 'A focused conversation about your business, your customers, and what your website needs to accomplish. No jargon, no pressure — just clarity on the goal.',
          'format' => 'basic_html',
        ],
      ]),
      $make_paragraph('process_step', [
        'field_step_number' => '02',
        'field_step_title' => 'Design',
        'field_step_description' => [
          'value' => 'You get a design brief before a single page is built. Direction, structure, and visual language are agreed up front — so the build is execution, not guesswork.',
          'format' => 'basic_html',
        ],
      ]),
      $make_paragraph('process_step', [
        'field_step_number' => '03',
        'field_step_title' => 'Build & Verify',
        'field_step_description' => [
          'value' => 'Your site is built on the FAMtastic production pipeline and put through automated verification — layout, consistency, accessibility, and performance — before you review it live.',
          'format' => 'basic_html',
        ],
      ]),
      $make_paragraph('process_step', [
        'field_step_number' => '04',
        'field_step_title' => 'Launch & Grow',
        'field_step_description' => [
          'value' => 'Domain, hosting, and deployment are handled for you. After launch, your site stays managed — updates, improvements, and support on a simple monthly plan.',
          'format' => 'basic_html',
        ],
      ]),
    ];

    // Featured references: code defensively — other content may not exist yet.
    $featured_services = [];
    $featured_case_studies = [];
    $featured_testimonials = [];
    try {
      $nids = $etm->getStorage('node')->getQuery()
        ->condition('type', 'service_page')
        ->condition('status', 1)
        ->sort('field_sort_order')
        ->range(0, 3)
        ->accessCheck(FALSE)
        ->execute();
      $featured_services = array_map(fn($nid) => ['target_id' => $nid], array_values($nids));
      $nids = $etm->getStorage('node')->getQuery()
        ->condition('type', 'case_study')
        ->condition('status', 1)
        ->sort('field_sort_order')
        ->range(0, 3)
        ->accessCheck(FALSE)
        ->execute();
      $featured_case_studies = array_map(fn($nid) => ['target_id' => $nid], array_values($nids));
      $nids = $etm->getStorage('node')->getQuery()
        ->condition('type', 'testimonial')
        ->condition('status', 1)
        ->sort('field_sort_order')
        ->range(0, 3)
        ->accessCheck(FALSE)
        ->execute();
      $featured_testimonials = array_map(fn($nid) => ['target_id' => $nid], array_values($nids));
    }
    catch (\Throwable $e) {
      echo "WARN  featured reference lookup failed (fields left empty): {$e->getMessage()}\n";
    }

    $homepage = Node::create([
      'type' => 'homepage',
      'title' => 'Home',
      'uid' => 1,
      'status' => 1,
      'field_hero_headline' => 'Design That Glows in the Dark',
      'field_hero_subheadline' => 'FAMtastic Designs builds premium websites for small businesses that refuse to look ordinary — engineered by a real developer, verified before launch, and managed long after it.',
      'field_cta_primary_text' => 'Start Your Project',
      'field_cta_primary_link' => [
        'uri' => 'internal:/contact',
        'title' => 'Start Your Project',
      ],
      'field_cta_secondary_text' => 'See Our Work',
      'field_cta_secondary_link' => [
        'uri' => 'internal:/work',
        'title' => 'See Our Work',
      ],
      'field_why_title' => 'Why FAMtastic',
      'field_why_items' => $why_items,
      'field_process_title' => 'How It Works',
      'field_process_steps' => $process_steps,
      'field_service_area_title' => 'Proudly Serving the Treasure Coast and Beyond',
      'field_service_area_cities' => [
        ['value' => 'Port St. Lucie'],
        ['value' => 'Fort Pierce'],
        ['value' => 'Stuart'],
        ['value' => 'Jensen Beach'],
        ['value' => 'Palm City'],
        ['value' => 'Vero Beach'],
        ['value' => 'Tradition'],
        ['value' => 'West Palm Beach'],
      ],
      'field_final_cta_title' => 'Ready to Stand Apart on Purpose?',
      'field_final_cta_body' => [
        'value' => 'Your competitors all bought the same template. You did not get into business to be interchangeable. Let\'s build the website your work actually deserves.',
        'format' => 'basic_html',
      ],
      'field_featured_services' => $featured_services,
      'field_featured_case_studies' => $featured_case_studies,
      'field_featured_testimonials' => $featured_testimonials,
    ]);
    $homepage->save();
    $homepage_id = $homepage->id();
    $created[] = "node homepage 'Home' (nid $homepage_id)";
    echo "OK    node homepage 'Home' (nid $homepage_id)\n";

    \Drupal::configFactory()->getEditable('system.site')
      ->set('page.front', '/node/' . $homepage_id)
      ->save();
    echo "OK    system.site page.front set to /node/$homepage_id\n";
  }
  catch (\Throwable $e) {
    echo "ERROR node homepage: {$e->getMessage()}\n";
    $homepage_id = NULL;
  }
}

// ---------------------------------------------------------------------------
// 3b. About page.
// ---------------------------------------------------------------------------
if ($node_exists('page', 'About')) {
  $skipped[] = "node page 'About'";
  echo "SKIP  node page 'About' (exists)\n";
}
else {
  try {
    $about_body = <<<'HTML'
<h2>Fritz Medine — Founder, FAMtastic Designs</h2>
<p>Fritz is a Drupal developer with a business-first mindset: <em>interested in effect, not speed</em>. Where most web shops start with a template and work backward to your business, Fritz starts with your business and engineers forward to the website.</p>
<p>His background is enterprise-grade content management — the kind of structured, scalable architecture that large organizations pay agencies a fortune for. FAMtastic Designs exists to bring that same discipline to small businesses, at a price a small business can actually carry.</p>
<h3>The FAMtastic Philosophy</h3>
<p>FAMtastic has a definition, and it is a promise: <strong>fearless deviation from established norms with a bold and unapologetic commitment to stand apart on purpose, applying mastery of craft to the point that the results are the proof, and manifesting the extraordinary from the ordinary.</strong></p>
<p>That is not a tagline. It is a design principle that runs through every decision — the dark surfaces and electric lime, the refusal to ship cookie-cutter layouts, the insistence that a website is verified before a client ever sees it.</p>
<h3>Built Different, On Purpose</h3>
<p>Fritz designed and operates an AI-powered production pipeline — FAMtastic Site Studio — that turns a conversation about your business into a verified, deployed, production website. The tooling is modern; the standards are old-school. Every build passes automated checks for layout, consistency, accessibility, and performance. Honest assessment over polished summaries, every time.</p>
<p>From Port St. Lucie, Florida, Fritz works with businesses across the Treasure Coast and beyond — one project at a time, implemented and verified before moving to the next.</p>
HTML;
    $about = Node::create([
      'type' => 'page',
      'title' => 'About',
      'uid' => 1,
      'status' => 1,
      'field_page_type' => 'about',
      'field_hero_headline' => 'The Engineer Behind the Designs',
      'field_hero_subheadline' => 'Enterprise-grade engineering discipline, small-business prices, and a stubborn refusal to ship ordinary.',
      'body' => [
        'value' => $about_body,
        'format' => 'full_html',
      ],
      'field_cta_text' => 'Book a Free Consultation',
      'field_cta_link' => [
        'uri' => 'internal:/contact',
        'title' => 'Book a Free Consultation',
      ],
      'field_sort_order' => 10,
      'field_meta_title' => 'About Fritz Medine | FAMtastic Designs',
      'field_meta_description' => [
        'value' => 'Meet Fritz Medine — Drupal developer and founder of FAMtastic Designs, a premium web design studio in Port St. Lucie, FL. Design that glows in the dark.',
      ],
    ]);
    $about->save();
    $created[] = "node page 'About' (nid {$about->id()})";
    echo "OK    node page 'About' (nid {$about->id()})\n";
  }
  catch (\Throwable $e) {
    echo "ERROR node page 'About': {$e->getMessage()}\n";
  }
}

// ---------------------------------------------------------------------------
// 3c. Contact page.
// ---------------------------------------------------------------------------
if ($node_exists('page', 'Contact')) {
  $skipped[] = "node page 'Contact'";
  echo "SKIP  node page 'Contact' (exists)\n";
}
else {
  try {
    $contact_body = <<<'HTML'
<p>Tell us what you need your website or automation to accomplish. We will reply by email with clear next steps and a scoped price.</p>
<h3>Reach the Team</h3>
<ul>
  <li><strong>Email:</strong> <a href="mailto:hello@famtasticdesigns.com">hello@famtasticdesigns.com</a></li>
  <li><strong>Response:</strong> Within one business day.</li>
</ul>
<p>No sales call is required. Use the form or email us whenever it works for you.</p>
HTML;
    $contact = Node::create([
      'type' => 'page',
      'title' => 'Contact',
      'uid' => 1,
      'status' => 1,
      'field_page_type' => 'contact',
      'field_hero_headline' => 'Let\'s Build Something Great Together',
      'field_hero_subheadline' => 'Share what you need and receive a clear, fixed-price next step without a required sales call.',
      'body' => [
        'value' => $contact_body,
        'format' => 'full_html',
      ],
      'field_cta_text' => 'Send Message',
      'field_cta_link' => [
        'uri' => 'internal:/contact#contact-form',
        'title' => 'Send Message',
      ],
      'field_sort_order' => 20,
      'field_meta_title' => 'Contact FAMtastic Designs | Port St. Lucie Web Design',
      'field_meta_description' => [
        'value' => 'Contact FAMtastic Designs by secure form or email at hello@famtasticdesigns.com. Get a clear scope and fixed-price next step without a required sales call.',
      ],
    ]);
    $contact->save();
    $created[] = "node page 'Contact' (nid {$contact->id()})";
    echo "OK    node page 'Contact' (nid {$contact->id()})\n";
  }
  catch (\Throwable $e) {
    echo "ERROR node page 'Contact': {$e->getMessage()}\n";
  }
}

// ---------------------------------------------------------------------------
// 3d. TV splash page (draft).
// ---------------------------------------------------------------------------
$splash_title = 'Inside Success TV';
if ($node_exists('splash_page', $splash_title)) {
  $skipped[] = "node splash_page '$splash_title'";
  echo "SKIP  node splash_page '$splash_title' (exists)\n";
}
else {
  try {
    $splash_body = <<<'HTML'
<p>Fritz Medine, founder of FAMtastic Designs, was featured on <em>Inside Success TV</em> to talk about what happens when enterprise-grade engineering meets small-business hustle — and why your website should never look like everyone else's.</p>
<p>The full episode is coming soon. In the meantime, the philosophy discussed on the show is the same one that builds every FAMtastic site: stand apart on purpose, master the craft, and let the results be the proof.</p>
<p><em>Check back for the episode premiere, or get ahead of the crowd and start your project today.</em></p>
HTML;
    $splash = Node::create([
      'type' => 'splash_page',
      'title' => $splash_title,
      'uid' => 1,
      'status' => 0,
      'field_path_alias' => '/tv',
      'field_splash_status' => 'draft',
      'field_hero_headline' => 'As Seen on Inside Success TV',
      'field_hero_subheadline' => 'Fritz joins the show to talk design, discipline, and building websites that glow in the dark. The episode premieres soon.',
      'body' => [
        'value' => $splash_body,
        'format' => 'full_html',
      ],
      'field_cta_text' => 'Work With Fritz',
      'field_cta_link' => [
        'uri' => 'internal:/contact?source=tv',
        'title' => 'Work With Fritz',
      ],
      'field_theme_override' => 'tv_special',
      'field_meta_title' => 'FAMtastic Designs on Inside Success TV',
      'field_meta_description' => [
        'value' => 'Fritz Medine of FAMtastic Designs featured on Inside Success TV. Watch the upcoming episode and start your premium web design project today.',
      ],
    ]);
    $splash->save();
    $created[] = "node splash_page '$splash_title' (nid {$splash->id()})";
    echo "OK    node splash_page '$splash_title' (nid {$splash->id()})\n";

    // Set the /tv path alias programmatically. Drupal 10/11 stores aliases
    // as PathAlias entities (AliasManager::save() was removed).
    try {
      $system_path = '/node/' . $splash->id();
      $storage = \Drupal::entityTypeManager()->getStorage('path_alias');
      $existing = $storage->loadByProperties(['alias' => '/tv']);
      if (empty($existing)) {
        $alias = \Drupal\path_alias\Entity\PathAlias::create([
          'path' => $system_path,
          'alias' => '/tv',
          'langcode' => 'en',
        ]);
        $alias->save();
        echo "OK    path alias /tv -> $system_path\n";
      }
      else {
        echo "SKIP  path alias /tv (already set)\n";
      }
    }
    catch (\Throwable $e) {
      echo "ERROR setting /tv alias: {$e->getMessage()}\n";
    }
  }
  catch (\Throwable $e) {
    echo "ERROR node splash_page: {$e->getMessage()}\n";
  }
}

// ---------------------------------------------------------------------------
// Summary.
// ---------------------------------------------------------------------------
echo "\n== Seed summary ==\n";
echo 'Created: ' . count($created) . "\n";
foreach ($created as $line) {
  echo "  + $line\n";
}
echo 'Skipped (already existed): ' . count($skipped) . "\n";
foreach ($skipped as $line) {
  echo "  - $line\n";
}
echo "Done.\n";
