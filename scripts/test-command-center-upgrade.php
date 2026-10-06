<?php
/** Verify real Drush8067-to8068 upgrade and repeat invocation idempotence. */
if (\Drupal::database()->driver() !== 'sqlite' || getenv('FAMTASTIC_EMAIL_TRANSPORT') !== 'memory') throw new RuntimeException('Disposable runtime required.');
if ((int) \Drupal::keyValue('system.schema')->get('famtastic_pipeline') !== 8068) throw new RuntimeException('Real updatedb did not advance schema8067 to8068.');
$db = \Drupal::database(); $schema = $db->schema();
$before = \Drupal::state()->get('command_center_upgrade_baseline');
if (!$before) throw new RuntimeException('Missing preparation baseline.');
\Drupal::moduleHandler()->loadInclude('famtastic_pipeline', 'install'); $sandbox = []; famtastic_pipeline_update_8068($sandbox);
foreach ($before as $table => $count) if ((int) $db->select($table, 't')->countQuery()->execute()->fetchField() !== $count) throw new RuntimeException('Upgrade changed record counts.');
foreach (['famtastic_message_draft', 'famtastic_message_draft_revision', 'famtastic_ai_receipt'] as $table) if (!$schema->tableExists($table)) throw new RuntimeException('Missing staff table.');
foreach (\Drupal\famtastic_pipeline\Service\AcquisitionSampleSchema::tables() as $table => $_) if (!$schema->tableExists($table)) throw new RuntimeException('Acquisition table lost.');
foreach (_famtastic_pipeline_customer_invoice_schema() as $table => $_) if (!$schema->tableExists($table)) throw new RuntimeException('Invoice table lost.');
if ($db->select('famtastic_portal_thread', 't')->fields('t', ['staff_label'])->range(0,1)->execute()->fetchField() !== 'active') throw new RuntimeException('Upgrade label default.');
print "PASS real8067→8068 Drush migration + repeat hook; campaign/thread counts, invoice/acquisition tables retained; staff schema installed and active default.\n";
