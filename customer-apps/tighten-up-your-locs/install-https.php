<?php
/** Install one previously absent exact-site HTTPS policy, with private rollback evidence. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$o=getopt('',['sha256:','apply']);$hash=$o['sha256']??'';
$root='/home/nineoo/customer-sites/tighten-up-your-locs';$source=$root.'/private/owner-app/site-https.htaccess';$dest=$root.'/public/.htaccess';
if(!preg_match('/^[a-f0-9]{64}$/D',$hash)||!is_file($source)||is_link($source)||hash_file('sha256',$source)!==$hash||is_link($dest))throw new RuntimeException('exact_https_source_required');
if(is_file($dest)){
    if(hash_file('sha256',$dest)===$hash){echo json_encode(['status'=>'already_installed','sha256'=>$hash]).PHP_EOL;exit;}
    throw new RuntimeException('existing_policy_requires_review_no_overwrite');
}
if(!isset($o['apply'])){echo json_encode(['status'=>'checked','existing_policy'=>'absent','sha256'=>$hash,'writes'=>0]).PHP_EOL;exit;}
umask(0077);$backup=$root.'/private/owner-app/https-policy-before.json';$f=fopen($backup,'x');if(!$f)throw new RuntimeException('previous_attempt_requires_inspection');
fwrite($f,json_encode(['existing_policy'=>'absent','new_sha256'=>$hash,'installed_at'=>gmdate('c'),'rollback'=>'Remove only the exact matching public/.htaccess; never remove a changed policy.']));fclose($f);
$tmp=$root.'/public/.htaccess-locs-'.$hash.'.tmp';$f=fopen($tmp,'x');if(!$f)throw new RuntimeException('temp_exists');fwrite($f,file_get_contents($source));fclose($f);chmod($tmp,0644);
if(file_exists($dest)||!rename($tmp,$dest))throw new RuntimeException('https_install_failed');
echo json_encode(['status'=>'installed_pending_https_checks','sha256'=>hash_file('sha256',$dest)]).PHP_EOL;
