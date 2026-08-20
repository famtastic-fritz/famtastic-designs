<?php

declare(strict_types=1);

/**
 * Ten synthetic portal website-request scenarios.
 *
 * Run through bootstrapped Drupal:
 *   backend/vendor/bin/drush scr scripts/e2e-website-intake-scenarios.php
 *
 * The records are drafts, never contact providers, and are deleted afterward.
 */

$db = \Drupal::database();
$service = \Drupal::service('famtastic_pipeline.customer_portal');
$membership = $db->select('famtastic_membership', 'm');
$membership->join('famtastic_organization', 'o', 'o.id = m.organization_id');
$membership->fields('m', ['customer_id', 'organization_id']);
$membership->addField('o', 'public_id', 'organization_public_id');
$membership->condition('m.status', 'active')->orderBy('m.id', 'DESC')->range(0, 1);
$member = $membership->execute()->fetchAssoc();
if (!$member) {
  throw new RuntimeException('No synthetic customer workspace is available. Run the canonical customer proof first.');
}

$prefix = 'Intake scenario ' . time() . '-';
$defaults = [
  'action' => 'save',
  'organization' => $member['organization_public_id'],
  'business_name' => 'Synthetic Intake Business',
  'project_type' => 'new_website',
  'domain_choice' => 'undecided',
  'page_count' => 1,
  'primary_goal' => 'Explain the business and generate qualified inquiries.',
  'products_services' => 'A local professional service.',
  'recommendation_requested' => TRUE,
];

$scenarios = [
  'logo-ready' => ['brand_status' => 'ready'],
  'no-logo-declined' => ['brand_status' => 'no_logo_no_help'],
  'logo-help' => ['brand_status' => 'help_needed'],
  'industry-research' => ['industry' => 'Marine upholstery restoration', 'research_context' => 'Research seasonal buying behavior and local competitors.'],
  'business-model' => ['business_model' => 'Mobile service; customers request estimates by phone and pay deposits in person.'],
  'domain-email' => ['domain_choice' => 'new_domain', 'desired_domains' => "examplemarine.com\nexamplemarinefl.com", 'domain_fallback' => 'Ask before changing the name.', 'business_email_needs' => 'info@ and sales@ as full mailboxes.'],
  'reference-sites' => ['reference_sites' => 'https://example.test/liked\nhttps://example.test/disliked', 'reference_site_reasons' => 'Like the simple navigation; dislike autoplay and crowded text.'],
  'owned-infrastructure' => ['domain_choice' => 'existing_domain', 'existing_domain' => 'owned-example.test', 'existing_technology' => 'Domain at registrar; email at Google Workspace; site on Wix; source assets in GitHub.'],
  'unlisted-industry' => ['industry' => 'Custom bioluminescent aquarium installations'],
  'unlisted-request' => ['custom_needs' => 'Connect a proprietary tank-monitoring device that is not in the service catalog.'],
];

$createdRequestIds = [];
$createdProspectIds = [];
$results = [];
try {
  foreach ($scenarios as $key => $specific) {
    $input = $specific + $defaults;
    $input['project_name'] = $prefix . $key;
    $result = $service->createWebsiteRequest((int) $member['customer_id'], (string) $member['organization_public_id'], $input);
    $row = $db->select('famtastic_project_request', 'r')->fields('r')->condition('public_id', $result['public_id'])->execute()->fetchAssoc();
    $createdRequestIds[] = (int) $row['id'];
    $createdProspectIds[] = (int) $row['prospect_id'];
    $intake = $result['intake'];
    $recommendation = $intake['recommendation'];

    assert($result['status'] === 'draft');
    assert($intake['schema_version'] === 'website_discovery_v2');
    foreach ($specific as $field => $value) {
      if (in_array($field, ['domain_choice', 'existing_domain'], TRUE)) continue;
      assert(($intake[$field] ?? NULL) === (string) $value, "$key failed to preserve $field");
    }
    if ($key === 'logo-ready' || $key === 'no-logo-declined') {
      assert(!in_array('FAM-BRAND', $recommendation['suggested_addon_skus'], TRUE));
    }
    if ($key === 'logo-help') {
      assert(in_array('FAM-BRAND', $recommendation['suggested_addon_skus'], TRUE));
    }
    if ($key === 'domain-email') {
      assert(in_array('FAM-BUSINESS-EMAIL', $recommendation['suggested_addon_skus'], TRUE));
    }
    if ($key === 'unlisted-request') {
      assert($recommendation['review_required'] === TRUE);
      assert($recommendation['label'] === 'Custom scope review');
    }
    $results[$key] = [
      'recommendation' => $recommendation['label'],
      'addons' => $recommendation['suggested_addon_skus'],
      'review_required' => $recommendation['review_required'],
    ];
  }
}
finally {
  if ($createdRequestIds) {
    $db->delete('famtastic_project_request')->condition('id', $createdRequestIds, 'IN')->execute();
  }
  if ($createdProspectIds) {
    $db->delete('famtastic_customer_resource')->condition('resource_type', 'prospect')->condition('resource_id', $createdProspectIds, 'IN')->execute();
    $storage = \Drupal::entityTypeManager()->getStorage('famtastic_prospect');
    $storage->delete($storage->loadMultiple($createdProspectIds));
  }
}

print json_encode([
  'status' => 'passed',
  'classification' => 'locally_proven',
  'lane' => 'authenticated_customer_portal',
  'scenario_count' => count($results),
  'results' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

