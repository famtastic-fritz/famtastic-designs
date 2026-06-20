<?php

/**
 * @file
 * Seed sample Proof Artifact nodes for the Designs gallery.
 *
 * Run with: ./vendor/bin/drush php:script scripts/seed-proof-artifacts.php
 */

use Drupal\node\Entity\Node;

$artifacts = [
  [
    'title' => 'Cyber_Blade',
    'industry' => 'Fashion / Footwear',
    'summary' => 'Raw concept render turned into a tech-wear footwear campaign hero.',
    'before' => 'https://placehold.co/600x400/111/333?text=Before+Cyber_Blade',
    'after' => 'https://placehold.co/600x400/FF4500/fff?text=After+Cyber_Blade',
    'metrics' => '{"conversion_lift": "+34%", "bounce_rate": "-18%", "time_on_page": "+42s"}',
  ],
  [
    'title' => 'Neural Drift',
    'industry' => 'SaaS / Creative Tools',
    'summary' => 'Lo-fi UI mockup evolved into a cinematic overlay package launch page.',
    'before' => 'https://placehold.co/600x400/111/333?text=Before+Neural+Drift',
    'after' => 'https://placehold.co/600x400/00E5FF/000?text=After+Neural+Drift',
    'metrics' => '{"trial_signups": "+210%", "page_views": "+56k", "share_rate": "+12%"}',
  ],
  [
    'title' => 'Data Stream',
    'industry' => 'E-Commerce / Accessories',
    'summary' => 'Basic product photo rebuilt as a cargo-system rigid backpack drop.',
    'before' => 'https://placehold.co/600x400/111/333?text=Before+Data+Stream',
    'after' => 'https://placehold.co/600x400/a200ff/fff?text=After+Data+Stream',
    'metrics' => '{"add_to_cart": "+28%", "revenue_per_session": "+19%", "return_visits": "+41%"}',
  ],
];

foreach ($artifacts as $artifact) {
  $existing = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties(['type' => 'proof_artifact', 'title' => $artifact['title']]);

  if ($existing) {
    print "Skipping existing: {$artifact['title']}\n";
    continue;
  }

  $node = Node::create([
    'type' => 'proof_artifact',
    'title' => $artifact['title'],
    'status' => 1,
    'uid' => 1,
    'body' => [
      [
        'value' => $artifact['summary'],
        'format' => 'plain_text',
      ],
    ],
    'field_industry' => $artifact['industry'],
    'field_story_summary' => [
      [
        'value' => $artifact['summary'],
        'format' => 'plain_text',
      ],
    ],
    'field_before_url' => [
      [
        'uri' => $artifact['before'],
        'title' => 'Before',
        'options' => [],
      ],
    ],
    'field_after_url' => [
      [
        'uri' => $artifact['after'],
        'title' => 'After',
        'options' => [],
      ],
    ],
    'field_metrics' => $artifact['metrics'],
    'field_is_public' => TRUE,
    'field_published_at' => \Drupal::time()->getRequestTime(),
  ]);
  $node->save();
  print "Created: {$artifact['title']} (nid {$node->id()})\n";
}

print "Seed complete.\n";
