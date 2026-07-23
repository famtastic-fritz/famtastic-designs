<?php

/**
 * @file
 * Seeds FAMtastic Designs v2 storefront content:
 *   - 6 service_page nodes
 *   - 7 package_page nodes
 *   - 13 faq_item nodes
 *   - Updates the existing homepage node (hero/why/process/stats/final CTA)
 *   - Exact path aliases for all service + package pages
 *
 * Run from backend/ inside a bootstrapped Drupal:
 *   vendor/bin/drush php:script scripts/seed-storefront.php
 *
 * IDEMPOTENT: nodes are skipped when a node of the same type+title exists,
 * path aliases are skipped when the alias already exists, and the homepage
 * update is deterministic (same final state on every run).
 */

use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\path_alias\Entity\PathAlias;

$etm = \Drupal::entityTypeManager();
$node_storage = $etm->getStorage('node');
$alias_storage = $etm->getStorage('path_alias');

$created = [];
$skipped = [];

echo "== FAMtastic Designs v2 — storefront seed ==\n";

/**
 * Create a paragraph and return the ERR value array.
 */
$make_paragraph = function ($type, array $values) {
  $values['type'] = $type;
  $p = Paragraph::create($values);
  $p->save();
  return [
    'target_id' => $p->id(),
    'target_revision_id' => $p->getRevisionId(),
  ];
};

/**
 * Build a process_step paragraph (auto step number).
 */
$make_step = function ($number, $title, $description) use ($make_paragraph) {
  return $make_paragraph('process_step', [
    'field_step_number' => $number,
    'field_step_title' => $title,
    'field_step_description' => [
      'value' => $description,
      'format' => 'basic_html',
    ],
  ]);
};

/**
 * Normalize a process step to [title, description]. Accepts either a
 * "Title — Description" string (em dash) or a [title, description] pair.
 */
$split_step = function ($step) {
  if (is_array($step)) {
    return [$step[0], $step[1]];
  }
  $parts = explode(' — ', $step, 2);
  return [trim($parts[0]), trim($parts[1] ?? '')];
};

/**
 * Build a faq_qa paragraph.
 */
$make_faq = function ($question, $answer) use ($make_paragraph) {
  return $make_paragraph('faq_qa', [
    'field_question' => $question,
    'field_answer' => [
      'value' => $answer,
      'format' => 'basic_html',
    ],
  ]);
};

/**
 * Set an exact path alias for a node, skipping if the alias exists.
 */
$set_alias = function ($nid, $alias) use ($alias_storage, &$created, &$skipped) {
  $existing = $alias_storage->loadByProperties(['alias' => $alias]);
  if (!empty($existing)) {
    $skipped[] = "alias $alias";
    echo "SKIP  alias $alias (exists)\n";
    return;
  }
  PathAlias::create([
    'path' => '/node/' . $nid,
    'alias' => $alias,
    'langcode' => 'en',
  ])->save();
  $created[] = "alias $alias -> /node/$nid";
  echo "OK    alias $alias -> /node/$nid\n";
};

/**
 * Idempotent node lookup by type+title.
 */
$find_node = function ($type, $title) use ($node_storage) {
  $nodes = $node_storage->loadByProperties(['type' => $type, 'title' => $title]);
  return $nodes ? reset($nodes) : NULL;
};

// ---------------------------------------------------------------------------
// 1. Service pages.
// ---------------------------------------------------------------------------
$service_defaults = [
  'pain_points_title' => "You're Losing Money Because...",
  'solution_title' => "Here's What Changes",
  'process_title' => 'How It Works',
  'features_title' => "What's Included",
  'faq_title' => 'Frequently Asked Questions',
];

$services = [
  [
    'title' => 'AI Chatbot Solutions',
    'alias' => '/services/ai-chatbot',
    'sort' => 1,
    'hero_headline' => 'Stop Losing Customers to Voicemail',
    'hero_subheadline' => 'An AI chatbot trained on YOUR business that answers questions, books appointments, and qualifies leads — 24/7.',
    'pain_points' => [
      '60% of customers call after hours and get voicemail — they call your competitor next',
      "You're spending 2+ hours/day answering the same 10 questions",
      'Every missed call is a $500-$5,000 job going to someone else',
    ],
    'solution_bullets' => [
      'Trained on your specific services, pricing, and FAQs',
      'Answers questions, books appointments, routes emergencies',
      'Qualifies leads before they reach you',
      'Works on your website, Facebook Messenger, and SMS',
      'Gets smarter over time',
    ],
    'process_steps' => [
      'Train — We feed it your business info',
      'Install — We add it to your site in one day',
      'Launch — It starts handling conversations',
      'Optimize — We refine based on real chats',
    ],
    'testimonial_quote' => 'The AI chatbot handles 80% of our inquiries. I went from 2 hours on email to 15 minutes.',
    'testimonial_attribution' => 'James R., HVAC Company, Jensen Beach',
    'features' => [
      'AI chatbot trained on your business',
      'Website integration',
      'Facebook Messenger integration',
      'SMS integration',
      'Lead qualification and routing',
      'Appointment scheduling',
      'Conversation analytics dashboard',
      '48-hour setup',
    ],
    'faq_qa' => [
      ['Will it sound like a robot?', 'No. We train it on your actual business language. It answers like you would — just faster and always available.'],
      ["What if it can't answer something?", 'It escalates to you via email or SMS with the full conversation history.'],
      ['How long does setup take?', '48 hours from when you provide your business information.'],
      ['Can I change what it says?', 'Yes. You get a dashboard to review conversations and request changes.'],
      ['How much does it cost?', '$199 one-time setup. We also offer ongoing optimization plans.'],
    ],
    'cta_text' => 'Add AI Chatbot to My Site',
    'cta_link' => '/contact?service=ai-chatbot',
  ],
  [
    'title' => 'Site Rebuild & Redesign',
    'alias' => '/services/site-rebuild',
    'sort' => 2,
    'hero_headline' => 'Your Website Is Costing You Customers',
    'hero_subheadline' => "Slow, outdated, broken-on-mobile sites lose 40% of visitors in 3 seconds. Let's fix that.",
    'pain_points' => [
      '40% of visitors leave if your site takes more than 3 seconds to load',
      "57% won't recommend a business with a bad mobile website",
      "Google penalizes slow sites — you're ranking lower than you should",
      "Your competitor's site looks modern. Yours looks like 2012.",
    ],
    'solution_bullets' => [
      'Full custom redesign — not a template, built for YOUR brand',
      'Mobile-first, speed-optimized (under 2-second load time)',
      "SEO overhaul — you'll rank higher in local search",
      'Lead capture that actually converts',
      'Live in 1 week, not 2 months',
    ],
    'process_steps' => [
      "Audit — Free analysis of what's broken",
      'Design — You see the new design before we build',
      'Build — Speed, mobile, SEO, lead capture',
      'Launch — Go live with analytics to prove it works',
    ],
    'testimonial_quote' => 'Fritz rebuilt our site and appointment bookings went up 40% in the first month.',
    'testimonial_attribution' => 'Dr. Marcus J., Medical Practice, Port St. Lucie',
    'features' => [
      'Full custom redesign (3-5 pages)',
      'Mobile-first responsive build',
      'Speed optimization (Core Web Vitals)',
      'SEO overhaul + meta tags',
      'Lead capture forms',
      'Google Analytics setup',
      '2 rounds of revisions',
      '1-week delivery',
    ],
    'faq_qa' => [
      ['How much does a rebuild cost?', 'Starting at $499 for a 3-page rebuild. Custom quotes for larger sites.'],
      ['Will I lose my current content?', 'No. We migrate all your existing content, images, and SEO value.'],
      ['How long does it take?', 'Most rebuilds are live in 1 week.'],
      ['Can I see the design before you build?', 'Absolutely. You approve the design before we write a single line of code.'],
    ],
    'cta_text' => 'Get My Free Website Audit',
    'cta_link' => '/contact?service=rebuild',
  ],
  [
    'title' => 'Custom Website Development',
    'alias' => '/services/custom-website-development',
    'sort' => 3,
    'hero_headline' => 'Your Business Deserves Better Than a Template',
    'hero_subheadline' => 'Hand-built websites designed around YOUR goals. Not a template. Not a page builder. Just clean, fast, conversion-focused code.',
    'pain_points' => [
      "Template sites look like everyone else's — you blend in",
      'DIY builders are slow, limited, and look unprofessional',
      'Your website should reflect the quality of your business',
      "You need custom features that templates can't handle",
    ],
    'solution_bullets' => [
      'Built from scratch for your specific business and goals',
      'Mobile-first, SEO-ready from day one',
      'Contact forms + lead capture that actually work',
      'Google Analytics + conversion tracking',
      'You own the code — no platform lock-in',
    ],
    'process_steps' => [
      'Discover — 30-minute call to understand your goals',
      'Design — Wireframes + visual design, you approve everything',
      'Build — Clean code, fast performance, thorough testing',
      'Launch — Go live + training session so you can make updates',
    ],
    'testimonial_quote' => "Fritz didn't just build a website — he built us a system. Our intake process went from 15 emails to one form.",
    'testimonial_attribution' => 'Dr. Marcus J., Medical Practice Owner, Port St. Lucie',
    'features' => [
      'Custom design (no templates)',
      'Mobile-responsive build',
      'SEO-optimized structure',
      'Fast loading speeds',
      'Contact forms + lead capture',
      'Google Analytics setup',
      '2 rounds of revisions',
      'Training session',
      '30 days post-launch support',
    ],
    'faq_qa' => [],
    'cta_text' => 'Start My Custom Website',
    'cta_link' => '/contact?service=custom',
  ],
  [
    'title' => 'Landing Page Design',
    'alias' => '/services/landing-page-design',
    'sort' => 4,
    'hero_headline' => 'Every Ad Dollar Deserves a Page That Converts',
    'hero_subheadline' => 'Landing pages built for one job: turning clicks into customers. A/B-ready. Fast. Focused.',
    'pain_points' => [
      'Sending ad traffic to your homepage kills conversion',
      'A 1-second delay drops conversions by 7%',
      'Most businesses have no idea which ads actually work',
      "Generic pages don't match the ad promise",
    ],
    'solution_bullets' => [
      'Single-focus design (one goal = one conversion action)',
      'A/B-test ready structure',
      'Lead capture with direct CRM/email routing',
      'UTM tracking — know exactly which ad drove each lead',
      'Thank-you page with clear next steps',
    ],
    'process_steps' => [
      ['Define the Conversion Goal', 'Call, form, or purchase — one page, one goal.'],
      ['Write & Design', 'Write copy and design the page.'],
      ['Build & Integrate', 'Build the page and integrate with your CRM/ads.'],
      ['Launch & Track', 'Launch and track performance.'],
    ],
    'testimonial_quote' => 'Cost per lead dropped 35%. Conversion went from 2.1% to 4.7%.',
    'testimonial_attribution' => 'Cassandra T., Real Estate Broker, Stuart, FL',
    'features' => [
      'Single high-converting page',
      'Lead capture form',
      'Thank-you page',
      'Mobile optimization',
      'UTM tracking setup',
      'Analytics + heatmap setup',
      '1 round of revisions',
      '14 days post-launch support',
    ],
    'faq_qa' => [],
    'cta_text' => 'Build My Landing Page',
    'cta_link' => '/contact?service=landing',
  ],
  [
    'title' => 'Client Portal Systems',
    'alias' => '/services/client-portal-systems',
    'sort' => 5,
    'hero_headline' => 'Your Clients Expect a Professional Experience',
    'hero_subheadline' => 'Private, branded portals where clients log in, view projects, pay invoices, and communicate — all under YOUR brand.',
    'pain_points' => [
      'Managing clients through email chains is chaotic',
      'Clients ask "what\'s the status?" constantly',
      'Chasing invoices wastes hours every week',
      'Your competitors look more professional than you',
    ],
    'solution_bullets' => [
      'Branded login page (your logo, your colors, your domain)',
      'Client dashboard with project status, files, messages',
      'Secure file upload and download',
      'Integrated online payments',
      'Mobile-responsive — works on any device',
    ],
    'process_steps' => [
      'Brand — Your logo, colors, and domain',
      'Configure — Set up features and client permissions',
      'Import — Load existing client data',
      'Launch — Train your team and invite clients',
    ],
    'testimonial_quote' => 'Client satisfaction improved 45%. I save 12 hours a week on admin. Clients love checking status and paying online.',
    'testimonial_attribution' => 'Law Firm Owner, Fort Pierce, FL',
    'features' => [
      'Branded login page',
      'Client dashboard',
      'Project status tracking',
      'File upload/download',
      'Secure messaging',
      'Payment integration',
      'Mobile-responsive',
      'Role-based access',
      'Training session',
    ],
    'faq_qa' => [],
    'cta_text' => 'Build My Client Portal',
    'cta_link' => '/contact?service=portal',
  ],
  [
    'title' => 'E-Commerce Solutions',
    'alias' => '/services/e-commerce-solutions',
    'sort' => 6,
    'hero_headline' => 'Sell Online Without the Tech Headaches',
    'hero_subheadline' => 'Full-featured online stores with secure checkout, inventory management, and shipping. Built to grow with you.',
    'pain_points' => [
      "You're losing sales to Amazon because you don't sell online",
      'Setting up an online store feels overwhelming',
      "You need inventory, tax, shipping — it's a lot",
      'Payment security and compliance is scary',
    ],
    'solution_bullets' => [
      'Unlimited products with full catalog management',
      'Secure checkout (Stripe, PayPal, Square)',
      'Automatic inventory tracking',
      'Shipping rate calculation built-in',
      'Abandoned cart recovery',
    ],
    'process_steps' => [
      'Setup — Products, photos, pricing, payment methods',
      'Configure — Shipping rates, tax rules, notifications',
      'Design — Branded store that matches your business',
      'Launch — Test orders + go live + training',
    ],
    'testimonial_quote' => 'Online sales exceeded in-store revenue within 6 months. The store runs itself.',
    'testimonial_attribution' => 'Boutique Owner, Vero Beach, FL',
    'features' => [
      'Product catalog setup',
      'Secure payment processing',
      'Inventory management',
      'Shipping rate calculation',
      'Tax calculation',
      'Customer accounts',
      'Abandoned cart recovery',
      'Email notifications',
      'Analytics and reporting',
    ],
    'faq_qa' => [],
    'cta_text' => 'Start My Online Store',
    'cta_link' => '/contact?service=ecommerce',
  ],
];

foreach ($services as $svc) {
  if ($find_node('service_page', $svc['title'])) {
    $skipped[] = "node service_page '{$svc['title']}'";
    echo "SKIP  node service_page '{$svc['title']}' (exists)\n";
    continue;
  }
  try {
    $steps = [];
    $n = 0;
    foreach ($svc['process_steps'] as $step_text) {
      $n++;
      [$st, $sd] = $split_step($step_text);
      $steps[] = $make_step(str_pad((string) $n, 2, '0', STR_PAD_LEFT), $st, $sd);
    }
    $faqs = [];
    foreach ($svc['faq_qa'] as [$q, $a]) {
      $faqs[] = $make_faq($q, $a);
    }

    $node = Node::create([
      'type' => 'service_page',
      'title' => $svc['title'],
      'uid' => 1,
      'status' => 1,
      'field_sort_order' => $svc['sort'],
      'field_hero_headline' => $svc['hero_headline'],
      'field_hero_subheadline' => $svc['hero_subheadline'],
      'field_pain_points_title' => $service_defaults['pain_points_title'],
      'field_pain_points' => $svc['pain_points'],
      'field_solution_title' => $service_defaults['solution_title'],
      'field_solution_bullets' => $svc['solution_bullets'],
      'field_process_title' => $service_defaults['process_title'],
      'field_process_steps' => $steps,
      'field_testimonial_quote' => [
        'value' => $svc['testimonial_quote'],
        'format' => 'basic_html',
      ],
      'field_testimonial_attribution' => $svc['testimonial_attribution'],
      'field_features_title' => $service_defaults['features_title'],
      'field_features' => $svc['features'],
      'field_faq_title' => $service_defaults['faq_title'],
      'field_faq_qa' => $faqs,
      'field_cta_text' => $svc['cta_text'],
      'field_cta_link' => [
        'uri' => 'internal:' . $svc['cta_link'],
        'title' => $svc['cta_text'],
      ],
    ]);
    $node->save();
    $created[] = "node service_page '{$svc['title']}' (nid {$node->id()})";
    echo "OK    node service_page '{$svc['title']}' (nid {$node->id()})\n";
    $set_alias($node->id(), $svc['alias']);
  }
  catch (\Throwable $e) {
    echo "ERROR node service_page '{$svc['title']}': {$e->getMessage()}\n";
  }
}

// ---------------------------------------------------------------------------
// 2. Package pages.
// ---------------------------------------------------------------------------
$packages = [
  [
    'title' => '$199 Quick Start',
    'alias' => '/packages/199-quick-start',
    'price' => '$199',
    'timeline' => '48 hours',
    'badge' => 'quick_start',
    'sort' => 1,
    'hero_headline' => 'Get a Professional Website for $199',
    'hero_subheadline' => 'One page. AI-optimized. Live in 48 hours.',
    'features' => [
      'One-page AI-optimized website',
      'Mobile-responsive design',
      'Lead capture form',
      'Google indexing',
      '48-hour delivery',
    ],
    'best_for' => 'Businesses with no website who need to get online fast. Perfect for startups, side hustles, and solo professionals.',
    'cta_text' => 'Get Started — $199',
    'cta_link' => '/contact?package=199',
  ],
  [
    'title' => '$499 Site Upgrade',
    'alias' => '/packages/499-site-upgrade',
    'price' => '$499',
    'timeline' => '1 week',
    'badge' => 'best_value',
    'sort' => 2,
    'hero_headline' => 'Upgrade Your Website for $499',
    'hero_subheadline' => 'From broken to brilliant — fix what your current site is costing you.',
    'features' => [
      'Everything in $199, plus:',
      '3-5 page full redesign',
      'Speed optimization',
      'SEO overhaul',
      'Lead routing',
      'Mobile-first rebuild',
    ],
    'best_for' => 'Businesses with an outdated or broken website that need a proper upgrade without breaking the bank.',
    'cta_text' => 'Upgrade My Site — $499',
    'cta_link' => '/contact?package=499',
  ],
  [
    'title' => 'Starter Website',
    'alias' => '/packages/starter-website',
    'price' => '$1,999',
    'timeline' => '2-3 weeks',
    'badge' => 'none',
    'sort' => 3,
    'hero_headline' => 'Starter Website System',
    'hero_subheadline' => 'A custom-built foundation for your business.',
    'features' => [
      'Up to 5 custom pages',
      'Mobile-first design',
      'SEO-optimized structure',
      'Contact forms + lead capture',
      'Google Analytics',
      '2 rounds of revisions',
      'Training session',
    ],
    'best_for' => 'Small businesses ready for a professional custom website.',
    'cta_text' => 'Start My Project — $1,999',
    'cta_link' => '/contact?package=starter',
  ],
  [
    'title' => 'Business Website',
    'alias' => '/packages/business-website',
    'price' => '$3,999',
    'timeline' => '4-6 weeks',
    'badge' => 'most_popular',
    'sort' => 4,
    'hero_headline' => 'Business Website + AI System',
    'hero_subheadline' => 'Your website + AI working together to grow your business.',
    'features' => [
      'Up to 10 custom pages',
      'AI chatbot integration',
      'Client portal features',
      'Advanced lead capture',
      'Workflow automation',
      'CRM integration',
      'Analytics dashboard',
      '3 rounds of revisions',
    ],
    'best_for' => 'Growing businesses that need intelligent systems, not just a website.',
    'cta_text' => 'Build My Business System — $3,999',
    'cta_link' => '/contact?package=business',
  ],
  [
    'title' => 'Premium Website + AI',
    'alias' => '/packages/premium-website-ai',
    'price' => '$6,999',
    'timeline' => '6-8 weeks',
    'badge' => 'none',
    'sort' => 5,
    'hero_headline' => 'Premium AI-Powered System',
    'hero_subheadline' => 'The full intelligence stack. Everything automated.',
    'features' => [
      '20+ custom pages',
      'Full AI chatbot system',
      'Complete client portal',
      'Workflow automation suite',
      'CRM + analytics integration',
      'Priority support',
      'Quarterly optimization reviews',
    ],
    'best_for' => 'Established businesses ready to fully automate their operations.',
    'cta_text' => 'Build My Premium System — $6,999',
    'cta_link' => '/contact?package=premium',
  ],
  [
    'title' => 'Landing Page',
    'alias' => '/packages/landing-page',
    'price' => '$1,499',
    'timeline' => '1-2 weeks',
    'badge' => 'none',
    'sort' => 6,
    'hero_headline' => 'High-Converting Landing Page',
    'hero_subheadline' => 'One page. One goal. Maximum conversions.',
    'features' => [
      'Single-focus conversion page',
      'A/B-test ready',
      'Lead capture + routing',
      'UTM tracking',
      'Thank-you page',
      '14 days support',
    ],
    'best_for' => 'Businesses running ads who need pages that convert clicks into customers.',
    'cta_text' => 'Build My Landing Page — $1,499',
    'cta_link' => '/contact?package=landing',
  ],
  [
    'title' => 'Website Care Plan',
    'alias' => '/packages/website-care-plan',
    'price' => '$149/month',
    'timeline' => 'Ongoing',
    'badge' => 'none',
    'sort' => 7,
    'hero_headline' => 'Website Care Plan',
    'hero_subheadline' => 'Your site stays secure, fast, and up-to-date.',
    'features' => [
      'Monthly security updates',
      'Speed optimization',
      'Content updates (up to 2 hrs/month)',
      '24/7 uptime monitoring',
      'Monthly performance report',
      'Priority support',
    ],
    'best_for' => 'Any business that wants hands-off website maintenance.',
    'cta_text' => 'Sign Up — $149/month',
    'cta_link' => '/contact?package=careplan',
  ],
];

foreach ($packages as $pkg) {
  if ($find_node('package_page', $pkg['title'])) {
    $skipped[] = "node package_page '{$pkg['title']}'";
    echo "SKIP  node package_page '{$pkg['title']}' (exists)\n";
    continue;
  }
  try {
    $node = Node::create([
      'type' => 'package_page',
      'title' => $pkg['title'],
      'uid' => 1,
      'status' => 1,
      'field_sort_order' => $pkg['sort'],
      'field_price' => $pkg['price'],
      'field_timeline' => $pkg['timeline'],
      'field_badge' => $pkg['badge'],
      'field_hero_headline' => $pkg['hero_headline'],
      'field_hero_subheadline' => $pkg['hero_subheadline'],
      'field_features' => $pkg['features'],
      'field_best_for' => [
        'value' => $pkg['best_for'],
        'format' => 'basic_html',
      ],
      'field_cta_text' => $pkg['cta_text'],
      'field_cta_link' => [
        'uri' => 'internal:' . $pkg['cta_link'],
        'title' => $pkg['cta_text'],
      ],
    ]);
    $node->save();
    $created[] = "node package_page '{$pkg['title']}' (nid {$node->id()})";
    echo "OK    node package_page '{$pkg['title']}' (nid {$node->id()})\n";
    $set_alias($node->id(), $pkg['alias']);
  }
  catch (\Throwable $e) {
    echo "ERROR node package_page '{$pkg['title']}': {$e->getMessage()}\n";
  }
}

// ---------------------------------------------------------------------------
// 3. FAQ items.
// ---------------------------------------------------------------------------
$faqs = [
  'General Questions' => [
    ['How long does a website take?', 'Depends on package. $199 = 48hrs. Business = 4-6 weeks.'],
    ['Do you use templates?', 'Never. Every site is custom-built.'],
    ['Will my site work on mobile?', 'Yes. Mobile-first is our standard.'],
    ['Do you offer payment plans?', 'Yes. Contact us to discuss options.'],
  ],
  'Process Questions' => [
    ['What happens during the free consultation?', 'We learn your goals, recommend the right system, give you a quote.'],
    ['What do you need from me to get started?', 'Logo, brand colors, content preferences, and any photos you want included.'],
    ['How many revisions do I get?', '2-3 depending on your package.'],
  ],
  'Technical Questions' => [
    ['What platforms do you use?', 'React, Next.js, Drupal, WordPress custom themes. We pick the right tool.'],
    ['Can I update my site myself?', 'Yes. Every project includes training.'],
    ['Will my site show up on Google?', 'Yes. SEO is built into every site.'],
    ['Do you provide ongoing support?', 'Yes. Our Care Plan covers everything.'],
  ],
  'Location Questions' => [
    ['Do you work with clients outside Port St. Lucie?', 'Yes. We work with businesses anywhere.'],
    ['Can we meet in person?', "Yes. We're based in Port St. Lucie and serve the entire Treasure Coast."],
  ],
];

$term_storage = $etm->getStorage('taxonomy_term');
foreach ($faqs as $category => $items) {
  $terms = $term_storage->loadByProperties(['vid' => 'faq_categories', 'name' => $category]);
  $term = $terms ? reset($terms) : NULL;
  if (!$term) {
    echo "ERROR faq category term '$category' not found — skipping its items\n";
    continue;
  }
  foreach ($items as [$question, $answer]) {
    if ($find_node('faq_item', $question)) {
      $skipped[] = "node faq_item '$question'";
      echo "SKIP  node faq_item '$question' (exists)\n";
      continue;
    }
    try {
      $node = Node::create([
        'type' => 'faq_item',
        'title' => $question,
        'uid' => 1,
        'status' => 1,
        'field_answer' => [
          'value' => $answer,
          'format' => 'basic_html',
        ],
        'field_faq_category' => ['target_id' => $term->id()],
      ]);
      $node->save();
      $created[] = "node faq_item '$question' (nid {$node->id()}, category $category)";
      echo "OK    node faq_item '$question' (nid {$node->id()}, category $category)\n";
    }
    catch (\Throwable $e) {
      echo "ERROR node faq_item '$question': {$e->getMessage()}\n";
    }
  }
}

// ---------------------------------------------------------------------------
// 4. Homepage update (existing node, deterministic final state).
// ---------------------------------------------------------------------------
$homepage_nids = $node_storage->getQuery()
  ->condition('type', 'homepage')
  ->accessCheck(FALSE)
  ->execute();
if (empty($homepage_nids)) {
  echo "ERROR no homepage node found — skipping homepage update\n";
}
else {
  try {
    $homepage = $node_storage->load(reset($homepage_nids));

    // Delete existing why_items / process_steps paragraphs before replacing.
    foreach (['field_why_items', 'field_process_steps'] as $field) {
      foreach ($homepage->get($field)->referencedEntities() as $paragraph) {
        $paragraph->delete();
      }
    }

    $why_data = [
      ['Engineering-Driven', 'Not a design agency that learned to code. A 22-year engineering veteran that designs. Every system is built on solid architecture.'],
      ['AI-First Approach', "We don't bolt on AI as an afterthought. Intelligence is woven into every system from day one."],
      ['Results That Matter', '40% more appointments. 80% automated inquiries. 300% lead increase. We measure success in your revenue, not our awards.'],
      ['Local Roots, Global Standards', 'Based in Port St. Lucie, serving the Treasure Coast. Built with enterprise-grade engineering.'],
    ];
    $why_items = [];
    foreach ($why_data as [$wt, $wb]) {
      $why_items[] = $make_paragraph('why_item', [
        'field_why_title' => $wt,
        'field_why_body' => [
          'value' => $wb,
          'format' => 'basic_html',
        ],
      ]);
    }

    $process_data = [
      ['01', 'Discover', '30-minute call to understand your business, goals, and challenges.'],
      ['02', 'Design', 'We architect your system. You approve everything before we build.'],
      ['03', 'Build', 'Clean code, intelligent systems, thorough testing.'],
      ['04', 'Launch', 'Your system goes live. We train your team and optimize.'],
    ];
    $process_steps = [];
    foreach ($process_data as [$num, $pt, $pd]) {
      $process_steps[] = $make_step($num, $pt, $pd);
    }

    // Replace stats items too (deterministic re-runs).
    if ($homepage->hasField('field_stats_items')) {
      foreach ($homepage->get('field_stats_items')->referencedEntities() as $paragraph) {
        $paragraph->delete();
      }
    }
    $stats_data = [
      ['22+', 'Years Engineering Experience'],
      ['100+', 'Systems Deployed'],
      ['40%', 'Average Lead Increase'],
      ['$4.2M', 'Revenue Generated for Clients'],
    ];
    $stats_items = [];
    foreach ($stats_data as [$value, $label]) {
      $stats_items[] = $make_paragraph('metric_item', [
        'field_metric_value' => $value,
        'field_metric_label' => $label,
      ]);
    }

    $homepage->set('field_hero_headline', 'Agentic AI Business Solutions Engineering Studio');
    $homepage->set('field_hero_subheadline', 'We build intelligent systems that capture leads, automate operations, and grow your business — while you sleep.');
    $homepage->set('field_why_title', 'Why FAMtastic Designs?');
    $homepage->set('field_why_items', $why_items);
    $homepage->set('field_process_title', 'How We Build Your System');
    $homepage->set('field_process_steps', $process_steps);
    $homepage->set('field_stats_items', $stats_items);
    $homepage->set('field_final_cta_title', 'Your System Should Work As Hard As You Do');
    $homepage->set('field_final_cta_body', [
      'value' => "Every day without an intelligent system is a day you're leaving money on the table. Let's fix that.",
      'format' => 'basic_html',
    ]);
    $homepage->set('status', 1);
    $homepage->save();
    $created[] = "homepage update (nid {$homepage->id()})";
    echo "OK    homepage updated (nid {$homepage->id()})\n";
  }
  catch (\Throwable $e) {
    echo "ERROR homepage update: {$e->getMessage()}\n";
  }
}

// ---------------------------------------------------------------------------
// Summary + verification.
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

echo "\n== Verification ==\n";
foreach (['service_page' => 6, 'package_page' => 7, 'faq_item' => 13] as $type => $expected) {
  $count = $node_storage->getQuery()
    ->condition('type', $type)
    ->accessCheck(FALSE)
    ->count()
    ->execute();
  $ok = ($count == $expected) ? 'OK' : 'MISMATCH';
  echo "COUNT $type: $count (expected $expected) [$ok]\n";
}

$expected_aliases = array_merge(
  array_column($services, 'alias'),
  array_column($packages, 'alias')
);
$alias_manager = \Drupal::service('path_alias.manager');
foreach ($expected_aliases as $alias) {
  $path = $alias_manager->getPathByAlias($alias);
  $ok = str_starts_with($path, '/node/') ? 'OK' : 'FAIL';
  echo "ALIAS $alias -> $path [$ok]\n";
}

echo "Done.\n";
