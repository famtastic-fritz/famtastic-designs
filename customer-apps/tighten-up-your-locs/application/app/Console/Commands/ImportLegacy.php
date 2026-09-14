<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class ImportLegacy extends Command {
    protected $signature='locs:import-legacy {file} {--apply}';
    protected $description='Preserve exact frozen Locs request UUIDs; never queue duplicate alerts';
    public function handle():int {
        $file=$this->argument('file');if(!is_file($file)||is_link($file)||(fileperms($file)&0777)!==0600)throw new \RuntimeException('private_export_required');
        $bytes=file_get_contents($file);$d=json_decode($bytes,true,512,JSON_THROW_ON_ERROR);
        if(($d['schema']??'')!=='locs.request-migration.v1'||($d['site_key']??'')!==config('locs.site_key')||!is_array($d['records']))throw new \RuntimeException('export_scope_invalid');
        $rows=[];foreach($d['records'] as $r){if(($r['site_key']??'')!==config('locs.site_key')||!Str::isUuid($r['public_id'])||!filter_var($r['email'],FILTER_VALIDATE_EMAIL)||!$r['consent'])throw new \RuntimeException('invalid_legacy_record');$rows[]=['id'=>$r['public_id'],'idempotency_key'=>'legacy:'.$r['public_id'],'payload_hash'=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR)),'name'=>$r['customer_name'],'email'=>$r['email'],'phone'=>$r['phone'],'service_key'=>$r['service_key'],'requested_window'=>$r['requested_window'],'message'=>$r['message'],'status'=>$r['status']==='new'?'received':$r['status'],'version'=>1,'source'=>'legacy-migration','consent_at'=>gmdate('Y-m-d H:i:s',(int)$r['created']),'created_at'=>gmdate('Y-m-d H:i:s',(int)$r['created']),'updated_at'=>gmdate('Y-m-d H:i:s',(int)$r['changed'])];}
        if($this->option('apply')) DB::transaction(function()use($rows){DB::table('booking_resource_locks')->where('id',1)->lockForUpdate()->first();foreach($rows as $r){$existing=DB::table('booking_requests')->where('id',$r['id'])->first();if($existing){if($existing->payload_hash!==$r['payload_hash'])throw new \RuntimeException('migration_collision');continue;}DB::table('booking_requests')->insert($r);}});
        $this->line(json_encode(['status'=>$this->option('apply')?'imported':'validated','count'=>count($rows),'source_sha256'=>hash('sha256',$bytes),'notifications_created'=>0]));return 0;
    }
}
