<?php

/**
 * @file
 * Idempotently aligns public package pages with the canonical offer ladder.
 */

$definitions = [
  '/packages/199-quick-start' => ['title' => 'Web Basics Bundle — $199', 'headline' => 'Get a Professional Website for $199', 'subheadline' => 'One focused page, one year of basic hosting, and a clear domain path.', 'timeline' => 'Timing confirmed after intake', 'best_for' => 'Businesses that need a credible first website and can explain the core offer on one focused page.', 'cta' => 'Start My Web Basics Website — $199', 'href' => '/buy', 'features' => ['One focused one-page business website', 'Mobile-responsive design', 'Lead-capture form', 'Foundational search and indexing setup', 'First-year basic managed hosting', 'Available new-domain registration for year one, or existing-domain connection']],
  '/packages/499-site-upgrade' => ['title' => 'Business Website Bundle — $499', 'headline' => 'Launch or Upgrade a Business Website for $499', 'subheadline' => 'Up to five focused pages with lead capture, analytics, SEO foundations, and a structured launch path.', 'timeline' => 'Timing confirmed after intake', 'best_for' => 'Businesses that need several standard pages for services, trust, customer questions, and lead capture—whether the site is new or replacing an old one.', 'cta' => 'Start My Business Website — $499', 'href' => '/start?package=business-website', 'features' => ['Up to five standard business pages', 'Mobile-first implementation', 'Lead capture and owner routing', 'Foundational on-page SEO', 'Google Analytics connection', 'Two consolidated revision rounds', 'First-year business managed hosting', 'Available new-domain registration for year one, or existing-domain connection']],
  '/packages/starter-website' => ['title' => 'Custom Website — $1,999', 'headline' => 'A Fully Custom Website Foundation', 'subheadline' => 'Custom discovery, information architecture, visual design, implementation, and handoff for a distinctive business presence.', 'best_for' => 'Businesses whose brand, content structure, or customer journey needs original design and deeper discovery beyond the structured $499 bundle.', 'cta' => 'Scope My Custom Website — $1,999', 'href' => '/start?package=custom-website', 'features' => ['Up to five fully custom page designs', 'Discovery and content architecture', 'Original mobile-first visual system', 'Lead capture and analytics foundation', 'Foundational SEO implementation', 'Two revision rounds', 'Training and handoff']],
  '/packages/business-website' => ['title' => 'Business Growth System — $3,999', 'headline' => 'Business Website + Connected Growth Systems', 'subheadline' => 'A broader website connected to lead capture, automation, customer experience, and business reporting.', 'best_for' => 'Growing businesses that need the website to connect with customer acquisition and operations, not only publish information.', 'cta' => 'Scope My Growth System — $3,999', 'href' => '/start?package=business-growth'],
  '/packages/premium-website-ai' => ['title' => 'Premium Website + AI System — $6,999', 'headline' => 'Premium Website, Portal, Automation, and AI', 'subheadline' => 'A custom digital business system with deeper content, customer workflows, integrations, and governed AI assistance.', 'best_for' => 'Established businesses ready to connect a premium website with customer service, automation, portal capabilities, analytics, and governed AI.', 'cta' => 'Scope My Premium System — $6,999', 'href' => '/start?package=premium-ai'],
  '/packages/landing-page' => ['title' => 'Campaign Landing Page System — $1,499', 'headline' => 'A Complete Paid-Campaign Landing System', 'subheadline' => 'A conversion-focused campaign destination with attribution, routing, measurement, and follow-up—not a basic business website.', 'best_for' => 'Businesses with a defined offer and paid or targeted traffic that need a measurable campaign funnel. This is not the $199 first-website offer.', 'cta' => 'Plan My Campaign System — $1,499', 'href' => '/start?package=campaign-landing-page', 'features' => ['Campaign strategy and single-focus page', 'Conversion-focused content structure', 'Lead capture and owner routing', 'UTM and conversion-event tracking', 'Dedicated thank-you experience', 'A/B-test-ready implementation', 'Fourteen days of launch support']],
  '/packages/website-care-plan' => ['title' => 'Website Care & Maintenance — $149/month', 'headline' => 'Ongoing Website Care & Maintenance', 'subheadline' => 'Keep an existing site maintained, monitored, supported, and ready for measured improvement.', 'best_for' => 'Businesses with a live website that want hands-off technical care and a defined monthly support allowance.', 'cta' => 'Discuss Website Care — $149/month', 'href' => '/start?package=website-care'],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
$nodes = $storage->loadByProperties(['type' => 'package_page']);
$alias_manager = \Drupal::service('path_alias.manager');
$updated = [];
foreach ($nodes as $node) {
  $alias = $alias_manager->getAliasByPath('/node/' . $node->id());
  if (!isset($definitions[$alias])) continue;
  $item = $definitions[$alias];
  $node->setTitle($item['title']);
  $node->set('field_hero_headline', $item['headline']);
  $node->set('field_hero_subheadline', ['value' => $item['subheadline'], 'format' => 'basic_html']);
  $node->set('field_best_for', ['value' => $item['best_for'], 'format' => 'basic_html']);
  $node->set('field_cta_text', $item['cta']);
  $node->set('field_cta_link', ['uri' => 'internal:' . $item['href'], 'title' => $item['cta']]);
  if ($node->hasField('field_meta_title')) $node->set('field_meta_title', $item['title'] . ' | FAMtastic Designs');
  if ($node->hasField('field_meta_description')) $node->set('field_meta_description', $item['subheadline']);
  if (isset($item['timeline'])) $node->set('field_timeline', $item['timeline']);
  if (isset($item['features'])) $node->set('field_features', array_map(fn ($value) => ['value' => $value], $item['features']));
  $node->save();
  $updated[] = $alias . ' => ' . $item['title'];
}
if (count($updated) !== count($definitions)) {
  throw new RuntimeException(sprintf('Expected %d package pages, updated %d.', count($definitions), count($updated)));
}
echo "Package ladder normalized:\n- " . implode("\n- ", $updated) . "\n";
