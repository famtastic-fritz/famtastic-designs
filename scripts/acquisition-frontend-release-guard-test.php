<?php
declare(strict_types=1);
require __DIR__.'/acquisition-frontend-release-guard.php';
$home='/home/test';$dir=$home.'/private_files/acquisition-199';$input=$dir.'/windows.json';$candidate=str_repeat('a',40);$key='fixture-only';
$config=['binding_config'=>['frontend_sha'=>$candidate]];
$files=[$dir.'/industry-clock-install-receipt.json'=>json_encode(['routine_input'=>$input]),$input=>json_encode(['config'=>$config,'signature'=>hash_hmac('sha256',json_encode($config),$key)]),$dir.'/owner-signing.key'=>$key];
$line='* * * * * cd '.$home.'/public_html && /usr/local/bin/php '.$home.'/public_html/vendor/bin/drush.php famtastic:acquisition-window --input='.$input.' >'.$home.'/deploy/famtastic-designs/acquisition-window-last-run.log 2>&1';
$cron="# FAMTASTIC_ACQUISITION_WINDOWS_V1\n".$line."\n";$passed=0;
$test=static function(string $name,string $c,array $f,string $sha,bool $allow,string $date='2026-10-08')use($home,&$passed):void{
  $okay=true;try{AcquisitionFrontendReleaseGuard::check($c,$home,$sha,static function($p)use($f){if(!isset($f[$p]))throw new RuntimeException('missing');return $f[$p];},$date);}catch(Throwable $e){$okay=false;}
  if($okay!==$allow)throw new RuntimeException('failed '.$name);$passed++;
};
$test('same release',$cron,$files,$candidate,true);
$test('new release',$cron,$files,str_repeat('b',40),false);
$test('paused clock',"# unrelated\n",[],$candidate,true);
$test('duplicate marker',$cron.$cron,$files,$candidate,false);
$test('unowned sender',$line."\n",$files,$candidate,false);
$test('altered cron',str_replace('2>&1','',$cron),$files,$candidate,false);
$test('separated marker',str_replace("V1\n","V1\n# other\n",$cron),$files,$candidate,false);
$f=$files;$f[$input]='broken';$test('malformed config',$cron,$f,$candidate,false);
$f=$files;$f[$dir.'/owner-signing.key']='wrong';$test('wrong signing key',$cron,$f,$candidate,false);
$f=$files;$f[$dir.'/industry-clock-install-receipt.json']=json_encode(['routine_input'=>$dir.'/../windows.json']);$test('unsafe path',$cron,$f,$candidate,false);
$test('missing receipt',$cron,[],$candidate,false);
$test('invalid commit',$cron,$files,'invalid',false);
$test('active catchup',$cron."# FAMTASTIC_OCT7_CATCHUP_20261007\n",$files,$candidate,false,'2026-10-07');
$test('expired catchup',$cron."# FAMTASTIC_OCT7_CATCHUP_20261007\n",$files,$candidate,true);
echo json_encode(['status'=>'passed','checks'=>$passed])."\n";
