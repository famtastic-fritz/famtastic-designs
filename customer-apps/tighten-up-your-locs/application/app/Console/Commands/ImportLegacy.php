<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLegacy extends Command
{
    protected $signature = 'locs:import-legacy {file} {--apply}';
    protected $description = 'Preserve the one frozen Locs request and reconcile every mapped field; no alerts';

    public function handle(): int
    {
        $this->require(config('locs.site_key') === 'site-dffd4cb9c3aa47fd', 'site_scope_invalid');
        $this->require(config('locs.public_booking_enabled') === false && config('locs.mail_enabled') === false, 'migration_requires_booking_and_mail_disabled');
        $this->require(DB::connection()->getDriverName() === 'mysql'
            && DB::selectOne('SELECT DATABASE() AS name')->name === 'nineoo_locs', 'dedicated_locs_database_required');
        $this->require(DB::transactionLevel() === 0, 'existing_transaction_not_allowed');
        $file = $this->argument('file');
        $this->require(is_file($file) && !is_link($file) && (fileperms($file) & 0777) === 0600
            && filesize($file) > 0 && filesize($file) <= 1048576, 'private_bounded_export_required');
        $bytes = file_get_contents($file);
        $document = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        $rows = self::mapExport($document);
        $beforeNotifications = DB::table('notification_outbox')->count();
        $target = DB::transaction(function () use ($rows) {
            // MySQL-only importer: a locking read serializes authority without a dry-run write.
            $this->require(DB::table('booking_resource_locks')->where('id', 1)->lockForUpdate()->first() !== null, 'booking_authority_missing');
            foreach (['appointments', 'openings', 'booking_events', 'notification_outbox'] as $table) {
                $this->require(DB::table($table)->count() === 0, 'target_inventory_requires_review');
            }
            $existingCount = DB::table('booking_requests')->count();
            $this->require($existingCount <= 1, 'target_inventory_requires_review');
            foreach ($rows as $row) {
                $existing = DB::table('booking_requests')->where('id', $row['id'])->first();
                if ($existing) {
                    $this->assertMappedRow($row, $existing);
                } else {
                    $this->require($existingCount === 0, 'target_request_collision');
                    if ($this->option('apply')) {
                        DB::table('booking_requests')->insert($row);
                    }
                }
            }
            if ($this->option('apply')) {
                $this->require(DB::table('booking_requests')->count() === 1, 'target_count_mismatch');
                foreach ($rows as $row) {
                    $this->assertMappedRow($row, DB::table('booking_requests')->where('id', $row['id'])->first());
                }
            }
            return DB::table('booking_requests')->count();
        });
        $this->require(DB::table('notification_outbox')->count() === $beforeNotifications, 'unexpected_migration_notification');
        $this->line(json_encode(['status' => $this->option('apply') ? 'imported_and_reconciled' : 'validated',
            'count' => count($rows), 'target_count' => $target, 'source_sha256' => hash('sha256', $bytes),
            'records_sha256' => $document['records_sha256'],
            'mapped_sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
            'field_reconciliation' => $this->option('apply') ? 'all_mapped_fields_equal' : 'checked_existing_only',
            'notifications_created' => 0], JSON_THROW_ON_ERROR));
        return 0;
    }

    /** Pure mapping is separately testable without touching either database. */
    public static function mapExport(array $document): array
    {
        $require = static function (bool $condition, string $code): void {
            if (!$condition) {
                throw new \RuntimeException($code);
            }
        };
        $site = 'site-dffd4cb9c3aa47fd';
        $expectedCounts = ['famtastic_booking_request' => 1, 'famtastic_booking_appointment' => 0,
            'famtastic_booking_appointment_event' => 0, 'famtastic_booking_availability' => 0];
        $require(($document['schema'] ?? null) === 'locs.request-migration.v1'
            && ($document['site_key'] ?? null) === $site && is_array($document['records'] ?? null)
            && count($document['records']) === 1, 'export_scope_or_count_invalid');
        $retirement = $document['retirement'] ?? [];
        $require(($retirement['guard_version'] ?? null) === 1 && ($retirement['guards_verified'] ?? null) === 12
            && ($retirement['binding_status'] ?? null) === 'retired'
            && ($retirement['counts'] ?? null) === $expectedCounts, 'immutable_export_proof_required');
        $require(is_string($document['records_sha256'] ?? null)
            && hash_equals(hash('sha256', json_encode($document['records'], JSON_THROW_ON_ERROR)), $document['records_sha256']), 'export_record_digest_mismatch');
        $rows = [];
        $ids = [];
        foreach ($document['records'] as $record) {
            $require(is_array($record), 'invalid_legacy_record');
            foreach (['public_id', 'customer_name', 'email', 'phone', 'service_key', 'requested_window', 'message', 'status', 'source'] as $key) {
                $require(isset($record[$key]) && is_string($record[$key]), 'invalid_legacy_record_fields');
            }
            $require(($record['site_key'] ?? null) === $site && Str::isUuid($record['public_id'])
                && !isset($ids[strtolower($record['public_id'])])
                && filter_var($record['email'], FILTER_VALIDATE_EMAIL)
                && in_array($record['consent'] ?? null, [1, '1', true], true), 'invalid_legacy_record');
            $ids[strtolower($record['public_id'])] = true;
            foreach (['customer_name' => 120, 'email' => 254, 'phone' => 40, 'service_key' => 40,
                'requested_window' => 180, 'message' => 2000, 'status' => 24, 'source' => 80] as $key => $limit) {
                $require(mb_strlen($record[$key]) <= $limit && !str_contains($record[$key], "\0"), 'legacy_field_requires_expanded_mapping');
            }
            $require(trim($record['customer_name']) !== '' && trim($record['requested_window']) !== ''
                && preg_match('/^[a-z0-9-]+$/D', $record['service_key']) === 1
                && in_array($record['status'], ['new', 'reviewing', 'responded', 'closed', 'declined'], true), 'legacy_values_invalid');
            foreach (['id', 'created', 'changed'] as $key) {
                $require(isset($record[$key]) && (is_int($record[$key]) || (is_string($record[$key]) && ctype_digit($record[$key])))
                    && (int) $record[$key] > 0, 'legacy_identity_or_timestamp_invalid');
            }
            $require((int) $record['changed'] >= (int) $record['created'], 'legacy_timestamp_order_invalid');
            $rows[] = ['id' => $record['public_id'], 'idempotency_key' => 'legacy:'.$record['public_id'],
                'payload_hash' => hash('sha256', json_encode($record, JSON_THROW_ON_ERROR)),
                'name' => $record['customer_name'], 'email' => $record['email'], 'phone' => $record['phone'],
                'service_key' => $record['service_key'], 'requested_window' => $record['requested_window'],
                'message' => $record['message'], 'status' => $record['status'] === 'new' ? 'received' : $record['status'],
                'version' => 1, 'source' => $record['source'],
                'consent_at' => gmdate('Y-m-d H:i:s', (int) $record['created']),
                'created_at' => gmdate('Y-m-d H:i:s', (int) $record['created']),
                'updated_at' => gmdate('Y-m-d H:i:s', (int) $record['changed'])];
        }
        return $rows;
    }

    private function assertMappedRow(array $expected, ?object $actual): void
    {
        $this->require($actual !== null, 'migration_target_missing');
        foreach ($expected as $key => $value) {
            $actualValue = $actual->$key;
            $this->require($key === 'version' ? (int) $actualValue === $value : $actualValue === $value, 'migration_field_collision');
        }
    }

    private function require(bool $condition, string $code): void
    {
        if (!$condition) {
            throw new \RuntimeException($code);
        }
    }
}
