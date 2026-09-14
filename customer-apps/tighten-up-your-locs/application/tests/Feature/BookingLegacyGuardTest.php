<?php

namespace {
    // Source-contract harness only; no Drupal database or production execution.
    if (!class_exists('Drupal', false)) {
        class Drupal
        {
            public static object $legacyDatabase;
            public static object $legacyConfig;
            public static function database(): object { return self::$legacyDatabase; }
            public static function configFactory(): object
            {
                return new class {
                    public function getEditable(string $name): object { return \Drupal::$legacyConfig; }
                };
            }
        }
    }
}

namespace Drupal\Core\Database\Statement {
    if (!enum_exists(FetchAs::class)) {
        enum FetchAs { case Associative; }
    }
}

namespace Tests\Feature {
    use Tests\TestCase;

    class BookingLegacyStatement
    {
        public function __construct(private mixed $value) {}
        public function fetchField(): mixed { return $this->value; }
        public function fetchAssoc(): mixed { return $this->value; }
        public function fetchAll(mixed $mode = null): mixed { return $this->value; }
    }

    class BookingLegacyQuery
    {
        private bool $count = false;
        private array $values = [];
        public function __construct(private BookingLegacyDatabase $db, private string $table, private bool $update = false) {}
        public function condition(mixed ...$arguments): self { return $this; }
        public function fields(mixed ...$arguments): self
        {
            if ($this->update) $this->values = $arguments[0];
            return $this;
        }
        public function orderBy(mixed ...$arguments): self { return $this; }
        public function countQuery(): self { $this->count = true; return $this; }
        public function execute(): mixed
        {
            if ($this->update) {
                $this->db->owner = array_replace($this->db->owner, $this->values);
                $this->db->writes++;
                return 1;
            }
            if ($this->count) return new BookingLegacyStatement($this->db->counts[$this->table]);
            return new BookingLegacyStatement($this->table === 'famtastic_booking_site_owner' ? $this->db->owner : $this->db->records);
        }
    }

    class BookingLegacyDatabase
    {
        public array $counts = ['famtastic_booking_request' => 1, 'famtastic_booking_appointment' => 0,
            'famtastic_booking_appointment_event' => 0, 'famtastic_booking_availability' => 0];
        public array $owner = ['customer_id' => 11, 'organization_id' => 11, 'status' => 'active'];
        public array $records = [['id' => 1, 'public_id' => 'ddc62fea-e574-4f57-9139-3bcdd9c70646', 'site_key' => 'site-dffd4cb9c3aa47fd']];
        public array $guards = [];
        public int $writes = 0;
        public bool $lateAppointment = false;
        public bool $lockReleased = false;
        public function driver(): string { return 'mysql'; }
        public function inTransaction(): bool { return false; }
        public function getPrefix(): string { return ''; }
        public function select(string $table, string $alias): BookingLegacyQuery { return new BookingLegacyQuery($this, $table); }
        public function update(string $table): BookingLegacyQuery { return new BookingLegacyQuery($this, $table, true); }
        public function query(string $sql, array $parameters = []): BookingLegacyStatement
        {
            if (str_contains($sql, 'information_schema.TRIGGERS')) return new BookingLegacyStatement($this->guards[$parameters[':name']] ?? false);
            if (str_contains($sql, 'GET_LOCK')) return new BookingLegacyStatement(1);
            if (str_contains($sql, 'RELEASE_LOCK')) { $this->lockReleased = true; return new BookingLegacyStatement(1); }
            if ($sql === 'SET SESSION lock_wait_timeout = 15') return new BookingLegacyStatement(1);
            if (preg_match('/^CREATE TRIGGER `([^`]+)` BEFORE (INSERT|UPDATE|DELETE) ON `([^`]+)` FOR EACH ROW (.+)$/D', $sql, $matches)) {
                $this->guards[$matches[1]] = ['EVENT_OBJECT_TABLE' => $matches[3], 'EVENT_MANIPULATION' => $matches[2], 'ACTION_TIMING' => 'BEFORE', 'ACTION_STATEMENT' => $matches[4]];
                $this->writes++;
                if ($this->lateAppointment && count($this->guards) === 12) $this->counts['famtastic_booking_appointment'] = 1;
                return new BookingLegacyStatement(1);
            }
            if (preg_match('/^DROP TRIGGER `([^`]+)`$/D', $sql, $matches)) {
                unset($this->guards[$matches[1]]);
                $this->writes++;
                return new BookingLegacyStatement(1);
            }
            throw new \RuntimeException('unexpected_mock_query');
        }
    }

    class BookingLegacyConfig
    {
        public array $values = ['booking_request_enabled_sites' => ['site-dffd4cb9c3aa47fd', 'other-business'],
            'booking_availability_enabled_sites' => ['site-dffd4cb9c3aa47fd', 'other-business']];
        public function get(string $key): mixed { return $this->values[$key] ?? null; }
        public function set(string $key, mixed $value): self { $this->values[$key] = $value; return $this; }
        public function save(bool $trusted): void {}
    }

    class BookingLegacyGuardTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            \Drupal::$legacyDatabase = new BookingLegacyDatabase();
            \Drupal::$legacyConfig = new BookingLegacyConfig();
        }

        private function execute(string $mode, bool $restoreAllowed = false): array
        {
            $previousMode = getenv('LOCS_MIGRATION_MODE');
            $previousRestore = getenv('LOCS_INDEPENDENT_TRAFFIC_DISABLED');
            putenv('LOCS_MIGRATION_MODE='.$mode);
            putenv('LOCS_INDEPENDENT_TRAFFIC_DISABLED='.($restoreAllowed ? 'confirmed' : ''));
            ob_start();
            try {
                require dirname(__DIR__, 3).'/legacy-export.php';
                return json_decode(ob_get_contents(), true, flags: JSON_THROW_ON_ERROR);
            } finally {
                ob_end_clean();
                putenv($previousMode === false ? 'LOCS_MIGRATION_MODE' : 'LOCS_MIGRATION_MODE='.$previousMode);
                putenv($previousRestore === false ? 'LOCS_INDEPENDENT_TRAFFIC_DISABLED' : 'LOCS_INDEPENDENT_TRAFFIC_DISABLED='.$previousRestore);
            }
        }

        public function test_inspection_does_not_mutate_configuration_or_tables(): void
        {
            $result = $this->execute('inspect');
            $this->assertSame(0, $result['writes']);
            $this->assertSame(0, \Drupal::$legacyDatabase->writes);
            $this->assertSame('active', \Drupal::$legacyDatabase->owner['status']);
        }

        public function test_freeze_creates_twelve_scoped_guards_and_preserves_other_business(): void
        {
            $result = $this->execute('freeze-exact-locs');
            $this->assertSame('frozen', $result['status']);
            $this->assertCount(12, \Drupal::$legacyDatabase->guards);
            foreach (\Drupal::$legacyDatabase->guards as $guard) {
                $this->assertSame('BEFORE', $guard['ACTION_TIMING']);
                $this->assertStringContainsString("'site-dffd4cb9c3aa47fd'", $guard['ACTION_STATEMENT']);
                if ($guard['EVENT_MANIPULATION'] === 'UPDATE') {
                    $this->assertStringContainsString('OLD.`site_key`', $guard['ACTION_STATEMENT']);
                    $this->assertStringContainsString('NEW.`site_key`', $guard['ACTION_STATEMENT']);
                }
            }
            $this->assertSame(['other-business'], \Drupal::$legacyConfig->values['booking_request_enabled_sites']);
            $this->assertSame('retired', \Drupal::$legacyDatabase->owner['status']);
            $writes = \Drupal::$legacyDatabase->writes;
            $this->execute('freeze-exact-locs');
            $this->assertSame($writes + 1, \Drupal::$legacyDatabase->writes); // Binding only; no trigger recreation.
        }

        public function test_late_pre_freeze_appointment_is_detected_after_guard_installation(): void
        {
            \Drupal::$legacyDatabase->lateAppointment = true;
            try {
                $this->execute('freeze-exact-locs');
                $this->fail('Expected changed inventory rejection.');
            } catch (\RuntimeException $error) {
                $this->assertSame('inventory_changed_requires_expanded_migration', $error->getMessage());
            }
            $this->assertCount(12, \Drupal::$legacyDatabase->guards);
            $this->assertSame('retired', \Drupal::$legacyDatabase->owner['status']);
            $this->assertTrue(\Drupal::$legacyDatabase->lockReleased);
        }

        public function test_name_collision_never_overwrites_or_drops_unmanaged_trigger(): void
        {
            $this->execute('freeze-exact-locs');
            $name = array_key_first(\Drupal::$legacyDatabase->guards);
            \Drupal::$legacyDatabase->guards[$name]['ACTION_STATEMENT'] = 'BEGIN SET @another_business = 1; END';
            $before = \Drupal::$legacyDatabase->writes;
            try {
                $this->execute('restore-exact-locs', true);
                $this->fail('Expected trigger collision rejection.');
            } catch (\RuntimeException $error) {
                $this->assertSame('unmanaged_retirement_trigger_collision', $error->getMessage());
            }
            $this->assertSame($before, \Drupal::$legacyDatabase->writes);
            $this->assertCount(12, \Drupal::$legacyDatabase->guards);
        }

        public function test_frozen_export_contains_verified_inventory_and_record_digest(): void
        {
            $this->execute('freeze-exact-locs');
            $writes = \Drupal::$legacyDatabase->writes;
            $export = $this->execute('export-frozen-locs');
            $this->assertSame(12, $export['retirement']['guards_verified']);
            $this->assertSame('retired', $export['retirement']['binding_status']);
            $this->assertSame(\Drupal::$legacyDatabase->records, $export['records']);
            $this->assertSame(hash('sha256', json_encode($export['records'], JSON_THROW_ON_ERROR)), $export['records_sha256']);
            $this->assertSame($writes, \Drupal::$legacyDatabase->writes);
        }

        public function test_restore_requires_explicit_independent_traffic_disabled_assertion(): void
        {
            $this->execute('freeze-exact-locs');
            try {
                $this->execute('restore-exact-locs');
                $this->fail('Expected restore boundary rejection.');
            } catch (\RuntimeException $error) {
                $this->assertSame('independent_traffic_must_be_disabled_before_restore', $error->getMessage());
            }
            $this->assertCount(12, \Drupal::$legacyDatabase->guards);
            \Drupal::$legacyDatabase->guards['unrelated_business_trigger'] = ['unrelated' => true];
            $result = $this->execute('restore-exact-locs', true);
            $this->assertSame('restored', $result['status']);
            $this->assertSame(['unrelated_business_trigger'], array_keys(\Drupal::$legacyDatabase->guards));
            $this->assertContains('other-business', \Drupal::$legacyConfig->values['booking_request_enabled_sites']);
        }
    }
}
