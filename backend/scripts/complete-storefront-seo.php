<?php

/**
 * Idempotently completes customer-facing metadata and CTA fields.
 */

$definitions = [
  'AI Chatbot Solutions' => ['AI Chatbots for Small Businesses', 'Give website visitors approved answers, lead qualification, and a clear human handoff with an AI chatbot designed around your business.', 'Explore AI Chatbot Solutions', '/start?service=ai-chatbot'],
  'Client Portal Systems' => ['Branded Customer Portal Systems', 'Give customers one secure mobile-friendly place for projects, files, messages, purchases, support, education, and account settings.', 'Plan My Customer Portal', '/start?service=client-portal-systems'],
  'Custom Website Development' => ['Custom Website Development', 'Build a maintainable website around your customer journey, content, integrations, measurement, and business operations—not a generic template.', 'Scope My Custom Website', '/start?service=custom-website-development'],
  'E-Commerce Solutions' => ['Ecommerce Website Solutions', 'Plan and build secure ecommerce journeys connecting catalog, checkout, payments, fulfillment, customer accounts, notifications, and reporting.', 'Plan My Ecommerce System', '/start?service=e-commerce-solutions'],
  'Landing Page Design' => ['Conversion-Focused Landing Pages', 'Create a focused campaign page with a clear offer, mobile conversion path, attribution, lead routing, acknowledgment, and measurable follow-up.', 'Plan My Landing Page', '/start?service=landing-page-design'],
  'Site Rebuild & Redesign' => ['Website Redesign and Rebuild', 'Replace an outdated website with a faster, mobile-first experience built around clearer content, trustworthy proof, useful actions, and measurement.', 'Plan My Website Rebuild', '/start?service=site-rebuild'],
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
foreach ($definitions as $title => [$metaTitle, $description, $cta, $href]) {
  $matches = $storage->loadByProperties(['type' => 'service_page', 'title' => $title]);
  if (!$matches) throw new RuntimeException("Missing service page: {$title}");
  $node = reset($matches);
  $node->set('field_meta_title', $metaTitle);
  $node->set('field_meta_description', $description);
  $node->set('field_cta_text', $cta);
  $node->set('field_cta_link', ['uri' => 'internal:' . $href, 'title' => $cta]);
  $node->save();
}

$packages = $storage->loadByProperties(['type' => 'package_page', 'title' => 'Web Basics Bundle — $199']);
if (!$packages) throw new RuntimeException('Missing Web Basics package page.');
$package = reset($packages);
$package->set('field_meta_title', 'Web Basics Website Bundle — $199');
$package->set('field_meta_description', 'Launch one focused business website for $199 with a mobile-ready page, foundational SEO, lead capture, first-year hosting, and a clear domain path.');
$package->set('field_cta_text', 'Buy Web Basics Securely — $199');
$package->set('field_cta_link', ['uri' => 'internal:/buy', 'title' => 'Buy Web Basics Securely — $199']);
$package->save();

echo "Storefront metadata and CTAs are complete.\n";
