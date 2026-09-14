<?php
/**
 * CLI-only pre-cutover MySQL smoke check. Every synthetic record is rolled back.
 * Usage: php tests/Feature/BookingProductionCheck.php --run-exact-locs-rollback-check
 * This is not a PHPUnit file and needs no production dev dependencies.
 */
declare(strict_types=1);

use App\Services\BookingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

if (PHP_SAPI !== 'cli' || $argc !== 2 || $argv[1] !== '--run-exact-locs-rollback-check') {
    fwrite(STDERR, "exact_rollback_check_flag_required\n");
    exit(1);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$assert = static function (bool $ok, string $code): void {
    if (!$ok) {
        throw new RuntimeException($code);
    }
};
$tables = ['booking_requests', 'appointments', 'openings', 'booking_events', 'notification_outbox', 'booking_resource_locks'];
$snapshot = static function () use ($tables): array {
    $result = [];
    foreach ($tables as $table) {
        // Only digest/counts leave this process; no request details or tokens are printed.
        $rows = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $result[$table] = ['count' => count($rows), 'sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
    }
    return $result;
};
$baseline = null;
$sentBefore = 0;
$passed = [];
$failure = null;
$started = false;

try {
    $assert($app->environment('production'), 'production_environment_required');
    $assert(config('locs.site_key') === BookingService::SITE_KEY, 'site_scope_invalid');
    $assert(config('locs.public_booking_enabled') === false && config('locs.mail_enabled') === false, 'precutover_booking_and_mail_must_be_disabled');
    $assert(DB::connection()->getDriverName() === 'mysql' && DB::selectOne('SELECT DATABASE() AS name')->name === 'nineoo_locs', 'dedicated_locs_database_required');
    $assert(DB::transactionLevel() === 0, 'existing_transaction_not_allowed');
    $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', 'nineoo_locs')->whereIn('TABLE_NAME', $tables)->get(['TABLE_NAME', 'ENGINE']);
    $assert(count($engines) === count($tables), 'required_booking_tables_missing');
    foreach ($engines as $row) {
        $assert(strcasecmp($row->ENGINE, 'InnoDB') === 0, 'transactional_tables_required');
    }
    $assert(DB::table('appointments')->count() === 0, 'existing_appointments_stop_synthetic_check');
    // No transport is called, even if a future service accidentally adds a mail dependency.
    config(['locs.mail_enabled' => false, 'locs.owner_email' => 'locs-rollback-check@example.invalid', 'mail.default' => 'array']);
    $baseline = $snapshot();
    $sentBefore = DB::table('notification_outbox')->whereNotNull('sent_at')->count();
    DB::beginTransaction();
    $started = true;
    $assert(DB::table('booking_resource_locks')->where('id', 1)->increment('lock_version') === 1, 'booking_authority_missing');
    DB::table('booking_resource_locks')->where('id', 1)->lockForUpdate()->first();
    $assert(DB::table('appointments')->count() === 0, 'appointment_appeared_before_lock');
    $assert(DB::table('booking_requests')->count() === $baseline['booking_requests']['count'], 'inventory_changed_before_check');
    $service = app(BookingService::class);
    $actor = (int) DB::table('users')->where('is_owner', true)->value('id');
    $assert($actor > 0, 'independent_owner_required');
    $run = (string) Str::uuid();
    $firstPayload = ['name' => 'Rollback-only Locs QA', 'email' => 'locs-rollback-check@example.invalid',
        'service_key' => 'question', 'requested_window' => 'Synthetic pre-cutover check, never committed',
        'message' => 'Synthetic record inside a rollback-only transaction.', 'idempotency_key' => 'rollback-'.$run.'-request'];
    $receipt = $service->receive($firstPayload);
    $assert(Str::isUuid($receipt['reference']) && DB::table('booking_requests')->where('id', $receipt['reference'])->exists(), 'durable_request_write_failed');
    $assert($receipt === $service->receive($firstPayload), 'request_replay_failed');
    $passed[] = 'request_insert_and_replay';
    $start = time() + 2 * 86400;
    $command = ['action' => 'confirm', 'request_id' => $receipt['reference'], 'expected_version' => 1,
        'starts_at' => $start, 'ends_at' => $start + 3600, 'idempotency_key' => 'rollback-'.$run.'-confirm'];
    $confirmation = $service->command($command, $actor);
    $assert($confirmation['appointment']['status'] === 'confirmed', 'confirmation_failed');
    $assert($confirmation === $service->command($command, $actor), 'command_replay_failed');
    $assert(DB::transactionLevel() === 1, 'nested_transaction_escaped_outer_rollback');
    $passed[] = 'confirmation_and_replay';
    $expect = static function (string $code, callable $operation) use ($assert): void {
        try {
            $operation();
        } catch (HttpException $error) {
            $assert($error->getMessage() === $code, 'unexpected_conflict_code');
            return;
        }
        throw new RuntimeException('expected_conflict_not_raised');
    };
    $second = $service->receive(array_replace($firstPayload, ['name' => 'Second rollback-only Locs QA', 'idempotency_key' => 'rollback-'.$run.'-second']))['reference'];
    $mailBeforeConflict = DB::table('notification_outbox')->count();
    $expect('time_conflict', fn () => $service->command(array_replace($command, ['request_id' => $second, 'idempotency_key' => 'rollback-'.$run.'-conflict']), $actor));
    $assert(DB::table('appointments')->count() === 1 && DB::table('notification_outbox')->count() === $mailBeforeConflict, 'conflict_left_partial_writes');
    $passed[] = 'same_slot_conflict_and_atomic_rollback';
    $expect('idempotency_conflict', fn () => $service->command(array_replace($command, ['ends_at' => $start + 7200]), $actor));
    $passed[] = 'changed_payload_replay_denied';
    $id = $confirmation['appointment']['id'];
    $reschedule = ['action' => 'reschedule', 'appointment_id' => $id, 'expected_version' => 1,
        'starts_at' => $start + 7200, 'ends_at' => $start + 10800, 'idempotency_key' => 'rollback-'.$run.'-reschedule'];
    $service->command($reschedule, $actor);
    $expect('stale_version', fn () => $service->command(['action' => 'cancel', 'appointment_id' => $id,
        'expected_version' => 1, 'idempotency_key' => 'rollback-'.$run.'-stale'], $actor));
    $original = DB::table('appointments')->where('id', $id)->first();
    $assert($original->status === 'confirmed' && (int) $original->starts_at === $start
        && (int) $original->pending_starts_at === $start + 7200, 'original_reservation_not_preserved');
    $passed[] = 'stale_version_and_original_reservation_preserved';
    $assert(DB::table('notification_outbox')->whereNotNull('sent_at')->count() === $sentBefore, 'unexpected_transport_state');
    $assert(DB::transactionLevel() === 1, 'outer_transaction_missing');
} catch (Throwable $error) {
    $failure = $error instanceof RuntimeException && preg_match('/^[a-z_]+$/D', $error->getMessage())
        ? $error->getMessage() : 'rollback_check_failed_inspect_private_logs';
} finally {
    if ($started) {
        try {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        } catch (Throwable) {
            $failure = 'rollback_failed_stop_cutover';
        }
    }
}

if ($baseline !== null) {
    try {
        if ($snapshot() !== $baseline) {
            $failure = 'post_rollback_snapshot_mismatch_stop_cutover';
        } else {
            $passed[] = 'all_six_table_snapshots_unchanged_after_rollback';
        }
    } catch (Throwable) {
        $failure = 'post_rollback_snapshot_unavailable_stop_cutover';
    }
}
echo json_encode(['status' => $failure ? 'failed' : 'mysql_rollback_checks_passed',
    'checks' => $passed, 'failure' => $failure, 'synthetic_commits' => 0,
    'mail_transport_invocations' => 0, 'concurrency_proof' => 'not_claimed_by_this_single_process_check'], JSON_THROW_ON_ERROR).PHP_EOL;
exit($failure ? 1 : 0);
