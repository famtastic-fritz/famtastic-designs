<?php
/** CLI private env setup. Reads existing mailbox credentials from encrypted stdin. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--apply-exact-locs',$argv,true)) exit(1);
umask(0077);
$root='/home/nineoo/customer-sites/tighten-up-your-locs/private/owner-app';
$c=json_decode(file_get_contents($root.'/credentials.json'),true,512,JSON_THROW_ON_ERROR);
$mail=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
if(!is_array($mail)||!isset($mail['password'])||($mail['email']??'')!=='hello@tightenupyourlocs.com') throw new RuntimeException('mail_scope_invalid');
if(file_exists($root.'/.env')) throw new RuntimeException('environment_exists_no_overwrite');
$values=['APP_NAME'=>'Tighten Up Your Locs','APP_ENV'=>'production','APP_KEY'=>$c['app_key'],'APP_DEBUG'=>'false','APP_URL'=>'https://tightenupyourlocs.com','APP_LOCALE'=>'en','DB_CONNECTION'=>'mysql','DB_HOST'=>'localhost','DB_PORT'=>'3306','DB_DATABASE'=>$c['database'],'DB_USERNAME'=>$c['runtime_user'],'DB_PASSWORD'=>$c['runtime_password'],'SESSION_DRIVER'=>'database','SESSION_COOKIE'=>'__Host-locs_session','SESSION_ENCRYPT'=>'true','SESSION_SECURE_COOKIE'=>'true','SESSION_SAME_SITE'=>'lax','CACHE_STORE'=>'database','QUEUE_CONNECTION'=>'database','MAIL_MAILER'=>'smtp','MAIL_SCHEME'=>'smtps','MAIL_HOST'=>'p3plzcpnl506112.prod.phx3.secureserver.net','MAIL_PORT'=>'465','MAIL_USERNAME'=>$mail['email'],'MAIL_PASSWORD'=>$mail['password'],'MAIL_FROM_ADDRESS'=>$mail['email'],'MAIL_FROM_NAME'=>'Tighten Up Your Locs','LOCS_OWNER_EMAIL'=>'junyeismom@gmail.com','LOCS_MAIL_ENABLED'=>'false','LOCS_PUBLIC_BOOKING_ENABLED'=>'false','LOG_LEVEL'=>'error'];
$f=fopen($root.'/.env','x');if(!$f) throw new RuntimeException('environment_create_failed');
foreach($values as $key=>$value){if(preg_match('/[\r\n\x00]/',(string)$value))throw new RuntimeException('invalid_environment_value');fwrite($f,$key.'="'.str_replace(['\\','"'],['\\\\','\\"'],(string)$value).'"'.PHP_EOL);}fclose($f);
foreach(['framework/cache/data','framework/sessions','framework/views','logs','app/private'] as $dir) if(!is_dir($root.'/storage/'.$dir))mkdir($root.'/storage/'.$dir,0700,true);
echo "Independent private environment configured; booking and mail disabled pending acceptance.\n";
