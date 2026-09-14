<?php

namespace Tests\Feature;

use App\Console\Commands\ImportLegacy;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingImportTest extends TestCase
{
    private function document(): array
    {
        $records = [[
            'id' => '12', 'public_id' => (string) Str::uuid(), 'site_key' => 'site-dffd4cb9c3aa47fd',
            'customer_name' => 'Migration fixture', 'email' => 'fixture@example.invalid', 'phone' => '',
            'service_key' => 'retightening', 'requested_window' => str_repeat('x', 180),
            'message' => 'Preserve this original text.', 'status' => 'new', 'consent' => '1',
            'source' => 'tighten-up-your-locs-site', 'created' => '1789344000', 'changed' => '1789430400',
        ]];
        return ['schema' => 'locs.request-migration.v1', 'site_key' => 'site-dffd4cb9c3aa47fd',
            'retirement' => ['guard_version' => 1, 'guards_verified' => 12, 'binding_status' => 'retired',
                'counts' => ['famtastic_booking_request' => 1, 'famtastic_booking_appointment' => 0,
                    'famtastic_booking_appointment_event' => 0, 'famtastic_booking_availability' => 0]],
            'records_sha256' => hash('sha256', json_encode($records, JSON_THROW_ON_ERROR)), 'records' => $records];
    }

    public function test_preserves_uuid_contact_text_original_source_and_utc_dates(): void
    {
        $document = $this->document();
        $record = $document['records'][0];
        $mapped = ImportLegacy::mapExport($document)[0];
        $this->assertSame($record['public_id'], $mapped['id']);
        $this->assertSame($record['customer_name'], $mapped['name']);
        foreach (['email', 'phone', 'service_key', 'requested_window', 'message', 'source'] as $key) {
            $this->assertSame($record[$key], $mapped[$key]);
        }
        $this->assertSame('received', $mapped['status']);
        $this->assertSame(gmdate('Y-m-d H:i:s', (int) $record['created']), $mapped['created_at']);
        $this->assertSame($mapped['created_at'], $mapped['consent_at']);
        $this->assertSame(gmdate('Y-m-d H:i:s', (int) $record['changed']), $mapped['updated_at']);
        $this->assertSame(hash('sha256', json_encode($record, JSON_THROW_ON_ERROR)), $mapped['payload_hash']);
    }

    public function test_incomplete_retirement_guards_refuse_import(): void
    {
        $document = $this->document();
        $document['retirement']['guards_verified'] = 11;
        $this->expectExceptionMessage('immutable_export_proof_required');
        ImportLegacy::mapExport($document);
    }

    public function test_altered_record_digest_refuses_import(): void
    {
        $document = $this->document();
        $document['records'][0]['message'] = 'Changed after export';
        $this->expectExceptionMessage('export_record_digest_mismatch');
        ImportLegacy::mapExport($document);
    }

    public function test_inventory_growth_refuses_fixed_one_record_migration(): void
    {
        $document = $this->document();
        $document['records'][] = $document['records'][0];
        $this->expectExceptionMessage('export_scope_or_count_invalid');
        ImportLegacy::mapExport($document);
    }

    public function test_values_are_rejected_before_database_can_truncate_them(): void
    {
        $document = $this->document();
        $document['records'][0]['requested_window'] = str_repeat('x', 181);
        $document['records_sha256'] = hash('sha256', json_encode($document['records'], JSON_THROW_ON_ERROR));
        $this->expectExceptionMessage('legacy_field_requires_expanded_mapping');
        ImportLegacy::mapExport($document);
    }

    public function test_export_with_any_appointment_cannot_use_request_only_import(): void
    {
        $document = $this->document();
        $document['retirement']['counts']['famtastic_booking_appointment'] = 1;
        $this->expectExceptionMessage('immutable_export_proof_required');
        ImportLegacy::mapExport($document);
    }
}
