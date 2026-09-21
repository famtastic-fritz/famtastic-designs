<?php
declare(strict_types=1);
// Exact new static route only. No Drupal, shared frontend, customer data or mail.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
umask(0077);
const SHOWCASE_ROOT = '/home/xrdj7j99xhzt/public_html/work/anderlye';
const SHOWCASE_BASE = '/home/xrdj7j99xhzt/deploy/anderlye-showcase';
const SHOWCASE_FILES = ['assets/hero-steel-mobile.webp','assets/hero-steel.webp','index.html','style.css'];
function need(bool $ok,string $message):void { if (!$ok) throw new RuntimeException($message); }
function saveRecord(string $path,array $value):void {
    $temporary=$path.'.tmp';
    need(!is_link($path)&&!is_link($temporary),'Record path is a symlink');
    need(file_put_contents($temporary,json_encode($value,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n",LOCK_EX)!==false,'Cannot save record');
    need(chmod($temporary,0600)&&rename($temporary,$path),'Cannot promote record');
}
function verifyFiles(string $root,array $files):void {
    need(is_dir($root)&&!is_link($root),'Payload directory is missing or linked');
    $found=[];
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
    foreach($iterator as $entry){need(!$entry->isLink()&&$entry->isFile(),'Non-file payload entry');$relative=substr($entry->getPathname(),strlen($root)+1);$found[]=$relative;need(isset($files[$relative])&&hash_equals($files[$relative],hash_file('sha256',$entry->getPathname())),'Payload bytes differ');}
    sort($found);need($found===SHOWCASE_FILES,'Payload contains unexpected or missing files');
}
function moveWithoutOverwrite(string $from,string $to):void {
    need(!file_exists($to)&&!is_link($to),'Destination already exists');
    $process=proc_open(['/bin/mv','-T','--no-clobber','--',$from,$to],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    need(is_resource($process),'Cannot start scoped promotion');fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);
    need(proc_close($process)===0&&!file_exists($from)&&is_dir($to),'Promotion refused or failed; inspect private stage');
}
function existingAssetsDigest():string {
    $root='/home/xrdj7j99xhzt/public_html/assets';
    need(is_dir($root)&&!is_link($root),'Existing asset root differs');$entries=[];
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $entry){
        need(!$entry->isLink()&&$entry->isFile(),'Existing assets contain an unexpected entry');
        $entries[substr($entry->getPathname(),strlen($root)+1)]=hash_file('sha256',$entry->getPathname());
    }
    ksort($entries);return hash('sha256',json_encode($entries,JSON_THROW_ON_ERROR));
}
$mode=$argv[1]??'';$sha=$argv[2]??'';$encoded=$argv[3]??'';
need(in_array($mode,['preflight','prepare','activate','finalize','rollback'],true),'Unknown mode');
need((bool)preg_match('/^[a-f0-9]{40}$/D',$sha),'Expected exact source SHA');
$manifest=json_decode((string)base64_decode($encoded,true),true,512,JSON_THROW_ON_ERROR);
need(($manifest['source_sha']??'')===$sha&&($manifest['host']??'')==='famtasticdesigns.com'&&($manifest['route']??'')==='/work/anderlye/','Manifest binding differs');
$files=$manifest['files']??[];need(array_keys($files)===SHOWCASE_FILES,'Manifest allowlist differs');
foreach($files as $digest)need(is_string($digest)&&(bool)preg_match('/^[a-f0-9]{64}$/D',$digest),'Invalid digest');
need(realpath(dirname(SHOWCASE_ROOT))===dirname(SHOWCASE_ROOT),'Public parent is absent or linked');
need(is_executable('/bin/mv')&&function_exists('proc_open'),'Atomic no-overwrite primitive unavailable');
$stage=SHOWCASE_BASE.'/'.$sha;
if($mode==='preflight'){
    echo json_encode(['mode'=>$mode,'target'=>SHOWCASE_ROOT,'target_absent'=>!file_exists(SHOWCASE_ROOT)&&!is_link(SHOWCASE_ROOT),'stage_absent'=>!file_exists($stage)&&!is_link($stage),'public_parent_writable'=>is_writable(dirname(SHOWCASE_ROOT)),'frontend_release_sha256'=>hash_file('sha256','/home/xrdj7j99xhzt/public_html/.frontend-release'),'existing_assets_sha256'=>existingAssetsDigest(),'provider_mutated'=>false],JSON_THROW_ON_ERROR)."\n";exit;
}
if(!is_dir(SHOWCASE_BASE))need(mkdir(SHOWCASE_BASE,0700),'Cannot create private base');
need(realpath(SHOWCASE_BASE)===SHOWCASE_BASE&&(fileperms(SHOWCASE_BASE)&0077)===0,'Private base ownership/perms differ');
$lock=fopen(SHOWCASE_BASE.'/release.lock','c');need($lock!==false&&flock($lock,LOCK_EX),'Release lock unavailable');
if($mode==='prepare'){
    need(!file_exists(SHOWCASE_ROOT)&&!is_link(SHOWCASE_ROOT),'Public route exists; never overwrite');
    need(!file_exists($stage)&&!is_link($stage),'Private stage exists; inspect before retry');
    need(mkdir($stage,0700)&&mkdir($stage.'/payload',0700)&&mkdir($stage.'/payload/assets',0700),'Cannot create stage');
    saveRecord($stage.'/manifest.json',$manifest);
    echo json_encode(['mode'=>$mode,'stage'=>$stage,'source_sha'=>$sha],JSON_THROW_ON_ERROR)."\n";exit;
}
need(is_dir($stage)&&realpath($stage)===$stage,'Private stage missing or linked');
$saved=json_decode((string)file_get_contents($stage.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);need($saved===$manifest,'Staged manifest differs');
$receiptPath=$stage.'/receipt.json';
if($mode==='activate'){
    need(!file_exists($receiptPath),'Promotion already attempted; inspect receipt');
    need(!file_exists(SHOWCASE_ROOT)&&!is_link(SHOWCASE_ROOT),'Public route exists; never overwrite');
    verifyFiles($stage.'/payload',$files);
    foreach(SHOWCASE_FILES as $file)need(chmod($stage.'/payload/'.$file,0644),'Cannot set public file mode');
    need(chmod($stage.'/payload',0755)&&chmod($stage.'/payload/assets',0755),'Cannot set public directory mode');
    $receipt=['schema'=>'famtastic.anderlye-showcase-release.v1','source_sha'=>$sha,'public_root'=>SHOWCASE_ROOT,'files'=>$files,'status'=>'promotion_prepared','prepared_at'=>gmdate('c')];
    saveRecord($receiptPath,$receipt);moveWithoutOverwrite($stage.'/payload',SHOWCASE_ROOT);
    $receipt['status']='promoted';$receipt['promoted_at']=gmdate('c');saveRecord($receiptPath,$receipt);
}else{
    $receipt=json_decode((string)file_get_contents($receiptPath),true,512,JSON_THROW_ON_ERROR);
    need(($receipt['source_sha']??'')===$sha&&($receipt['status']??'')==='promoted','Only the pending exact promotion may finalize or roll back');
    verifyFiles(SHOWCASE_ROOT,$files);
    if($mode==='rollback'){moveWithoutOverwrite(SHOWCASE_ROOT,$stage.'/failed-public');$receipt['status']='rolled_back';$receipt['rolled_back_at']=gmdate('c');}
    else{$receipt['status']='published_verified';$receipt['verified_at']=gmdate('c');}
    saveRecord($receiptPath,$receipt);
}
echo json_encode($receipt,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
