<?php
/** Temporary, empty-database HTTP verification owner; CLI only, secrets via stdin. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root='/home/nineoo/customer-sites/tighten-up-your-locs/private/owner-app/current';require $root.'/vendor/autoload.php';$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$d=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
if(config('database.connections.mysql.database')!=='nineoo_locs'||!preg_match('/^[a-f0-9]{32}$/D',$d['nonce']??'')||strlen($d['password']??'')!==64)throw new RuntimeException('probe_scope_invalid');
$email='release-check-'.$d['nonce'].'@example.invalid';
$user=App\Models\User::where('email',$email)->first();
if(($d['action']??'')==='create') {
    if($user||config('locs.public_booking_enabled')||Illuminate\Support\Facades\DB::table('booking_requests')->count()!==0)throw new RuntimeException('probe_requires_empty_closed_application');
    $user=new App\Models\User;$user->forceFill(['name'=>'Release verification (temporary)','email'=>$email,'password'=>Illuminate\Support\Facades\Hash::make($d['password']),'is_owner'=>true,'email_verified_at'=>now()])->save();
    echo "temporary_owner_created\n";
}elseif(($d['action']??'')==='delete') {
    if(!$user){echo "temporary_owner_absent\n";exit;}
    if($user->name!=='Release verification (temporary)'||!Illuminate\Support\Facades\Hash::check($d['password'],$user->password))throw new RuntimeException('probe_cleanup_scope_invalid');
    Illuminate\Support\Facades\DB::transaction(function()use($user,$email){
        $db=Illuminate\Support\Facades\DB::getFacadeRoot();
        $requests=$db->table('booking_requests')->where('email',$email)->where('name','Release verification (temporary)')->pluck('id');
        $appointments=$db->table('appointments')->whereIn('request_id',$requests)->pluck('id');
        $entities=$requests->merge($appointments);$events=$db->table('booking_events')->whereIn('entity_id',$entities)->pluck('id');
        $db->table('notification_outbox')->whereIn('event_id',$events)->delete();
        $db->table('booking_events')->whereIn('id',$events)->delete();
        $db->table('appointments')->whereIn('id',$appointments)->delete();$db->table('booking_requests')->whereIn('id',$requests)->delete();
        $db->table('sessions')->where('user_id',$user->id)->delete();$db->table('password_reset_tokens')->where('email',$email)->delete();$user->delete();
    });echo "temporary_owner_and_sessions_removed\n";
}else throw new RuntimeException('probe_action_invalid');
