<?php
/** Drush CLI only. Exact Locs retirement, immutable request-only export and explicit restore. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$site = 'site-dffd4cb9c3aa47fd';
$db = \Drupal::database();
$mode = getenv('LOCS_MIGRATION_MODE') ?: 'inspect';
$tables = [
    'request' => 'famtastic_booking_request',
    'appointment' => 'famtastic_booking_appointment',
    'event' => 'famtastic_booking_appointment_event',
    'availability' => 'famtastic_booking_availability',
];
$require = static function (bool $condition, string $code): void {
    if (!$condition) {
        throw new RuntimeException($code);
    }
};
$require(in_array($mode, ['inspect', 'freeze-exact-locs', 'export-frozen-locs', 'restore-exact-locs'], true), 'mode_invalid');
$require($db->driver() === 'mysql' && !$db->inTransaction(), 'mysql_without_active_transaction_required');
$prefix = $db->getPrefix();
$require(preg_match('/^[a-zA-Z0-9_]*$/D', $prefix) === 1, 'unsupported_table_prefix');

// Twelve managed triggers guard only Locs rows. Other businesses keep their writes.
// CREATE TRIGGER acquires the table metadata lock, draining transactions already
// using that table; the durable trigger rejects stale, previously authorized writers.
$guards = [];
foreach ($tables as $short => $table) {
    foreach (['INSERT' => 'bi', 'UPDATE' => 'bu', 'DELETE' => 'bd'] as $event => $suffix) {
        $condition = match ($event) {
            'INSERT' => "NEW.`site_key` = '$site'",
            'UPDATE' => "OLD.`site_key` = '$site' OR NEW.`site_key` = '$site'",
            'DELETE' => "OLD.`site_key` = '$site'",
        };
        $guards[] = [
            'name' => 'locs_retire_dffd4cb9_'.$short.'_'.$suffix.'_v1',
            'table' => $prefix.$table,
            'event' => $event,
            'body' => "BEGIN IF $condition THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'locs_booking_retired'; END IF; END",
        ];
    }
}
$normalize = static fn (string $value): string => strtolower(trim(preg_replace('/\s+/', ' ', $value)));
$guardState = static function (array $guard) use ($db, $normalize, $require): bool {
    $row = $db->query('SELECT EVENT_OBJECT_TABLE, EVENT_MANIPULATION, ACTION_TIMING, ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = :name', [':name' => $guard['name']])->fetchAssoc();
    if (!$row) {
        return false;
    }
    $require($row['EVENT_OBJECT_TABLE'] === $guard['table']
        && $row['EVENT_MANIPULATION'] === $guard['event']
        && $row['ACTION_TIMING'] === 'BEFORE'
        && $normalize($row['ACTION_STATEMENT']) === $normalize($guard['body']), 'unmanaged_retirement_trigger_collision');
    return true;
};
$counts = static function () use ($db, $tables, $site): array {
    $result = [];
    foreach ($tables as $table) {
        $result[$table] = (int) $db->select($table, 'r')->condition('site_key', $site)->countQuery()->execute()->fetchField();
    }
    return $result;
};
$binding = static function () use ($db, $site, $require): array {
    $row = $db->select('famtastic_booking_site_owner', 'b')->fields('b')->condition('site_key', $site)->execute()->fetchAssoc();
    $require($row && (int) $row['customer_id'] === 11 && (int) $row['organization_id'] === 11, 'owner_binding_mismatch');
    return $row;
};
$requireRequestOnly = static function (array $inventory) use ($require): void {
    $require($inventory['famtastic_booking_request'] === 1
        && $inventory['famtastic_booking_appointment'] === 0
        && $inventory['famtastic_booking_appointment_event'] === 0
        && $inventory['famtastic_booking_availability'] === 0, 'inventory_changed_requires_expanded_migration');
};
$initial = $counts();
$owner = $binding();
$present = 0;
foreach ($guards as $guard) {
    $present += (int) $guardState($guard);
}
if ($mode === 'inspect') {
    echo json_encode(['counts' => $initial, 'binding_status' => $owner['status'], 'managed_guards_present' => $present, 'managed_guards_required' => count($guards), 'writes' => 0], JSON_THROW_ON_ERROR).PHP_EOL;
    return;
}

// Serializes our explicit freeze/export/restore commands without locking other businesses.
$lockName = 'locs-independent-migration-dffd4cb9-v1';
$require((int) $db->query('SELECT GET_LOCK(:name, 0)', [':name' => $lockName])->fetchField() === 1, 'locs_migration_busy');
try {
    // A busy legacy table must produce a visible partial-freeze failure, not an unbounded DDL wait.
    $db->query('SET SESSION lock_wait_timeout = 15');
    $config = \Drupal::configFactory()->getEditable('famtastic_pipeline.settings');
    if ($mode === 'restore-exact-locs') {
        // Restoration is a separate operator decision, permitted only before the new
        // booking authority accepts traffic. No generic DROP or unrelated trigger edit.
        $require(getenv('LOCS_INDEPENDENT_TRAFFIC_DISABLED') === 'confirmed', 'independent_traffic_must_be_disabled_before_restore');
        foreach ($guards as $guard) {
            if ($guardState($guard)) {
                $db->query('DROP TRIGGER `'.$guard['name'].'`');
            }
        }
        foreach (['booking_request_enabled_sites', 'booking_availability_enabled_sites'] as $key) {
            $values = $config->get($key) ?: [];
            $require(is_array($values), 'configuration_invalid');
            $values = array_values(array_filter($values, static fn ($value) => $value !== $site));
            $values[] = $site;
            $config->set($key, $values);
        }
        $db->update('famtastic_booking_site_owner')->fields(['status' => 'active', 'changed' => time()])
            ->condition('site_key', $site)->condition('customer_id', 11)->condition('organization_id', 11)->execute();
        $config->save(true);
        echo json_encode(['status' => 'restored', 'counts' => $counts(), 'managed_guards_present' => 0, 'records_deleted' => 0], JSON_THROW_ON_ERROR).PHP_EOL;
        return;
    }

    if ($mode === 'freeze-exact-locs') {
        $requireRequestOnly($counts());
        foreach (['booking_request_enabled_sites', 'booking_availability_enabled_sites'] as $key) {
            $values = $config->get($key) ?: [];
            $require(is_array($values), 'configuration_invalid');
            $config->set($key, array_values(array_filter($values, static fn ($value) => $value !== $site)));
        }
        $config->save(true);
        $db->update('famtastic_booking_site_owner')->fields(['status' => 'retired', 'changed' => time()])
            ->condition('site_key', $site)->condition('customer_id', 11)->condition('organization_id', 11)->execute();
        foreach ($guards as $guard) {
            if (!$guardState($guard)) {
                $db->query('CREATE TRIGGER `'.$guard['name'].'` BEFORE '.$guard['event'].' ON `'.$guard['table'].'` FOR EACH ROW '.$guard['body']);
            }
            $require($guardState($guard), 'retirement_guard_not_verified');
        }
    }

    // Every target row is now immutable, including writes from old in-flight PHP.
    // Recheck AFTER all trigger DDL drained old table transactions. A changed
    // inventory halts migration and remains frozen for reconciliation, never deleted.
    $require($binding()['status'] === 'retired', 'freeze_required');
    foreach (['booking_request_enabled_sites', 'booking_availability_enabled_sites'] as $key) {
        $require(!in_array($site, $config->get($key) ?: [], true), 'freeze_required');
    }
    foreach ($guards as $guard) {
        $require($guardState($guard), 'complete_retirement_guards_required');
    }
    $finalCounts = $counts();
    $requireRequestOnly($finalCounts);
    if ($mode === 'freeze-exact-locs') {
        echo json_encode(['status' => 'frozen', 'counts' => $finalCounts, 'managed_guards_present' => count($guards), 'records_deleted' => 0], JSON_THROW_ON_ERROR).PHP_EOL;
        return;
    }
    $records = $db->select('famtastic_booking_request', 'r')->fields('r')->condition('site_key', $site)->orderBy('id')
        ->execute()->fetchAll(\Drupal\Core\Database\Statement\FetchAs::Associative);
    $require(count($records) === 1, 'export_count_mismatch');
    $recordBytes = json_encode($records, JSON_THROW_ON_ERROR);
    // Private export only: redirect through encrypted SSH into a mode-0600 file.
    echo json_encode(['schema' => 'locs.request-migration.v1', 'site_key' => $site,
        'retirement' => ['guard_version' => 1, 'guards_verified' => count($guards), 'binding_status' => 'retired', 'counts' => $finalCounts],
        'records_sha256' => hash('sha256', $recordBytes), 'records' => $records], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $db->query('SELECT RELEASE_LOCK(:name)', [':name' => $lockName]);
}
