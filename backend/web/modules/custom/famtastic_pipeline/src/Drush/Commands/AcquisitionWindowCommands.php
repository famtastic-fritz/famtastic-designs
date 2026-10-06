<?php
declare(strict_types=1);
namespace Drupal\famtastic_pipeline\Drush\Commands;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drupal\Core\Site\Settings;
use Drupal\famtastic_pipeline\Service\AcquisitionWindowSchedule;

/** Dedicated owner-authorized campaign clock, never general Drupal cron. */
final class AcquisitionWindowCommands extends DrushCommands {
  private function signingKey(): void {
    $private=realpath((string)Settings::get('file_private_path',''));$dir=$private?realpath($private.'/acquisition-199'):FALSE;$key=$dir.'/owner-signing.key';
    if(!$dir||$dir!==$private.'/acquisition-199'||is_link($private.'/acquisition-199')||is_link($key)||!is_file($key)||(fileperms($dir)&0077)!==0||(fileperms($key)&0777)!==0600)throw new \RuntimeException('acquisition_clock_private_key_required');
    $secret=trim((string)file_get_contents($key));if(strlen($secret)<32)throw new \RuntimeException('acquisition_clock_private_key_required');putenv('FAMTASTIC_ACQUISITION_OWNER_SIGNING_SECRET='.$secret);
  }
  #[CLI\Command(name:'famtastic:acquisition-window')]
  #[CLI\Option(name:'input',description:'Signed 0600 campaign schedule config in file_private_path/acquisition-199.')]
  #[CLI\Option(name:'check-config',description:'Validate exact source, private queue and fresh capacity without reserving, preparing or sending.')]
  #[CLI\Option(name:'asap',description:'Execute a signed campaign-5 initial or date/hour industry exception, capped at 50 and one hour.')]
  public function tick(array $options=['input'=>'','check-config'=>FALSE,'asap'=>FALSE]): int {
    try {$this->signingKey();$result=\Drupal::service('famtastic_pipeline.acquisition_window_executor')->run((string)$options['input'],(bool)$options['check-config'],(bool)$options['asap']);$this->io()->writeln(json_encode($result,JSON_THROW_ON_ERROR));return in_array($result['status'],['halted','failed_closed'],TRUE)?self::EXIT_FAILURE:self::EXIT_SUCCESS;}
    catch(\Throwable $e){$this->io()->writeln(json_encode(['status'=>'failed_closed','error_code'=>preg_match('/^[a-z][a-z0-9_]{1,99}$/D',$e->getMessage())?$e->getMessage():'acquisition_private_failure_review','inbox_delivery_proved'=>FALSE],JSON_THROW_ON_ERROR));return self::EXIT_FAILURE;}
  }
  #[CLI\Command(name:'famtastic:acquisition-window-schedule')]
  #[CLI\Option(name:'input',description:'Exact signed private schedule config path.')]
  #[CLI\Option(name:'install',description:'Install only this marker-owned dedicated clock after check-config passes.')]
  #[CLI\Option(name:'confirm',description:'Repeat FAMTASTIC_ACQUISITION_WINDOWS_V1.')]
  public function schedule(array $options=['input'=>'','install'=>FALSE,'confirm'=>'']): int {
    $home=(string)getenv('HOME');$input=(string)$options['input'];$this->signingKey();
    $validation=\Drupal::service('famtastic_pipeline.acquisition_window_executor')->run($input,TRUE);
    $read=static function():string{$lines=[];$code=0;exec('crontab -l 2>/dev/null',$lines,$code);if($code!==0)throw new \RuntimeException('acquisition_crontab_unreadable');return implode("\n",$lines)."\n";};
    $before=$read();$present=AcquisitionWindowSchedule::inspect($before,$home,$input);
    if(!$options['install']){$this->io()->writeln(json_encode(['status'=>'checked','clock_installed'=>$present,'config_hash'=>$validation['config_hash'],'timer'=>'dedicated_acquisition_only','broad_dispatch'=>'not_invoked'],JSON_THROW_ON_ERROR));return self::EXIT_SUCCESS;}
    if($options['confirm']!=='FAMTASTIC_ACQUISITION_WINDOWS_V1')throw new \RuntimeException('acquisition_clock_confirmation_required');if($present)return self::EXIT_SUCCESS;
    $next=AcquisitionWindowSchedule::install($before,$home,$input);$folder=$home.'/deploy/famtastic-designs/cron-backups';
    if(!is_dir($folder)&&!mkdir($folder,0700,TRUE))throw new \RuntimeException('acquisition_clock_backup_required');
    $base=$folder.'/acquisition-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4));$old=umask(0077);
    try{if(file_put_contents($base.'.before',$before)===FALSE||file_put_contents($base.'.next',$next)===FALSE)throw new \RuntimeException('acquisition_clock_backup_failed');}finally{umask($old);}
    if(!hash_equals($before,$read()))throw new \RuntimeException('acquisition_crontab_changed');$out=[];$code=0;exec('crontab '.escapeshellarg($base.'.next'),$out,$code);
    if($code!==0||!hash_equals($next,$read()))throw new \RuntimeException('acquisition_clock_install_failed');$this->io()->writeln(json_encode(['status'=>'installed','clock_installed'=>TRUE,'broad_dispatch'=>'not_invoked'],JSON_THROW_ON_ERROR));return self::EXIT_SUCCESS;
  }
}
