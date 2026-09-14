<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class BackupDatabase extends Command {
    protected $signature='locs:backup';protected $description='Create a private dedicated-database backup without agency services';
    public function handle():int {
        if(!app()->environment('production')||config('database.default')!=='mysql'||config('database.connections.mysql.database')!=='nineoo_locs')throw new \RuntimeException('production_locs_scope_required');
        umask(0077);$root='/home/nineoo/customer-sites/tighten-up-your-locs/private/owner-app';$dir=$root.'/database-backups';if(!is_dir($dir))mkdir($dir,0700);
        $id=gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));$file=$dir.'/'.$id.'.sql';$cnf=$dir.'/'.$id.'.cnf';
        $db=config('database.connections.mysql');$secret="[client]\nuser=".$db['username']."\npassword=".$db['password']."\nhost=localhost\n";file_put_contents($cnf,$secret);
        try {$p=proc_open(['/usr/bin/mysqldump','--defaults-extra-file='.$cnf,'--single-transaction','--no-tablespaces','nineoo_locs'],[0=>['file','/dev/null','r'],1=>['file',$file,'w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new \RuntimeException('backup_process_failed');stream_get_contents($pipes[2]);fclose($pipes[2]);if(proc_close($p)!==0)throw new \RuntimeException('backup_failed');$this->line(json_encode(['backup'=>$file,'sha256'=>hash_file('sha256',$file),'bytes'=>filesize($file)]));}
        finally {if(is_file($cnf))unlink($cnf);}
        return 0;
    }
}
