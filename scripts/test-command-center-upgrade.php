<?php
/** Existing-site upgrade rehearsal preserving synthetic campaigns and conversations. */
if (\Drupal::database()->driver() !== 'sqlite' || getenv('FAMTASTIC_EMAIL_TRANSPORT') !== 'memory') throw new RuntimeException('Disposable runtime required.');
$db=\Drupal::database();$schema=$db->schema();
$before=['campaigns'=>(int)$db->select('famtastic_campaign','c')->countQuery()->execute()->fetchField(),'threads'=>(int)$db->select('famtastic_portal_thread','t')->countQuery()->execute()->fetchField()];
// Rehearse only new-field additions without destroying drafts from the flow fixture.
foreach (['famtastic_campaign'=>['plan_json','revision'],'famtastic_portal_thread'=>['staff_label']] as $table=>$fields) foreach($fields as $field) $schema->dropField($table,$field);
\Drupal::moduleHandler()->loadInclude('famtastic_pipeline','install');$sandbox=[];famtastic_pipeline_update_8066($sandbox);famtastic_pipeline_update_8066($sandbox);
$after=['campaigns'=>(int)$db->select('famtastic_campaign','c')->countQuery()->execute()->fetchField(),'threads'=>(int)$db->select('famtastic_portal_thread','t')->countQuery()->execute()->fetchField()];
if($before!==$after)throw new RuntimeException('Upgrade changed record counts');
if($db->select('famtastic_portal_thread','t')->fields('t',['staff_label'])->range(0,1)->execute()->fetchField()!=='active')throw new RuntimeException('Upgrade label default');
print "PASS pre8066 schema upgrade twice; campaign/thread records retained and explicit default active\n";
