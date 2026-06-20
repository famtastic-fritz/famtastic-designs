<?php

/**
 * @file
 * Seed sample SiteBlock, ServiceOffering, CampaignPage, and Testimonial nodes.
 *
 * Run with: ./vendor/bin/drush php:script scripts/seed-designs-content.php
 */

use Drupal\node\Entity\Node;

function ensure_not_exists(string $type, string $title): bool {
  $existing = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties(['type' => $type, 'title' => $title]);
  if ($existing) {
    print "Skipping existing: {$title}\n";
    return TRUE;
  }
  return FALSE;
}

// ---------------------------------------------------------------------------
// SiteBlocks for the front page.
// ---------------------------------------------------------------------------
$site_blocks = [
  ['page' => 'index', 'section' => 'hero', 'key' => 'headline', 'type' => 'text', 'value' => 'FEARLESS DEVIATION'],
  ['page' => 'index', 'section' => 'hero', 'key' => 'subhead', 'type' => 'text', 'value' => 'Unapologetic Digital Goods'],
  ['page' => 'index', 'section' => 'hero', 'key' => 'cta_text', 'type' => 'text', 'value' => 'Start a Project'],
  ['page' => 'index', 'section' => 'hero', 'key' => 'cta_url', 'type' => 'url', 'value' => '/campaign/local-business'],
  ['page' => 'index', 'section' => 'cross_promo_hosting', 'key' => 'headline', 'type' => 'text', 'value' => 'Need hosting first?'],
  ['page' => 'index', 'section' => 'cross_promo_hosting', 'key' => 'body', 'type' => 'text', 'value' => 'Power your new site on FAMtastic Hosting — domains, SSL, and 24/7 support included.'],
  ['page' => 'index', 'section' => 'cross_promo_hosting', 'key' => 'cta_url', 'type' => 'url', 'value' => 'https://famtastichosting.com/?ref=designs'],
];

foreach ($site_blocks as $block) {
  $label = "{$block['page']}:{$block['section']}:{$block['key']}";
  if (ensure_not_exists('site_block', $label)) {
    continue;
  }
  Node::create([
    'type' => 'site_block',
    'title' => $label,
    'status' => 1,
    'uid' => 1,
    'field_page' => $block['page'],
    'field_section' => $block['section'],
    'field_key_name' => $block['key'],
    'field_value_type' => $block['type'],
    'field_value_text' => [
      ['value' => $block['value'], 'format' => 'plain_text'],
    ],
    'field_updated_by' => 'system',
  ])->save();
  print "Created SiteBlock: {$label}\n";
}

// ---------------------------------------------------------------------------
// ServiceOfferings.
// ---------------------------------------------------------------------------
$services = [
  [
    'title' => 'Web Design',
    'slug' => 'web-design',
    'pitch' => 'High-impact sites that load fast, rank well, and convert visitors into buyers.',
    'body' => 'From landing pages to full product sites, we design for speed, clarity, and conversion. Every build ships mobile-first with Core Web Vitals in mind.',
    'price' => 249900,
    'deliverables' => '["Discovery workshop", "Figma prototype", "Responsive build", "Launch checklist"]',
    'duration' => 14,
    'icon' => 'layout',
    'order' => 10,
  ],
  [
    'title' => 'AI Automation',
    'slug' => 'ai-automation',
    'pitch' => 'Connect your tools and let agents handle the repetitive work.',
    'body' => 'We build n8n / Make workflows, custom GPT pipelines, and CRM automations that save hours every week.',
    'price' => 149900,
    'deliverables' => '["Workflow audit", "Automation map", "Deployed workflows", "Runbook"]',
    'duration' => 7,
    'icon' => 'cpu',
    'order' => 20,
  ],
  [
    'title' => 'Mobile Commerce',
    'slug' => 'mobile-commerce',
    'pitch' => 'Shop-ready apps and storefronts built for thumb-driven revenue.',
    'body' => 'Launch iOS/Android shopping experiences, PWAs, and headless storefronts tied to your inventory.',
    'price' => 399900,
    'deliverables' => '["UX flow", "App shell", "Payment integration", "App store assets"]',
    'duration' => 30,
    'icon' => 'shopping-bag',
    'order' => 30,
  ],
];

foreach ($services as $svc) {
  if (ensure_not_exists('service_offering', $svc['title'])) {
    continue;
  }
  Node::create([
    'type' => 'service_offering',
    'title' => $svc['title'],
    'status' => 1,
    'uid' => 1,
    'body' => [['value' => $svc['body'], 'format' => 'plain_text']],
    'field_slug' => $svc['slug'],
    'field_short_pitch' => [['value' => $svc['pitch'], 'format' => 'plain_text']],
    'field_starting_price' => $svc['price'],
    'field_deliverables' => $svc['deliverables'],
    'field_duration_days' => $svc['duration'],
    'field_icon' => $svc['icon'],
    'field_is_active' => TRUE,
    'field_sort_order' => $svc['order'],
  ])->save();
  print "Created ServiceOffering: {$svc['title']}\n";
}

// ---------------------------------------------------------------------------
// CampaignPage.
// ---------------------------------------------------------------------------
if (!ensure_not_exists('campaign_page', 'Local Business Launch')) {
  Node::create([
    'type' => 'campaign_page',
    'title' => 'Local Business Launch',
    'status' => 1,
    'uid' => 1,
    'field_campaign_key' => 'local_business',
    'field_slug' => 'local-business',
    'field_accent_color' => '#FF4500',
    'field_hero_headline' => [['value' => 'Look like the biggest player on your block.', 'format' => 'plain_text']],
    'field_hero_body' => [['value' => 'A focused launch package for local businesses that need a credible, fast, conversion-ready web presence in two weeks.', 'format' => 'plain_text']],
    'field_cta_label' => 'Claim Your Proof',
    'field_cta_action' => 'claim_proof',
    'field_pricing_snapshot' => '{"starter": 49900, "growth": 129900}',
  ])->save();
  print "Created CampaignPage: Local Business Launch\n";
}

// ---------------------------------------------------------------------------
// Testimonials.
// ---------------------------------------------------------------------------
$testimonials = [
  [
    'author' => 'Jordan K.',
    'role' => 'Owner, Westside Fitness',
    'quote' => 'The before/after proof was brutal in the best way. We went from a 6-second load time to under a second and saw inquiries double in a month.',
    'source' => 'email',
  ],
  [
    'author' => 'Pastor Mike',
    'role' => 'Grace Community Church',
    'quote' => 'They captured what we do better than we could say it ourselves. The new site actually gets people through the door.',
    'source' => 'video',
  ],
];

foreach ($testimonials as $t) {
  if (ensure_not_exists('testimonial', $t['author'])) {
    continue;
  }
  Node::create([
    'type' => 'testimonial',
    'title' => $t['author'],
    'status' => 1,
    'uid' => 1,
    'field_role' => $t['role'],
    'field_quote' => [['value' => $t['quote'], 'format' => 'plain_text']],
    'field_source' => $t['source'],
    'field_is_active' => TRUE,
  ])->save();
  print "Created Testimonial: {$t['author']}\n";
}

print "Seed complete.\n";
