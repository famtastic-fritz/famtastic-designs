<?php
namespace Tests\Feature;
use App\Services\BookingService;
use App\Services\OutboxDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;
class OutboxTest extends TestCase {
    use RefreshDatabase;
    protected function setUp():void {parent::setUp();config(['locs.owner_email'=>'owner@example.test','locs.mail_enabled'=>true]);}
    private function queued():object {app(BookingService::class)->receive(['name'=>'QA','email'=>'client@example.test','service_key'=>'question','requested_window'=>'Friday','idempotency_key'=>(string)Str::uuid()]);return DB::table('notification_outbox')->first();}
    private function receipt():\Illuminate\Mail\SentMessage {$m=(new \Symfony\Component\Mime\Email)->from('hello@example.test')->to('owner@example.test')->text('Local fixture');return new \Illuminate\Mail\SentMessage(new \Symfony\Component\Mailer\SentMessage($m,\Symfony\Component\Mailer\Envelope::create($m)));}
    public function test_disabled_mail_does_not_dispatch():void {$row=$this->queued();config(['locs.mail_enabled'=>false]);Mail::shouldReceive('send')->never();$this->assertSame('disabled',app(OutboxDispatcher::class)->dispatch()['status']);$this->assertDatabaseHas('notification_outbox',['id'=>$row->id,'status'=>'queued']);}
    public function test_success_records_transport_receipt_and_does_not_resend():void {$row=$this->queued();Mail::shouldReceive('send')->once()->andReturn($this->receipt());$d=app(OutboxDispatcher::class);$this->assertSame(1,$d->dispatch()['sent']);$this->assertSame(0,$d->dispatch()['sent']);$this->assertNotNull(DB::table('notification_outbox')->where('id',$row->id)->value('provider_message_id'));}
    public function test_uncertain_transport_keeps_booking_and_requires_explicit_retry():void {$row=$this->queued();Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('fixture failure before provider'));$d=app(OutboxDispatcher::class);$this->assertSame(1,$d->dispatch()['uncertain']);$this->assertSame(0,$d->dispatch()['sent']);$this->assertDatabaseCount('booking_requests',1);$this->assertDatabaseHas('notification_outbox',['id'=>$row->id,'status'=>'uncertain']);$this->assertTrue($d->retry($row->id));$this->assertDatabaseHas('notification_outbox',['id'=>$row->id,'status'=>'queued']);}
    public function test_expired_sender_is_not_blindly_retried():void {$row=$this->queued();DB::table('notification_outbox')->where('id',$row->id)->update(['status'=>'sending','leased_until'=>now()->subMinutes(3)]);Mail::shouldReceive('send')->never();app(OutboxDispatcher::class)->dispatch();$this->assertDatabaseHas('notification_outbox',['id'=>$row->id,'status'=>'uncertain']);}
}
