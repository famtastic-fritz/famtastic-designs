<?php
/** Recreate only pre-release staff schema in a disposable installed Drupal. */
if (\Drupal::database()->driver() !== 'sqlite' || getenv('FAMTASTIC_EMAIL_TRANSPORT') !== 'memory') throw new RuntimeException('Disposable runtime required.');
$db = \Drupal::database(); $schema = $db->schema();
$counts = [];
foreach (['famtastic_campaign', 'famtastic_portal_thread'] as $table) $counts[$table] = (int) $db->select($table, 't')->countQuery()->execute()->fetchField();
\Drupal::state()->set('command_center_upgrade_baseline', $counts);
foreach (['famtastic_campaign' => ['plan_json', 'revision'], 'famtastic_portal_thread' => ['staff_label']] as $table => $fields) foreach ($fields as $field) $schema->dropField($table, $field);
foreach (['famtastic_message_draft_revision', 'famtastic_message_draft', 'famtastic_ai_receipt'] as $table) $schema->dropTable($table);
\Drupal::keyValue('system.schema')->set('famtastic_pipeline', 8067);
print "Prepared disposable schema8067: invoice/acquisition tables retained; only new staff schema removed.\n";
