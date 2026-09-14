<?php
/** Exact private config flag changes, backup retained, no other values touched. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$flag=$argv[1]??'';$map=['--enable-mail'=>'LOCS_MAIL_ENABLED','--enable-booking'=>'LOCS_PUBLIC_BOOKING_ENABLED','--disable-booking'=>'LOCS_PUBLIC_BOOKING_ENABLED','--enable-newsletter'=>'NEWSLETTER_ENABLED','--disable-newsletter'=>'NEWSLETTER_ENABLED'];
if(!isset($map[$flag]))throw new RuntimeException('explicit_flag_required');
$root='/home/nineoo/customer-sites/tighten-up-your-locs/private/owner-app';$path=$root.'/.env';
if(!is_file($path)||is_link($path)||(fileperms($path)&0777)!==0600)throw new RuntimeException('private_environment_required');
$old=file_get_contents($path);$key=$map[$flag];$value=str_starts_with($flag,'--disable-')?'false':'true';
$next=preg_replace('/^'.preg_quote($key,'/').'="(?:true|false)"$/m',$key.'="'.$value.'"',$old,-1,$n);
if($n===0&&$key==='NEWSLETTER_ENABLED'&&!preg_match('/^NEWSLETTER_ENABLED\s*=/m',$old)){$next=rtrim($old)."\n".$key.'="'.$value.'"'."\n";$n=1;}
if($n!==1)throw new RuntimeException('flag_not_unique');
if($next===$old){echo "Already configured.\n";exit;}
umask(0077);$stamp=gmdate('YmdHis').'-'.bin2hex(random_bytes(3));copy($path,$root.'/.env-before-'.$stamp);$tmp=$root.'/.env-'.$stamp;file_put_contents($tmp,$next,LOCK_EX);rename($tmp,$path);echo $key.'='.$value."; previous configuration preserved.\n";
