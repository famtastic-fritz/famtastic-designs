<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Service;

/** Owns one dedicated wake-up; native NY slots decide whether any work is due. */
final class AcquisitionWindowSchedule {
  public const MARKER='# FAMTASTIC_ACQUISITION_WINDOWS_V1';
  public static function line(string $home,string $input): string {
    if(!preg_match('#^/home/[a-zA-Z0-9_-]+$#D',$home)||!str_starts_with($input,$home.'/')||!preg_match('#^/[a-zA-Z0-9_./-]+/acquisition-199/[a-zA-Z0-9_-]{1,80}\.json$#D',$input)||str_contains($input,'/../')||str_contains($input,'/./'))throw new \RuntimeException('acquisition_clock_path_invalid');
    return '* * * * * cd '.$home.'/public_html && /usr/local/bin/php '.$home.'/public_html/vendor/bin/drush.php famtastic:acquisition-window --input='.$input.' >'.$home.'/deploy/famtastic-designs/acquisition-window-last-run.log 2>&1';
  }
  public static function inspect(string $cron,string $home,string $input): bool {
    $lines=explode("\n",rtrim($cron,"\n"));$markers=0;$commands=0;$expected=self::line($home,$input);
    foreach($lines as $n=>$line){if(str_contains($line,'FAMTASTIC_ACQUISITION_WINDOWS')){if($line!==self::MARKER||($lines[$n+1]??'')!==$expected)throw new \RuntimeException('acquisition_clock_altered');$markers++;}
      if(!str_starts_with(ltrim($line),'#')&&preg_match('/famtastic:acquisition-window|acquisition-exact-operator\.php|acquisition-window-contact\.php/',$line)){if($line!==$expected)throw new \RuntimeException('acquisition_clock_unowned');$commands++;}}
    if($markers>1||$commands>1||$markers!==$commands)throw new \RuntimeException('acquisition_clock_ambiguous');return $markers===1;
  }
  public static function install(string $cron,string $home,string $input): string { if(self::inspect($cron,$home,$input))return $cron;return rtrim($cron,"\n")."\n\n".self::MARKER."\n".self::line($home,$input)."\n"; }
  public static function remove(string $cron,string $home,string $input): string {if(!self::inspect($cron,$home,$input))return $cron;return str_replace(self::MARKER."\n".self::line($home,$input)."\n",'', $cron);}
}
