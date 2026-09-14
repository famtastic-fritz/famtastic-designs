<?php
/** Exact Locs scheduler installation/legacy retirement; read-only by default. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
function run(array $args, ?string $input = null): array {
    $p = proc_open($args, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($p)) throw new RuntimeException('process_unavailable');
    if ($input !== null) fwrite($pipes[0], $input);
    fclose($pipes[0]); $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); return [proc_close($p), $out, $err];
}
$options=getopt('', ['target:', 'apply']); $target=$options['target']??'';
if (!in_array($target,['independent','legacy'],true)) throw new RuntimeException('exact_target_required');
$own='/home/nineoo/customer-sites/tighten-up-your-locs/private/owner-app';
$legacy='/home/xrdj7j99xhzt/deploy/famtastic-designs';
$root=$target==='independent'?$own:$legacy;
if (realpath($root)!==$root) throw new RuntimeException('host_scope_invalid');
[$code,$before,$err]=run(['crontab','-l']);
if ($code!==0 && !($code===1 && str_contains(strtolower($err),'no crontab'))) throw new RuntimeException('cannot_read_crontab');
$marker=$target==='independent'?'# LOCS_INDEPENDENT_SCHEDULER_V1':'# FAMTASTIC_LOCS_BOOKING_CRON_V1';
$entry=$target==='independent'
    ? '* * * * * cd '.$own.'/current && '.PHP_BINARY.' artisan schedule:run >> '.$own.'/scheduler.log 2>&1'
    : '*/5 * * * * cd /home/xrdj7j99xhzt/public_html && /usr/local/bin/php /home/xrdj7j99xhzt/public_html/vendor/drush/drush/drush.php php:script /home/xrdj7j99xhzt/deploy/famtastic-designs/workers/locs-booking-worker.php >> /home/xrdj7j99xhzt/deploy/famtastic-designs/logs/locs-booking-worker.log 2>&1';
$lines=preg_split('/\r?\n/',rtrim($before,"\r\n")); if($lines===[''])$lines=[];
$found=array_keys($lines,$marker,true); if(count($found)>1)throw new RuntimeException('duplicate_managed_marker');
if($found && ($lines[$found[0]+1]??null)!==$entry)throw new RuntimeException('managed_entry_changed_review_required');
foreach($lines as $i=>$line)if(str_contains($line,$target==='independent'?$own.'/current':$legacy.'/workers/locs-booking-worker.php') && (!$found||$i!==$found[0]+1))throw new RuntimeException('unmanaged_locs_scheduler_requires_review');
$afterLines=$lines;
if($target==='independent'&&!$found){$afterLines[]=$marker;$afterLines[]=$entry;}
if($target==='legacy'&&$found)array_splice($afterLines,$found[0],2);
$after=implode("\n",$afterLines).($afterLines?"\n":'');
$changed=$after!==$before;
if(isset($options['apply'])&&$changed){
    if($target==='independent'){
        require $own.'/current/vendor/autoload.php';$app=require $own.'/current/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        if(!config('locs.public_booking_enabled')||!config('locs.mail_enabled')||Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name')->name!=='nineoo_locs')throw new RuntimeException('independent_cutover_not_enabled');
    }else{
        [$checkCode,$check]=run(['/usr/local/bin/php','/home/xrdj7j99xhzt/public_html/vendor/drush/drush/drush.php','--root=/home/xrdj7j99xhzt/public_html','php:script',$legacy.'/locs-independent-legacy-export.php']);
        $data=json_decode(trim($check),true);
        if($checkCode!==0||($data['binding_status']??'')!=='retired'||($data['managed_guards_present']??0)!==12)throw new RuntimeException('legacy_freeze_not_verified');
    }
    umask(0077);$backup=$root.'/crontab-before-'.$target.'-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(4)).'.txt';
    $f=fopen($backup,'x');if(!$f)throw new RuntimeException('backup_failed');fwrite($f,$before);fclose($f);
    if(run(['crontab','-'],$after)[0]!==0)throw new RuntimeException('crontab_install_failed');
    if(run(['crontab','-l'])[1]!==$after)throw new RuntimeException('crontab_verification_failed');
}
echo json_encode(['target'=>$target,'status'=>isset($options['apply'])?'verified':'inspected','change_needed'=>$changed,
    'managed_entry_present_before'=>(bool)$found,'managed_entry_present_after'=>$target==='independent',
    'unrelated_entries_preserved'=>true,'before_sha256'=>hash('sha256',$before),'after_sha256'=>hash('sha256',$after)]).PHP_EOL;
