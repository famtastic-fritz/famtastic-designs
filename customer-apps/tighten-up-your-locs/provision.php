<?php
/** CLI, exact Locs account only. Default read-only. Never prints credentials. */
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
const ROOT='/home/nineoo/customer-sites/tighten-up-your-locs/private/owner-app';
function demand(bool $ok,string $code):void { if(!$ok) throw new RuntimeException($code); }
function api(string $op,array $params=[]):array {
    demand(in_array($op,['list_databases','list_users','create_database','create_user','set_privileges_on_database'],true),'operation_not_allowed');
    $c=json_decode(file_get_contents('/home/nineoo/customer-sites/tighten-up-your-locs/private/reload.json'),true,512,JSON_THROW_ON_ERROR);
    demand($c['account']==='nineoo'&&$c['hostname']==='p3plzcpnl506112.prod.phx3.secureserver.net','account_mismatch');
    $h=curl_init('https://'.$c['hostname'].':2083/execute/Mysql/'.$op);
    curl_setopt_array($h,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($params),CURLOPT_HTTPHEADER=>['Authorization: cpanel '.$c['account'].':'.$c['token']],CURLOPT_TIMEOUT=>30]);
    $raw=curl_exec($h);$status=curl_getinfo($h,CURLINFO_RESPONSE_CODE);curl_close($h);
    demand($raw!==false&&$status===200,'provider_response_uncertain_reconcile_before_retry');
    $r=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$r=$r['result']??$r;
    demand(($r['status']??0)===1,'provider_operation_failed');
    return $r['data']??[];
}
try {
    $apply=in_array('--apply-exact-locs',$argv,true);
    $dbs=array_column(api('list_databases'),'database');$users=array_column(api('list_users'),'user');
    $db='nineoo_locs';$runtime='nineoo_locsapp';$migration='nineoo_locsmig';
    if(!$apply) { echo json_encode(['database'=>$db,'database_exists'=>in_array($db,$dbs,true),'runtime_user_exists'=>in_array($runtime,$users,true),'migration_user_exists'=>in_array($migration,$users,true),'writes'=>0]).PHP_EOL;exit; }
    umask(0077);
    if(!is_dir(ROOT)) demand(mkdir(ROOT,0700),'private_root_create_failed');
    demand(realpath(ROOT)===ROOT&&!is_link(ROOT)&&(fileperms(ROOT)&0777)===0700,'unsafe_private_root');
    $path=ROOT.'/credentials.json';
    if(!file_exists($path)) {
        demand(!in_array($db,$dbs,true)&&!in_array($runtime,$users,true)&&!in_array($migration,$users,true),'unmanaged_database_collision');
        $c=['database'=>$db,'runtime_user'=>$runtime,'runtime_password'=>bin2hex(random_bytes(32)),'migration_user'=>$migration,'migration_password'=>bin2hex(random_bytes(32)),'app_key'=>'base64:'.base64_encode(random_bytes(32))];
        $f=fopen($path,'x');demand($f!==false,'credential_create_failed');fwrite($f,json_encode($c,JSON_THROW_ON_ERROR));fclose($f);
    }
    demand(!is_link($path)&&(fileperms($path)&0777)===0600,'unsafe_credential_file');
    $c=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    demand($c['database']===$db&&$c['runtime_user']===$runtime&&$c['migration_user']===$migration,'credential_scope_mismatch');
    if(!in_array($db,$dbs,true)) api('create_database',['name'=>$db]);
    foreach([$runtime=>'runtime_password',$migration=>'migration_password'] as $u=>$field) if(!in_array($u,$users,true)) api('create_user',['name'=>$u,'password'=>$c[$field]]);
    api('set_privileges_on_database',['user'=>$runtime,'database'=>$db,'privileges'=>'SELECT,INSERT,UPDATE,DELETE']);
    api('set_privileges_on_database',['user'=>$migration,'database'=>$db,'privileges'=>'ALL PRIVILEGES']);
    $pdo=new PDO('mysql:host=localhost;dbname='.$db,$runtime,$c['runtime_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    demand($pdo->query('SELECT DATABASE()')->fetchColumn()===$db,'database_binding_failed');
    echo json_encode(['status'=>'independent_database_provisioned','database'=>$db,'runtime_user'=>$runtime,'migration_user'=>$migration,'tables'=>(int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn(),'secrets_printed'=>false]).PHP_EOL;
} catch(Throwable $e) { fwrite(STDERR,preg_match('/^[a-z_]+$/',$e->getMessage())?$e->getMessage().PHP_EOL:"provision_failed_reconcile_before_retry\n");exit(1); }
