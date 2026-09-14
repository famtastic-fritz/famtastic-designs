<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingHttpTest extends TestCase
{
    use RefreshDatabase;
    private const ENDPOINT = '/api/booking-request/site-dffd4cb9c3aa47fd';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://tightenupyourlocs.com', 'locs.owner_email' => 'owner@example.test', 'locs.public_booking_enabled' => true]);
    }

    private function payload(): array
    {
        return ['name' => 'Test Client', 'email' => 'client@example.test', 'phone' => '',
            'service_key' => 'retightening', 'requested_window' => 'Friday', 'message' => '',
            'consent' => 'on', 'website' => '', 'source' => 'tighten-up-your-locs-site',
            'idempotency_key' => (string) Str::uuid()];
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_owner' => true, 'email_verified_at' => now()])->save();
        return $user;
    }

    public function test_public_intake_requires_exact_origin_consent_empty_honeypot_and_enabled_flag(): void
    {
        $payload = $this->payload();
        $this->postJson(self::ENDPOINT, $payload)->assertForbidden();
        $this->postJson(self::ENDPOINT, $payload, ['Origin' => 'https://famtasticdesigns.com'])->assertForbidden();
        $this->postJson(self::ENDPOINT, array_replace($payload, ['consent' => false]), ['Origin' => 'https://tightenupyourlocs.com'])->assertUnprocessable();
        $this->postJson(self::ENDPOINT, array_replace($payload, ['website' => 'https://spam.invalid']), ['Origin' => 'https://tightenupyourlocs.com'])->assertUnprocessable();
        config(['locs.public_booking_enabled' => false]);
        $this->postJson(self::ENDPOINT, $payload, ['Origin' => 'https://tightenupyourlocs.com'])->assertStatus(503);
        $this->assertDatabaseCount('booking_requests', 0);
    }

    public function test_existing_static_form_contract_returns_uuid_receipt_without_agency_dependency(): void
    {
        $payload = $this->payload();
        unset($payload['idempotency_key']);
        $first = $this->postJson(self::ENDPOINT, $payload, ['Origin' => 'https://tightenupyourlocs.com'])
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('status', 'received')->json();
        $this->assertTrue(Str::isUuid($first['reference']));
        $this->postJson(self::ENDPOINT, $payload, ['Origin' => 'https://tightenupyourlocs.com'])
            ->assertOk()->assertExactJson($first);
        $this->assertDatabaseCount('booking_requests', 1);
    }

    public function test_private_api_requires_verified_independent_owner(): void
    {
        $this->getJson('/admin/api/requests')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/admin/api/appointments')->assertForbidden();
        $owner = $this->owner();
        $owner->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($owner)->getJson('/admin/api/openings')->assertForbidden();
        $owner->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($owner)->getJson('/admin/api/requests')->assertOk()->assertJsonPath('requests', [])->assertJsonPath('has_more', false);
    }

    public function test_owner_booking_actions_reject_missing_csrf_when_unit_test_bypass_is_disabled(): void
    {
        $this->actingAs($this->owner());
        $this->app['env'] = 'local';
        $this->postJson('/admin/api/appointments', [])->assertStatus(419);
        $this->postJson('/admin/api/openings', [])->assertStatus(419);
    }

    public function test_owner_command_envelope_and_private_projection_exclude_tokens_and_hashes(): void
    {
        $request = app(BookingService::class)->receive($this->payload())['reference'];
        $this->actingAs($this->owner());
        $data = ['action' => 'propose', 'request_id' => $request, 'expected_version' => 1,
            'starts_at' => time() + 86400, 'ends_at' => time() + 90000, 'idempotency_key' => (string) Str::uuid()];
        $appointment = $this->postJson('/admin/api/appointments', $data)->assertOk()
            ->assertJsonPath('appointment.status', 'proposed')->assertJsonPath('notification_status', 'queued')->json('appointment');
        $requests = $this->getJson('/admin/api/requests')->assertOk()->json('requests');
        $this->assertArrayNotHasKey('idempotency_key', $requests[0]);
        $this->assertArrayNotHasKey('payload_hash', $requests[0]);
        $this->assertStringEndsWith('+00:00', $requests[0]['created_at']);
        $this->assertArrayNotHasKey('response_token_hash', $appointment);
        $this->postJson('/admin/api/appointments', ['action' => 'confirm', 'appointment_id' => $appointment['id'],
            'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])->assertOk()->assertJsonPath('appointment.status', 'confirmed');
        $this->postJson('/admin/api/appointments', ['action' => 'cancel', 'appointment_id' => $appointment['id'],
            'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()])->assertConflict()->assertJsonPath('code', 'stale_version');
    }

    public function test_availability_only_exposes_published_non_private_window_fields(): void
    {
        $service = app(BookingService::class);
        foreach ([false, true] as $published) {
            $service->openingCommand(['action' => 'create', 'label' => 'Friday opening',
                'starts_at' => time() + 86400, 'ends_at' => time() + 90000, 'published' => $published,
                'idempotency_key' => (string) Str::uuid()], 1);
        }
        $data = $this->getJson('/api/booking-availability/'.BookingService::SITE_KEY)->assertOk()->json();
        $this->assertCount(1, $data['windows']);
        $this->assertSame(['label', 'starts_at', 'ends_at'], array_keys($data['windows'][0]));
        $this->getJson('/api/booking-availability/another-site')->assertNotFound();
    }

    public function test_response_get_is_read_only_and_response_post_requires_csrf(): void
    {
        $service = app(BookingService::class);
        $id = $service->receive($this->payload())['reference'];
        $appointment = $service->command(['action' => 'propose', 'request_id' => $id, 'expected_version' => 1,
            'starts_at' => time() + 86400, 'ends_at' => time() + 90000, 'idempotency_key' => (string) Str::uuid()], 1)['appointment'];
        $mail = DB::table('notification_outbox')->where('template', 'appointment_propose')->first();
        $url = parse_url(json_decode($mail->payload, true)['response_url'], PHP_URL_PATH);
        $events = DB::table('booking_events')->count();
        $this->get($url)->assertOk()->assertSee('Accept this time')->assertDontSee('client@example.test')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertDatabaseCount('booking_events', $events);
        $this->assertDatabaseHas('appointments', ['id' => $appointment['id'], 'status' => 'proposed', 'version' => 1]);
        $this->app['env'] = 'local';
        $this->post($url, ['decision' => 'accept'])->assertStatus(419);
    }
}
