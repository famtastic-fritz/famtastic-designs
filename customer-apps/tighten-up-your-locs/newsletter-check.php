<?php
/** Controlled own-mailbox newsletter verification, CLI only. Output stays in its local runner. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root='/home/nineoo/customer-sites/tighten-up-your-locs/private/owner-app';
require $root.'/current/vendor/autoload.php';$app=require $root.'/current/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
$d=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
if(DB::selectOne('SELECT DATABASE() AS name')->name!=='nineoo_locs'||!preg_match('/^[a-f0-9]{32}$/D',$d['nonce']??''))throw new RuntimeException('check_scope_invalid');
$file=$root.'/newsletter-check-'.$d['nonce'].'.json';$email='hello@tightenupyourlocs.com';
$row=DB::table('newsletter_subscribers')->where('email',$email)->first();
if($d['action']==='begin'){
    if($row||is_file($file)||!config('newsletter.enabled')||!config('locs.mail_enabled'))throw new RuntimeException('fixture_preconditions_failed');
    umask(0077);$f=fopen($file,'x');if(!$f)throw new RuntimeException('fixture_receipt_exists');
    fwrite($f,json_encode(['started_at'=>now()->format('Y-m-d H:i:s'),'email'=>$email]));fclose($f);echo json_encode(['status'=>'ready']);exit;
}
if(!is_file($file))throw new RuntimeException('fixture_receipt_required');
$receipt=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
if($row&&($row->created_at<$receipt['started_at']||isset($receipt['subscriber_id'])&&$row->id!==$receipt['subscriber_id']))throw new RuntimeException('fixture_identity_changed');
if($d['action']==='dispatch'){
    if(!$row||($receipt['subscriber_id']??null)!==$row->id)throw new RuntimeException('bound_fixture_required');
    $notice=DB::table('newsletter_outbox')->where('subscriber_id',$row->id)->first();
    if($notice?->status==='sent'){echo json_encode(['status'=>'already_accepted']);exit;}
    if($notice?->status==='sending'){echo json_encode(['status'=>'scheduler_in_progress']);exit;}
    if($notice?->status!=='queued'||DB::table('newsletter_outbox')->where('status','queued')->count()!==1)throw new RuntimeException('fixture_not_the_only_queued_notice');
    echo json_encode(app(App\Services\NewsletterDispatcher::class)->dispatch(1));exit;
}
if($d['action']==='read'){
    if(!$row)throw new RuntimeException('fixture_request_not_saved');
    $notice=DB::table('newsletter_outbox')->where('subscriber_id',$row->id)->first();
    if(!$notice)throw new RuntimeException('fixture_confirmation_not_queued');
    $receipt['subscriber_id']=$row->id;
    $receipt['provider_message_id']=$notice->provider_message_id;$receipt['delivery_status']=$notice->status;
    file_put_contents($file,json_encode($receipt,JSON_PRETTY_PRINT),LOCK_EX);
    echo json_encode(['status'=>$row->status,'outbox_status'=>$notice->status,'provider_message_id'=>$notice->provider_message_id,
        'outbox_count'=>DB::table('newsletter_outbox')->where('subscriber_id',$row->id)->count(),
        'links'=>json_decode(Crypt::decryptString($notice->payload_encrypted),true,512,JSON_THROW_ON_ERROR)]);exit;
}
if($d['action']==='cleanup'){
    if($row){
        if(!isset($receipt['subscriber_id']))throw new RuntimeException('unbound_fixture_requires_review');
        app(App\Services\NewsletterService::class)->locked(function()use($row){
            if(DB::table('newsletter_outbox')->where('subscriber_id',$row->id)->where('status','sending')->exists())throw new RuntimeException('fixture_sender_still_active');
            DB::table('newsletter_outbox')->where('subscriber_id',$row->id)->delete();
            DB::table('newsletter_subscribers')->where('id',$row->id)->delete();
        });
    }
    $receipt['fixture_removed_at']=gmdate('c');file_put_contents($file,json_encode($receipt,JSON_PRETTY_PRINT),LOCK_EX);
    echo json_encode(['status'=>'own_mailbox_fixture_removed','provider_message_id'=>$receipt['provider_message_id']??null]);exit;
}
throw new RuntimeException('check_action_invalid');
