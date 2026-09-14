<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NewsletterDispatcher;
use App\Services\NewsletterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;
    private const ENDPOINT = '/api/newsletter/signup';
    private const ORIGIN = ['Origin' => 'https://tightenupyourlocs.com'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://tightenupyourlocs.com', 'newsletter.enabled' => true, 'locs.mail_enabled' => true]);
    }

    private function payload(): array
    {
        return ['email' => 'reader@example.test', 'consent' => true, 'website' => ''];
    }

    private function queued(): array
    {
        app(NewsletterService::class)->signup('reader@example.test');
        $row = DB::table('newsletter_outbox')->orderByDesc('created_at')->orderByDesc('token_version')->first();
        return [$row, json_decode(Crypt::decryptString($row->payload_encrypted), true, flags: JSON_THROW_ON_ERROR)];
    }

    private function path(string $url): string { return parse_url($url, PHP_URL_PATH); }

    public function test_signup_requires_json_exact_origin_explicit_boolean_consent_and_empty_honeypot(): void
    {
        $this->postJson(self::ENDPOINT, $this->payload())->assertForbidden();
        $this->postJson(self::ENDPOINT, $this->payload(), ['Origin' => 'https://famtasticdesigns.com'])->assertForbidden();
        $this->postJson(self::ENDPOINT, $this->payload(), ['Origin' => 'https://tightenupyourlocs.com.evil.test'])->assertForbidden();
        $this->postJson(self::ENDPOINT, array_replace($this->payload(), ['consent' => false]), self::ORIGIN)->assertUnprocessable();
        $this->postJson(self::ENDPOINT, array_replace($this->payload(), ['consent' => 1]), self::ORIGIN)->assertUnprocessable();
        $this->postJson(self::ENDPOINT, array_replace($this->payload(), ['website' => 'spam']), self::ORIGIN)->assertUnprocessable();
        $this->assertDatabaseCount('newsletter_subscribers', 0);
        $this->assertDatabaseCount('newsletter_outbox', 0);
    }

    public function test_disabled_signup_or_mail_never_claims_queued(): void
    {
        config(['newsletter.enabled' => false]);
        $this->postJson(self::ENDPOINT, $this->payload(), self::ORIGIN)->assertStatus(503);
        config(['newsletter.enabled' => true, 'locs.mail_enabled' => false]);
        $this->postJson(self::ENDPOINT, $this->payload(), self::ORIGIN)->assertStatus(503);
        $this->assertDatabaseCount('newsletter_outbox', 0);
    }

    public function test_bad_email_and_non_json_do_not_create_records(): void
    {
        $this->postJson(self::ENDPOINT, array_replace($this->payload(), ['email' => 'invalid']), self::ORIGIN)->assertUnprocessable();
        $this->post(self::ENDPOINT, $this->payload(), self::ORIGIN)->assertStatus(415);
        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_duplicate_signup_is_normalized_atomic_and_does_not_expose_membership_or_send_mail(): void
    {
        Mail::shouldReceive('send')->never();
        $this->postJson(self::ENDPOINT, $this->payload(), self::ORIGIN)->assertOk()->assertExactJson(NewsletterService::RESPONSE);
        $this->postJson(self::ENDPOINT, array_replace($this->payload(), ['email' => ' Reader@Example.Test ']), self::ORIGIN)
            ->assertOk()->assertExactJson(NewsletterService::RESPONSE);
        $this->assertDatabaseCount('newsletter_subscribers', 1);
        $this->assertDatabaseCount('newsletter_outbox', 1);
        $this->assertDatabaseCount('booking_requests', 0);
        $subscriber = DB::table('newsletter_subscribers')->first();
        $this->assertSame('pending', $subscriber->status);
        $this->assertSame('reader@example.test', $subscriber->email);
        $this->assertSame(64, strlen($subscriber->confirmation_token_hash));
        $outbox = DB::table('newsletter_outbox')->first();
        $this->assertStringNotContainsString('https://', $outbox->payload_encrypted);
        $this->assertSame('locs_newsletter_confirmation', $outbox->template_id);
    }

    public function test_get_is_read_only_post_requires_csrf_and_confirmation_token_is_single_use(): void
    {
        [$row, $payload] = $this->queued();
        $path = $this->path($payload['confirmation_url']);
        $this->get($path)->assertOk()->assertSee('Confirm my subscription')->assertDontSee('reader@example.test')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $row->subscriber_id, 'status' => 'pending']);
        $this->app['env'] = 'local';
        $this->post($path)->assertStatus(419);
        $this->app['env'] = 'testing';
        $this->post($path)->assertOk()->assertSee('You’re on the list.');
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $row->subscriber_id, 'status' => 'subscribed', 'confirmation_token_hash' => null]);
        $this->post($path)->assertNotFound();
        app(NewsletterService::class)->signup('reader@example.test');
        $this->assertDatabaseCount('newsletter_outbox', 1);
    }

    public function test_expired_or_replaced_confirmation_cannot_subscribe(): void
    {
        [$first, $payload] = $this->queued();
        $this->travel(25)->hours();
        $this->get($this->path($payload['confirmation_url']))->assertStatus(410);
        $this->post($this->path($payload['confirmation_url']))->assertStatus(410);
        [, $new] = $this->queued();
        $this->post($this->path($payload['confirmation_url']))->assertNotFound();
        $this->post($this->path($new['confirmation_url']))->assertOk();
        $this->assertDatabaseHas('newsletter_outbox', ['id' => $first->id, 'status' => 'superseded']);
    }

    public function test_unsubscribe_persists_without_expiry_works_when_disabled_and_never_reactivates_on_get(): void
    {
        [$row, $payload] = $this->queued();
        $this->post($this->path($payload['confirmation_url']))->assertOk();
        $unsubscribe = $this->path($payload['unsubscribe_url']);
        $this->travel(400)->days();
        config(['newsletter.enabled' => false, 'locs.mail_enabled' => false]);
        $this->get($unsubscribe)->assertOk()->assertSee('Unsubscribe');
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $row->subscriber_id, 'status' => 'subscribed']);
        $this->app['env'] = 'local';
        $this->post($unsubscribe)->assertStatus(419);
        $this->app['env'] = 'testing';
        $this->post($unsubscribe)->assertOk()->assertSee('You’re off the list.');
        $this->post($unsubscribe)->assertOk();
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $row->subscriber_id, 'status' => 'unsubscribed']);
        $this->post($this->path($payload['confirmation_url']))->assertNotFound();
        config(['newsletter.enabled' => true, 'locs.mail_enabled' => true]);
        [, $fresh] = $this->queued();
        $this->get($this->path($fresh['confirmation_url']))->assertOk();
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $row->subscriber_id, 'status' => 'unsubscribed']);
        $this->assertSame($payload['unsubscribe_url'], $fresh['unsubscribe_url']);
        $this->post($this->path($fresh['confirmation_url']))->assertOk();
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $row->subscriber_id, 'status' => 'subscribed']);
        $this->post($unsubscribe)->assertOk();
    }

    public function test_resend_cooldown_and_daily_cap_prevent_email_bombing(): void
    {
        $this->freezeTime();
        $service = app(NewsletterService::class);
        $service->signup('reader@example.test');
        for ($i = 0; $i < 8; $i++) $service->signup('reader@example.test');
        $this->assertDatabaseCount('newsletter_outbox', 1);
        for ($i = 0; $i < 5; $i++) {
            $this->travel(16)->minutes();
            $this->assertSame(NewsletterService::RESPONSE, $service->signup('reader@example.test'));
        }
        $this->assertDatabaseCount('newsletter_outbox', 3);
        $this->assertSame(1, DB::table('newsletter_outbox')->where('status', 'queued')->count());
        $this->assertSame(2, DB::table('newsletter_outbox')->where('status', 'superseded')->count());
    }

    public function test_owner_projection_requires_verified_owner_and_contains_no_link_secrets(): void
    {
        $this->queued();
        $path = '/admin/api/newsletter/subscribers';
        $this->getJson($path)->assertUnauthorized();
        $owner = User::factory()->create();
        $this->actingAs($owner)->getJson($path)->assertForbidden();
        $owner->forceFill(['is_owner' => true, 'email_verified_at' => null])->save();
        $this->actingAs($owner)->getJson($path)->assertForbidden();
        $owner->forceFill(['email_verified_at' => now()])->save();
        $result = $this->actingAs($owner)->getJson($path)->assertOk()->assertJsonPath('counts.pending', 1)
            ->assertJsonPath('has_more', false)->json();
        $this->assertSame(['id', 'email', 'status', 'consent_requested_at', 'confirmed_at', 'unsubscribed_at'], array_keys($result['subscribers'][0]));
        $this->assertStringNotContainsString('token', json_encode($result));
    }

    private function receipt(): \Illuminate\Mail\SentMessage
    {
        $mail = (new \Symfony\Component\Mime\Email)->from('hello@example.test')->to('reader@example.test')->text('Local fixture');
        return new \Illuminate\Mail\SentMessage(new \Symfony\Component\Mailer\SentMessage($mail, \Symfony\Component\Mailer\Envelope::create($mail)));
    }

    public function test_dispatcher_records_receipt_once_and_disabled_mail_does_not_send(): void
    {
        [$row] = $this->queued();
        $dispatcher = app(NewsletterDispatcher::class);
        config(['locs.mail_enabled' => false]);
        $this->assertSame('disabled', $dispatcher->dispatch()['status']);
        config(['locs.mail_enabled' => true]);
        Mail::shouldReceive('send')->once()->andReturn($this->receipt());
        $this->assertSame(1, $dispatcher->dispatch()['sent']);
        $this->assertSame(0, $dispatcher->dispatch()['sent']);
        $this->assertDatabaseHas('newsletter_outbox', ['id' => $row->id, 'status' => 'sent', 'attempts' => 1]);
        $this->assertNotNull(DB::table('newsletter_outbox')->where('id', $row->id)->value('provider_message_id'));
        // An SMTP receipt alone never subscribes the address.
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $row->subscriber_id, 'status' => 'pending']);
    }

    public function test_ambiguous_send_and_expired_sender_require_explicit_review_not_automatic_retry(): void
    {
        [$row] = $this->queued();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('fixture transport ambiguity'));
        $dispatcher = app(NewsletterDispatcher::class);
        $this->assertSame(1, $dispatcher->dispatch()['uncertain']);
        $this->assertSame(0, $dispatcher->dispatch()['sent']);
        $this->assertTrue($dispatcher->retry($row->id));
        DB::table('newsletter_outbox')->where('id', $row->id)->update(['status' => 'sending', 'leased_until' => now()->subMinutes(3)]);
        $this->assertSame(0, $dispatcher->dispatch()['sent']);
        $this->assertDatabaseHas('newsletter_outbox', ['id' => $row->id, 'status' => 'uncertain', 'last_error' => 'expired_sender_requires_review']);
    }

    public function test_unsubscribed_and_expired_confirmations_are_not_dispatched(): void
    {
        [$row, $payload] = $this->queued();
        $this->post($this->path($payload['unsubscribe_url']))->assertOk();
        Mail::shouldReceive('send')->never();
        $dispatcher = app(NewsletterDispatcher::class);
        $this->assertSame(0, $dispatcher->dispatch()['sent']);
        $this->travel(25)->hours();
        [$fresh] = $this->queued();
        $this->travel(25)->hours();
        $this->assertSame(1, $dispatcher->dispatch()['superseded']);
        $this->assertDatabaseHas('newsletter_outbox', ['id' => $fresh->id, 'status' => 'superseded']);
    }

    public function test_confirmation_mail_has_business_brand_two_local_links_and_plain_text(): void
    {
        [, $payload] = $this->queued();
        $html = view('mail.newsletter-confirmation', $payload)->render();
        $plain = view('mail.newsletter-confirmation-text', $payload)->render();
        $this->assertStringContainsString('TIGHTEN UP YOUR LOCS', $html);
        $this->assertStringContainsString('THE LOCS LETTER', $html);
        $this->assertStringContainsString('The Locs Letter', $plain);
        $this->assertStringContainsString('Confirm my subscription', $html);
        $this->assertStringContainsString($payload['confirmation_url'], $plain);
        $this->assertStringContainsString($payload['unsubscribe_url'], $plain);
        $this->assertStringNotContainsString('famtasticdesigns.com', $html.$plain);
    }

    public function test_failed_queue_insert_rolls_back_subscriber_and_consent(): void
    {
        DB::statement("CREATE TRIGGER reject_newsletter_outbox BEFORE INSERT ON newsletter_outbox BEGIN SELECT RAISE(ABORT, 'fixture rejection'); END");
        try {
            app(NewsletterService::class)->signup('reader@example.test');
            $this->fail('Expected queue insertion to fail.');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertStringContainsString('fixture rejection', $error->getMessage());
        }
        $this->assertDatabaseCount('newsletter_subscribers', 0);
        $this->assertDatabaseCount('newsletter_outbox', 0);
    }

    public function test_public_signup_route_throttles_after_six_requests(): void
    {
        for ($i = 0; $i < 6; $i++) $this->postJson(self::ENDPOINT, $this->payload(), self::ORIGIN)->assertOk();
        $this->postJson(self::ENDPOINT, $this->payload(), self::ORIGIN)->assertStatus(429);
        $this->assertDatabaseCount('newsletter_outbox', 1);
    }

    public function test_owner_newsletter_page_is_protected_read_only_and_escapes_email(): void
    {
        [$row] = $this->queued();
        $path = '/admin/newsletter';
        $this->get($path)->assertRedirect('/admin/login');
        $owner = User::factory()->create();
        $this->actingAs($owner)->get($path)->assertForbidden();
        $owner->forceFill(['is_owner' => true, 'email_verified_at' => now()])->save();
        $email = '<script>alert(1)</script>@example.test';
        DB::table('newsletter_subscribers')->where('id', $row->subscriber_id)->update(['email' => $email]);
        $this->actingAs($owner)->get($path)->assertOk()->assertSee('The Locs Letter.')
            ->assertSee($email)->assertDontSee($email, false)->assertSee('Awaiting confirmation')
            ->assertSee('writing and sending newsletter campaigns is not included yet')
            ->assertDontSee('confirmation_token_hash')->assertDontSee('payload_encrypted')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $row->subscriber_id, 'status' => 'pending']);
        $this->get('/admin')->assertOk()->assertSee('href="/admin/newsletter"', false);
    }

    public function test_owner_newsletter_page_has_honest_empty_and_partial_states(): void
    {
        $owner = User::factory()->create();
        $owner->forceFill(['is_owner' => true, 'email_verified_at' => now()])->save();
        $this->actingAs($owner)->get('/admin/newsletter')->assertOk()->assertSee('No newsletter signups have been saved yet.');
        $snapshot = ['counts' => ['pending' => 101, 'subscribed' => 0, 'unsubscribed' => 0],
            'subscribers' => collect(), 'has_more' => true, 'delivery' => ['queued' => 0, 'uncertain' => 2]];
        $view = view('newsletter.owner', $snapshot)->render();
        $this->assertStringContainsString('Showing the newest 100 signup records.', $view);
        $this->assertStringContainsString('2 confirmation emails need delivery review.', $view);
        $this->assertStringNotContainsString('<script', $view);
    }
}
