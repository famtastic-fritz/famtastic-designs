<?php
/** Authorized, exact-recipient launch messages; one attempt per recipient, never blind retry. */
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$o=getopt('',['recipient:','send']);$who=$o['recipient']??'';
if(!in_array($who,['shay','fritz'],true))throw new RuntimeException('exact_recipient_required');
$recipients=['shay'=>'junyeismom@gmail.com','fritz'=>'fritz.medine@gmail.com'];
$root='/home/nineoo/customer-sites/tighten-up-your-locs/private/owner-app';
require $root.'/current/vendor/autoload.php';$app=require $root.'/current/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('production')||Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS name')->name!=='nineoo_locs'
    ||config('locs.owner_email')!==$recipients['shay']||!config('locs.public_booking_enabled')||!config('locs.mail_enabled')||config('mail.default')!=='smtp')throw new RuntimeException('independent_launch_not_ready');
$path=$root.'/launch-message-'.$who.'-independent-v1.json';
if(!isset($o['send'])){echo json_encode(['status'=>'preview','recipient'=>$recipients[$who],'kind'=>$who==='shay'?'private_password_setup':'independent_admin_instructions','existing_attempt'=>is_file($path)]).PHP_EOL;exit;}
umask(0077);$f=fopen($path,'x');if(!$f)throw new RuntimeException('previous_attempt_requires_review_no_retry');
$receipt=['recipient'=>$recipients[$who],'cc'=>$who==='fritz'?[$recipients['shay']]:[],'status'=>'attempting','started_at'=>gmdate('c')];
fwrite($f,json_encode($receipt));fflush($f);
$write=static function(array $r)use($f):void{rewind($f);ftruncate($f,0);fwrite($f,json_encode($r,JSON_PRETTY_PRINT));fflush($f);};
Illuminate\Support\Facades\Event::listen(Illuminate\Mail\Events\MessageSent::class,function($event)use(&$receipt,$write){
    $receipt['status']='smtp_accepted';$receipt['provider_message_id']=$event->sent->getMessageId();$receipt['accepted_at']=gmdate('c');$write($receipt);
});
try{
    if($who==='shay'){
        $status=Illuminate\Support\Facades\Password::sendResetLink(['email'=>$recipients['shay'],'is_owner'=>true]);
        if($status!==Illuminate\Support\Facades\Password::RESET_LINK_SENT)throw new RuntimeException('setup_link_not_sent');
    }else{
        $html='<h1>Tighten Up Your Locs: independent admin</h1><p>Fritz,</p><p>The earlier instructions sending Shay to the FAMtastic Designs portal are superseded. Tighten Up Your Locs now has its own application, database, accounts, sessions, booking records and email queue.</p><p><a href="https://tightenupyourlocs.com/admin/">Open the Locs admin</a></p><p>Shay has a separate private password-setup email at junyeismom@gmail.com. She chooses a new Locs password there, then signs in at the link above. The setup link expires in one hour; she can request another using “Set up or reset password.” No FAMtastic account password was changed or copied.</p><ol><li>Requests: open a request and confirm an agreed time, or propose an alternative. A request alone does not reserve an appointment.</li><li>Calendar: choose a day to review appointments. Confirmed times stay reserved while a replacement time is proposed, until the customer accepts.</li><li>Openings: save a private draft or publish an opening for visitors to request.</li><li>Notifications: queued means awaiting delivery; sent means accepted by the mail server, not proof of an inbox read. Refresh after a connection issue before repeating an action.</li></ol><p>The calendar uses America/New_York. This release does not include external calendar sync, deposits, payments, SMS, classes or course delivery. Owner access currently belongs to Shay; it has not been extended to other accounts.</p><p>The original request was preserved during migration. The old portal is no longer the Locs booking authority. The website remains <a href="https://tightenupyourlocs.com/">tightenupyourlocs.com</a>.</p>';
        $html=str_replace('<p>Fritz,</p>','<p>Fritz and Shay,</p>',$html);
        Illuminate\Support\Facades\Mail::html($html,fn($message)=>$message->to($recipients['fritz'])->cc($recipients['shay'])->subject('Tighten Up Your Locs — your independent admin is live'));
    }
    if($receipt['status']!=='smtp_accepted')throw new RuntimeException('provider_receipt_missing');
}catch(Throwable $e){if($receipt['status']!=='smtp_accepted'){$receipt['status']='uncertain';$write($receipt);}fclose($f);throw new RuntimeException('launch_message_requires_receipt_review_no_retry');}
fclose($f);echo json_encode($receipt).PHP_EOL;
