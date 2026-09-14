<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
class OutboxDispatcher {
    public function dispatch(int $limit=10):array {
        if(!config('locs.mail_enabled')) return ['status'=>'disabled','sent'=>0,'uncertain'=>0];
        if(app()->environment('production')&&config('mail.default')!=='smtp') throw new \RuntimeException('production_smtp_required');
        $counts=['status'=>'processed','sent'=>0,'uncertain'=>0];
        // A crashed sender may have been accepted by SMTP; never blindly resend it.
        DB::table('notification_outbox')->where('status','sending')->where('leased_until','<',now())->update(['status'=>'uncertain','last_error'=>'expired_sender_requires_review','updated_at'=>now()]);
        for($i=0;$i<min($limit,25);$i++) {
            $row=DB::transaction(function(){
                $r=DB::table('notification_outbox')->whereIn('status',['queued','failed'])->where('available_at','<=',now())->orderBy('created_at')->lockForUpdate()->first();
                if(!$r)return null;
                $lease=(string)Str::uuid();
                DB::table('notification_outbox')->where('id',$r->id)->update(['status'=>'sending','lease_token'=>$lease,'leased_until'=>now()->addMinutes(2),'attempts'=>$r->attempts+1,'updated_at'=>now()]);
                $r->lease_token=$lease;return $r;
            });
            if(!$row)break;
            try {
                $sent=DB::transaction(function()use($row){
                    if(DB::table('booking_resource_locks')->where('id',1)->increment('lock_version')!==1)throw new \RuntimeException('booking_authority_missing');
                    DB::table('booking_resource_locks')->where('id',1)->lockForUpdate()->first();
                    $fresh=DB::table('notification_outbox')->where('id',$row->id)->lockForUpdate()->first();
                    if($fresh->status!=='sending'||$fresh->lease_token!==$row->lease_token)return false;
                    $p=json_decode($row->payload,true,512,JSON_THROW_ON_ERROR);
                    if(isset($p['appointment_id'])) {
                        $a=DB::table('appointments')->where('id',$p['appointment_id'])->first();
                        if(!$a||(int)$a->version!==(int)$p['version']) { DB::table('notification_outbox')->where('id',$row->id)->update(['status'=>'superseded','updated_at'=>now()]);return false; }
                    }
                    $messageId=$row->id.'@tightenupyourlocs.com';
                    $sent=Mail::send('mail.booking',['notice'=>$this->copy($row->template,$p)],function($mail)use($row,$messageId){$mail->to($row->recipient)->subject('Tighten Up Your Locs · Appointment update');$mail->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID',$messageId);});
                    if(!$sent)throw new \RuntimeException('no_transport_receipt');
                    $provider=$sent->getMessageId();
                    DB::table('notification_outbox')->where('id',$row->id)->update(['status'=>'sent','sent_at'=>now(),'provider_message_id'=>$provider,'last_error'=>null,'lease_token'=>null,'leased_until'=>null,'updated_at'=>now()]);
                    return true;
                });
                if($sent)$counts['sent']++;
            }catch(\Throwable){DB::table('notification_outbox')->where('id',$row->id)->where('lease_token',$row->lease_token)->update(['status'=>'uncertain','last_error'=>'transport_outcome_requires_review','updated_at'=>now()]);$counts['uncertain']++;}
        }
        return $counts;
    }
    public function retry(string $id):bool {
        if(!Str::isUuid($id))return false;
        return DB::table('notification_outbox')->where('id',$id)->whereIn('status',['failed','uncertain'])->update(['status'=>'queued','available_at'=>now(),'lease_token'=>null,'leased_until'=>null,'updated_at'=>now()])===1;
    }
    private function copy(string $template,array $p):array {
        if($template==='request_received')return ['title'=>'A new appointment request is ready','body'=>'Open your private Locs admin to review the request. A request is not yet a confirmed appointment.','url'=>'https://tightenupyourlocs.com/admin','action'=>'Review request','when'=>null];
        $proposed=isset($p['response_url']);
        $url=$p['response_url']??'https://tightenupyourlocs.com/#booking';
        if(!str_starts_with($url,'https://tightenupyourlocs.com/')&&!app()->environment('testing','local'))throw new \RuntimeException('untrusted_notice_url');
        $start=$proposed?($p['pending_starts_at']??$p['starts_at']):$p['starts_at'];
        $when=(new \DateTimeImmutable('@'.$start))->setTimezone(new \DateTimeZone('America/New_York'))->format('l, F j, Y \a\t g:i a T');
        $status=$p['status'];
        return ['title'=>$proposed?'A time for you to review':match($status){'confirmed'=>'Your appointment is confirmed','cancelled'=>'Your appointment was cancelled','completed'=>'Thank you for your visit',default=>'Your appointment update'},'body'=>$proposed?'Review the proposed time and choose whether to accept it.':match($status){'confirmed'=>'The time below is saved for your appointment.','cancelled'=>'This appointment is no longer reserved.', 'completed'=>'Your visit has been marked complete.',default=>'Contact Shay if you have any questions.'},'url'=>$url,'action'=>$proposed?'Review proposed time':'Visit Tighten Up Your Locs','when'=>$when];
    }
}
