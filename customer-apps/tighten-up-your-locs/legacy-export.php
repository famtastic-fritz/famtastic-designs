<?php
/** Drush CLI only, exact Locs site, count-only by default. Freeze before export. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$site='site-dffd4cb9c3aa47fd';$db=\Drupal::database();$mode=getenv('LOCS_MIGRATION_MODE')?:'inspect';
$tables=['famtastic_booking_request','famtastic_booking_appointment','famtastic_booking_appointment_event','famtastic_booking_availability'];
$counts=[];foreach($tables as $t)$counts[$t]=(int)$db->select($t,'r')->condition('site_key',$site)->countQuery()->execute()->fetchField();
$binding=$db->select('famtastic_booking_site_owner','b')->fields('b')->condition('site_key',$site)->execute()->fetchAssoc();
if(!$binding||(int)$binding['customer_id']!==11||(int)$binding['organization_id']!==11)throw new RuntimeException('owner_binding_mismatch');
if($mode==='inspect'){echo json_encode(['counts'=>$counts,'binding_status'=>$binding['status'],'writes'=>0]).PHP_EOL;return;}
if(!in_array($mode,['freeze-exact-locs','export-frozen-locs','restore-exact-locs'],true))throw new RuntimeException('mode_invalid');
// This migration only supports request-only legacy inventory. New appointments stop cutover.
if($counts['famtastic_booking_appointment']||$counts['famtastic_booking_appointment_event']||$counts['famtastic_booking_availability'])throw new RuntimeException('inventory_changed_requires_expanded_migration');
$config=\Drupal::configFactory()->getEditable('famtastic_pipeline.settings');
if($mode==='freeze-exact-locs'||$mode==='restore-exact-locs') {
    $active=$mode==='restore-exact-locs';
    foreach(['booking_request_enabled_sites','booking_availability_enabled_sites'] as $key){$v=$config->get($key)?:[];if(!is_array($v))throw new RuntimeException('configuration_invalid');$v=array_values(array_filter($v,fn($x)=>$x!==$site));if($active)$v[]=$site;$config->set($key,$v);}
    $config->save(true);
    $db->update('famtastic_booking_site_owner')->fields(['status'=>$active?'active':'retired','changed'=>time()])->condition('site_key',$site)->condition('customer_id',11)->execute();
    echo json_encode(['status'=>$active?'restored':'frozen','counts'=>$counts,'records_deleted'=>0]).PHP_EOL;return;
}
if($binding['status']!=='retired'||in_array($site,$config->get('booking_request_enabled_sites')?:[],true))throw new RuntimeException('freeze_required');
$records=$db->select('famtastic_booking_request','r')->fields('r')->condition('site_key',$site)->orderBy('id')->execute()->fetchAll(\Drupal\Core\Database\Statement\FetchAs::Associative);
// Private export is delivered through encrypted SSH to a 0600 operator file; never print to chat.
echo json_encode(['schema'=>'locs.request-migration.v1','site_key'=>$site,'records'=>$records],JSON_THROW_ON_ERROR).PHP_EOL;
