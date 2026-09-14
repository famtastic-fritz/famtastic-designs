<?php
/** Install one exact hashed, Git-sourced standalone Locs package. CLI only. */
declare(strict_types=1);
const SITE='/home/nineoo/customer-sites/tighten-up-your-locs';
function need(bool $ok,string $code):void {if(!$ok)throw new RuntimeException($code);}
function command(array $cmd,string $cwd,?array $env=null,?string $output=null):void {
    $p=proc_open($cmd,[0=>['file','/dev/null','r'],1=>$output?['file',$output,'w']:['pipe','w'],2=>['pipe','w']],$pipes,$cwd,$env);
    need(is_resource($p),'process_failed');
    if(!$output)stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);foreach($pipes as $pipe)fclose($pipe);
    need(proc_close($p)===0,'command_failed_'.basename($cmd[0]));
}
try {
    need(PHP_SAPI==='cli','cli_required');$o=getopt('',['package:','sha256:','commit:','apply']);
    $package=$o['package']??'';$sha=$o['sha256']??'';$commit=$o['commit']??'';
    need(preg_match('/^[a-f0-9]{40}$/D',$commit)===1&&preg_match('/^[a-f0-9]{64}$/D',$sha)===1,'invalid_source_identity');
    need(is_file($package)&&!is_link($package)&&hash_file('sha256',$package)===$sha,'package_hash_mismatch');
    $root=SITE.'/private/owner-app';need(realpath($root)===$root&&(fileperms($root)&0777)===0700,'private_root_invalid');
    $archive=new PharData($package);
    foreach(new RecursiveIteratorIterator($archive) as $file) { $name=str_replace('phar://'.$package.'/','',$file->getPathname());need(!str_contains($name,'..')&&!str_starts_with($name,'/')&&!$file->isLink(),'unsafe_archive_entry');need(str_starts_with($name,'application/')||str_starts_with($name,'gateway/'),'unexpected_archive_entry');need(!preg_match('~(^|/)(\.env|database\.sqlite|\.git)(/|$)~',$name),'private_archive_entry'); }
    if(!isset($o['apply'])) {echo json_encode(['status'=>'checked','commit'=>$commit,'package_sha256'=>$sha,'target'=>$root,'writes'=>0]).PHP_EOL;exit;}
    umask(0077);$lock=fopen($root.'/deploy.lock','c');need(flock($lock,LOCK_EX|LOCK_NB),'deploy_busy');
    $release=$root.'/releases/'.$commit;need(!file_exists($release),'release_exists_inspect_before_retry');mkdir($release,0700,true);$archive->extractTo($release,null,false);
    need(is_file($release.'/application/vendor/autoload.php')&&is_file($root.'/.env'),'runtime_missing');
    // All code/config/data lives outside webroot. Only explicit gateways/assets are public.
    symlink($root.'/.env',$release.'/application/.env');
    $app=$release.'/application';
    // Framework storage is empty in the package; use per-install private shared storage.
    rename($app.'/storage',$app.'/storage-skeleton');symlink($root.'/storage',$app.'/storage');
    $c=json_decode(file_get_contents($root.'/credentials.json'),true,512,JSON_THROW_ON_ERROR);
    need($c['database']==='nineoo_locs'&&$c['runtime_user']==='nineoo_locsapp'&&$c['migration_user']==='nineoo_locsmig','database_scope_mismatch');
    $backup=$root.'/backups/'.$commit;mkdir($backup,0700,true);
    $my=$backup.'/migration-client.cnf';file_put_contents($my,"[client]\nuser=".$c['migration_user']."\npassword=".$c['migration_password']."\nhost=localhost\n");
    command(['/usr/bin/mysqldump','--defaults-extra-file='.$my,'--single-transaction','--no-tablespaces',$c['database']],$app,null,$backup.'/before.sql');
    command([PHP_BINARY,'artisan','migrate','--force','--no-interaction'],$app,array_merge(getenv(),['DB_USERNAME'=>$c['migration_user'],'DB_PASSWORD'=>$c['migration_password']]));
    command([PHP_BINARY,'artisan','locs:provision-owner','--apply','--no-interaction'],$app);
    $previous=is_link($root.'/current')?readlink($root.'/current'):null;
    need(!file_exists($root.'/current')||is_link($root.'/current'),'current_not_managed_symlink');
    symlink($app,$root.'/next-'.$commit);rename($root.'/next-'.$commit,$root.'/current');
    $files=[];foreach(['admin','api','appointment'] as $route) foreach(['index.php','.htaccess'] as $file) $files[$route.'/'.$file]=$release.'/gateway/'.$file;
    foreach(['admin.css','admin.js'] as $asset) $files['admin-assets/'.$asset]=$app.'/public/admin-assets/'.$asset;
    foreach($files as $relative=>$source) {
        need(is_file($source),'public_source_missing');$dest=SITE.'/public/'.$relative;$dir=dirname($dest);
        need(!is_link($dest)&&!is_link($dir),'public_target_symlink');
        if(!is_dir($dir)){mkdir($dir,0755,true);chmod($dir,0755);}
        if(is_file($dest)){ $b=$backup.'/public/'.$relative;if(!is_dir(dirname($b)))mkdir(dirname($b),0700,true);copy($dest,$b); }
        $tmp=$dest.'.'.$commit.'.tmp';need(!file_exists($tmp),'temp_collision');copy($source,$tmp);chmod($tmp,0644);rename($tmp,$dest);$files[$relative]=hash_file('sha256',$dest);
    }
    $receipt=['status'=>'installed_pending_live_acceptance','source_commit'=>$commit,'package_sha256'=>$sha,'previous_application'=>$previous,'application'=>$app,'database'=>$c['database'],'public_files'=>$files,'backup'=>$backup,'installed_at'=>gmdate('c')];
    file_put_contents($root.'/receipt-'.$commit.'.json',json_encode($receipt,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));echo json_encode($receipt,JSON_UNESCAPED_SLASHES).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,preg_match('/^[a-z_]+$/',$e->getMessage())?$e->getMessage().PHP_EOL:"installation_failed_inspect_receipt_and_state\n");exit(1);}
