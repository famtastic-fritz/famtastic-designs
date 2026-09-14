<?php

namespace Tests\Feature;

use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BookingDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['locs.owner_email' => 'owner@example.test', 'app.url' => 'https://tightenupyourlocs.com']);
    }

    private function service(): BookingService
    {
        return app(BookingService::class);
    }

    private function receive(string $name = 'Test Client'): string
    {
        return $this->service()->receive(['name' => $name, 'email' => 'client@example.test',
            'service_key' => 'retightening', 'requested_window' => 'Friday afternoon',
            'idempotency_key' => (string) Str::uuid()])['reference'];
    }

    private function command(string $request, string $action = 'confirm', ?int $start = null): array
    {
        $start ??= time() + 86400;
        return ['action' => $action, 'request_id' => $request, 'expected_version' => 1,
            'starts_at' => $start, 'ends_at' => $start + 3600, 'idempotency_key' => (string) Str::uuid()];
    }

    private function fails(string $message, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected booking conflict: '.$message);
        } catch (HttpException $error) {
            $this->assertSame($message, $error->getMessage());
        }
    }

    private function token(string $id): string
    {
        $events = DB::table('booking_events')->where('entity_id', $id)->pluck('id');
        $mail = DB::table('notification_outbox')->whereIn('event_id', $events)->where('status', 'queued')->first();
        return basename(json_decode($mail->payload, true)['response_url']);
    }

    public function test_request_receipt_requires_a_durable_request_event_and_outbox(): void
    {
        $id = $this->receive();
        $this->assertTrue(Str::isUuid($id));
        $this->assertDatabaseHas('booking_requests', ['id' => $id, 'status' => 'received', 'version' => 1]);
        $this->assertDatabaseHas('booking_events', ['entity_id' => $id, 'action' => 'request_received']);
        $this->assertDatabaseHas('notification_outbox', ['recipient' => 'owner@example.test', 'status' => 'queued']);
    }

    public function test_request_and_command_replays_do_not_duplicate_records_or_notifications(): void
    {
        $payload = ['name' => 'Test Client', 'email' => 'client@example.test', 'service_key' => 'question',
            'requested_window' => 'Friday', 'idempotency_key' => (string) Str::uuid()];
        $one = $this->service()->receive($payload);
        $this->assertSame($one, $this->service()->receive($payload));
        $this->fails('idempotency_conflict', fn () => $this->service()->receive(array_replace($payload, ['name' => 'Changed'])));
        $data = $this->command($one['reference']);
        $first = $this->service()->command($data, 1);
        $this->assertSame($first, $this->service()->command($data, 1));
        $this->fails('idempotency_conflict', fn () => $this->service()->command(array_replace($data, ['ends_at' => $data['ends_at'] + 1]), 1));
        $this->assertDatabaseCount('booking_requests', 1);
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('notification_outbox', 2);
        $this->assertDatabaseCount('booking_events', 2);
    }

    public function test_conflicting_confirmation_rolls_back_every_write(): void
    {
        $start = time() + 86400;
        $this->service()->command($this->command($this->receive('First'), start: $start), 1);
        $second = $this->receive('Second');
        $events = DB::table('booking_events')->count();
        $mail = DB::table('notification_outbox')->count();
        $this->fails('time_conflict', fn () => $this->service()->command($this->command($second, start: $start + 120), 1));
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('booking_events', $events);
        $this->assertDatabaseCount('notification_outbox', $mail);
        $this->assertDatabaseHas('booking_requests', ['id' => $second, 'status' => 'received', 'version' => 1]);
        $adjacent = $this->service()->command($this->command($second, start: $start + 3600), 1);
        $this->assertSame('confirmed', $adjacent['appointment']['status']);
    }

    public function test_queue_failure_rolls_back_new_appointment_and_audit_event(): void
    {
        $id = $this->receive();
        DB::statement("CREATE TRIGGER reject_booking_mail BEFORE INSERT ON notification_outbox BEGIN SELECT RAISE(ABORT, 'simulated outbox write failure'); END");
        try {
            $this->service()->command($this->command($id), 1);
            $this->fail('Expected simulated queue failure');
        } catch (\Illuminate\Database\QueryException $error) {
            $this->assertStringContainsString('simulated outbox write failure', $error->getMessage());
        }
        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('booking_events', 1);
        $this->assertDatabaseHas('booking_requests', ['id' => $id, 'status' => 'received', 'version' => 1]);
    }

    public function test_stale_version_cannot_cancel_a_newer_appointment(): void
    {
        $a = $this->service()->command($this->command($this->receive()), 1)['appointment'];
        $change = ['action' => 'reschedule', 'appointment_id' => $a['id'], 'expected_version' => 1,
            'starts_at' => $a['starts_at'] + 7200, 'ends_at' => $a['ends_at'] + 7200, 'idempotency_key' => (string) Str::uuid()];
        $this->service()->command($change, 1);
        $this->fails('stale_version', fn () => $this->service()->command([
            'action' => 'cancel', 'appointment_id' => $a['id'], 'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()], 1));
        $this->assertDatabaseHas('appointments', ['id' => $a['id'], 'status' => 'confirmed', 'version' => 2]);
    }

    public function test_proposal_does_not_reserve_time_and_acceptance_rechecks_conflicts(): void
    {
        $start = time() + 86400;
        $a = $this->service()->command($this->command($this->receive('Proposed'), 'propose', $start), 1)['appointment'];
        $token = $this->token($a['id']);
        $this->assertDatabaseMissing('appointments', ['id' => $a['id'], 'response_token_hash' => $token]);
        $this->service()->command($this->command($this->receive('Reserved'), start: $start), 1);
        $this->fails('time_conflict', fn () => $this->service()->respond($a['id'], $token, 'accept'));
        $this->assertDatabaseHas('appointments', ['id' => $a['id'], 'status' => 'proposed', 'version' => 1]);
        $this->assertDatabaseMissing('booking_events', ['entity_id' => $a['id'], 'action' => 'customer_accept']);
    }

    public function test_failed_reschedule_preserves_original_and_customer_can_decline(): void
    {
        $start = time() + 86400;
        $a = $this->service()->command($this->command($this->receive('Original'), start: $start), 1)['appointment'];
        $new = $start + 7200;
        $this->service()->command(['action' => 'reschedule', 'appointment_id' => $a['id'], 'expected_version' => 1,
            'starts_at' => $new, 'ends_at' => $new + 3600, 'idempotency_key' => (string) Str::uuid()], 1);
        $token = $this->token($a['id']);
        $this->assertDatabaseHas('appointments', ['id' => $a['id'], 'status' => 'confirmed', 'starts_at' => $start, 'pending_starts_at' => $new]);
        $this->service()->command($this->command($this->receive('New reservation'), start: $new), 1);
        $this->fails('time_conflict', fn () => $this->service()->respond($a['id'], $token, 'accept'));
        $this->assertDatabaseHas('appointments', ['id' => $a['id'], 'starts_at' => $start, 'version' => 2]);
        $result = $this->service()->respond($a['id'], $token, 'decline');
        $this->assertSame('declined', $result['status']);
        $this->assertSame($result, $this->service()->respond($a['id'], $token, 'decline'));
        $this->fails('idempotency_conflict', fn () => $this->service()->respond($a['id'], $token, 'accept'));
        $this->assertDatabaseHas('appointments', ['id' => $a['id'], 'status' => 'confirmed', 'starts_at' => $start, 'pending_starts_at' => null, 'version' => 3]);
    }

    public function test_successful_acceptance_is_single_use_and_tokens_revoke_on_cancel(): void
    {
        $a = $this->service()->command($this->command($this->receive(), 'propose'), 1)['appointment'];
        $token = $this->token($a['id']);
        $first = $this->service()->respond($a['id'], $token, 'accept');
        $this->assertSame('confirmed', $first['status']);
        $this->assertSame($first, $this->service()->respond($a['id'], $token, 'accept'));
        $this->fails('idempotency_conflict', fn () => $this->service()->respond($a['id'], $token, 'decline'));
        $this->service()->command(['action' => 'cancel', 'appointment_id' => $a['id'], 'expected_version' => 2, 'idempotency_key' => (string) Str::uuid()], 1);
        $this->fails('response_link_unavailable', fn () => $this->service()->responseDetails($a['id'], $token));
        $this->assertDatabaseHas('appointments', ['id' => $a['id'], 'status' => 'cancelled', 'version' => 3]);
    }

    public function test_owner_confirmation_updates_existing_proposal_without_duplicate(): void
    {
        $a = $this->service()->command($this->command($this->receive(), 'propose'), 1)['appointment'];
        $token = $this->token($a['id']);
        $confirmed = $this->service()->command(['action' => 'confirm', 'appointment_id' => $a['id'],
            'expected_version' => 1, 'idempotency_key' => (string) Str::uuid()], 1);
        $this->assertSame('confirmed', $confirmed['appointment']['status']);
        $this->assertDatabaseCount('appointments', 1);
        $this->fails('response_link_unavailable', fn () => $this->service()->responseDetails($a['id'], $token));
        $this->assertDatabaseHas('notification_outbox', ['template' => 'appointment_propose', 'status' => 'superseded']);
    }

    public function test_latest_notification_status_is_selected_by_version_not_same_second_timestamp(): void
    {
        $a = $this->service()->command($this->command($this->receive()), 1)['appointment'];
        DB::table('notification_outbox')->where('template', 'appointment_confirm')->update(['status' => 'uncertain']);
        $this->service()->command(['action' => 'reschedule', 'appointment_id' => $a['id'], 'expected_version' => 1,
            'starts_at' => $a['starts_at'] + 7200, 'ends_at' => $a['ends_at'] + 7200,
            'idempotency_key' => (string) Str::uuid()], 1);
        $this->assertSame('queued', $this->service()->appointments()[0]['notification_status']);
        DB::table('notification_outbox')->where('template', 'appointment_reschedule')->update(['status' => 'sent']);
        $this->assertSame('sent', $this->service()->appointments()[0]['notification_status']);
    }

    public function test_expired_and_replaced_response_tokens_cannot_change_a_reservation(): void
    {
        $a = $this->service()->command($this->command($this->receive(), 'propose'), 1)['appointment'];
        $token = $this->token($a['id']);
        DB::table('appointments')->where('id', $a['id'])->update(['response_expires_at' => time() - 1]);
        $this->fails('response_link_unavailable', fn () => $this->service()->respond($a['id'], $token, 'accept'));
        $this->assertDatabaseHas('appointments', ['id' => $a['id'], 'status' => 'proposed', 'version' => 1]);
    }

    public function test_openings_publish_update_delete_have_version_and_replay_protection(): void
    {
        $data = ['action' => 'create', 'label' => 'Friday opening', 'starts_at' => time() + 86400,
            'ends_at' => time() + 90000, 'published' => false, 'idempotency_key' => (string) Str::uuid()];
        $result = $this->service()->openingCommand($data, 1);
        $this->assertSame($result, $this->service()->openingCommand($data, 1));
        $this->assertSame([], $this->service()->openings(true));
        $update = array_replace($data, ['action' => 'update', 'id' => $result['opening']['id'],
            'expected_version' => 1, 'published' => true, 'idempotency_key' => (string) Str::uuid()]);
        $this->service()->openingCommand($update, 1);
        $this->assertCount(1, $this->service()->openings(true));
        $this->fails('stale_version', fn () => $this->service()->openingCommand(array_replace($update, ['idempotency_key' => (string) Str::uuid()]), 1));
        $delete = ['action' => 'delete', 'id' => $result['opening']['id'], 'expected_version' => 2, 'idempotency_key' => (string) Str::uuid()];
        $deleted = $this->service()->openingCommand($delete, 1);
        $this->assertSame($deleted, $this->service()->openingCommand($delete, 1));
        $this->assertDatabaseCount('openings', 0);
    }
}
