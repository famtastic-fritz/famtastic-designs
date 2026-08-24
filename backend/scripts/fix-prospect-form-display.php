<?php

/**
 * Ensures the Prospect edit form actually renders its fields.
 *
 * Root cause (owner report 2026-08-24): the Authorized-to-represent checkbox
 * and every other field were absent from /admin/famtastic/prospect/{id}/edit
 * because no entity form display existed for the default form mode. This
 * creates/updates it idempotently. Canonical copy of the same display lives
 * in backend/config/site/core.entity_form_display.famtastic_prospect.*.yml.
 *
 * Run: drush -r <root> php:script backend/scripts/fix-prospect-form-display.php
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;

$display_id = 'famtastic_prospect.famtastic_prospect.default';
$storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');
/** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $display */
$display = $storage->load($display_id);
$created = FALSE;
if (!$display) {
  $display = $storage->create([
    'id' => $display_id,
    'targetEntityType' => 'famtastic_prospect',
    'bundle' => 'famtastic_prospect',
    'mode' => 'default',
    'status' => TRUE,
  ]);
  $created = TRUE;
}

$components = [
  'business_name' => ['type' => 'string_textfield', 'weight' => 1, 'settings' => ['size' => 60, 'placeholder' => '']],
  'business_category' => ['type' => 'string_textfield', 'weight' => 2, 'settings' => ['size' => 60, 'placeholder' => '']],
  'status' => ['type' => 'options_select', 'weight' => 3],
  'contact_name' => ['type' => 'string_textfield', 'weight' => 5, 'settings' => ['size' => 60, 'placeholder' => '']],
  'contact_method' => ['type' => 'string_textfield', 'weight' => 6, 'settings' => ['size' => 20, 'placeholder' => 'email, phone, or text']],
  'contact_value' => ['type' => 'string_textfield', 'weight' => 7, 'settings' => ['size' => 60, 'placeholder' => '']],
  'public_email' => ['type' => 'email_default', 'weight' => 8, 'settings' => ['placeholder' => '']],
  'public_phone' => ['type' => 'string_textfield', 'weight' => 9, 'settings' => ['size' => 30, 'placeholder' => '']],
  'website_url' => ['type' => 'string_textfield', 'weight' => 10, 'settings' => ['size' => 60, 'placeholder' => '']],
  'service_area' => ['type' => 'string_textfield', 'weight' => 11, 'settings' => ['size' => 60, 'placeholder' => '']],
  'authorized' => ['type' => 'boolean_checkbox', 'weight' => 12, 'settings' => ['display_label' => TRUE]],
  'campaign' => ['type' => 'string_textfield', 'weight' => 13, 'settings' => ['size' => 60, 'placeholder' => '']],
  'source' => ['type' => 'string_textfield', 'weight' => 14, 'settings' => ['size' => 60, 'placeholder' => '']],
  'business_description' => ['type' => 'string_textarea', 'weight' => 15, 'settings' => ['rows' => 4, 'placeholder' => '']],
  'discovery_notes' => ['type' => 'string_textarea', 'weight' => 16, 'settings' => ['rows' => 3, 'placeholder' => '']],
];
foreach ($components as $name => $options) {
  $display->setComponent($name, $options);
}
foreach ([
  'token_hash', 'token_expires', 'token_revoked', 'confirmed_fields',
  'first_response_due', 'first_responded_at', 'sla_alerted_at', 'owner_uid',
  'next_followup_due', 'lost_reason', 'nurture_eligible', 'discovered_at',
  'created', 'changed',
] as $hidden) {
  $display->removeComponent($hidden);
}
$display->save();

print ($created ? 'created' : 'updated') . " form display $display_id with " . count($components) . " components\n";
