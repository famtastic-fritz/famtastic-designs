<?php

namespace Tests\Feature;

use App\Services\BookingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    public function test_separate_php_processes_compete_for_one_slot_with_one_winner(): void
    {
        $directory = sys_get_temp_dir().'/locs-booking-race-'.Str::uuid();
        mkdir($directory, 0700);
        $database = $directory.'/booking.sqlite';
        touch($database);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database,
            'locs.owner_email' => 'owner@example.test']);
        DB::purge('sqlite');
        $processes = [];

        try {
            Artisan::call('migrate', ['--force' => true]);
            $service = app(BookingService::class);
            $start = time() + 86400;
            $commands = [];
            foreach (['First', 'Second'] as $name) {
                $reference = $service->receive(['name' => $name, 'email' => strtolower($name).'@example.test',
                    'service_key' => 'retightening', 'requested_window' => 'Tomorrow', 'idempotency_key' => (string) Str::uuid()])['reference'];
                $commands[] = ['action' => 'confirm', 'request_id' => $reference, 'expected_version' => 1,
                    'starts_at' => $start, 'ends_at' => $start + 3600, 'idempotency_key' => (string) Str::uuid()];
            }

            $worker = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => getenv('LOCS_TEST_DATABASE')]);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout = 10000');
Illuminate\Support\Facades\DB::listen(function ($query) {
    if (str_contains($query->sql, 'update "booking_resource_locks"')) usleep(250000);
});
touch(getenv('LOCS_TEST_READY'));
$deadline = microtime(true) + 10;
while (!is_file(getenv('LOCS_TEST_GATE'))) {
    if (microtime(true) > $deadline) throw new RuntimeException('Race gate timed out');
    usleep(10000);
}
try {
    $command = json_decode(base64_decode(getenv('LOCS_TEST_COMMAND')), true, flags: JSON_THROW_ON_ERROR);
    $result = app(App\Services\BookingService::class)->command($command, 1);
    echo json_encode(['result' => 'confirmed', 'id' => $result['appointment']['id']]);
} catch (Symfony\Component\HttpKernel\Exception\HttpException $error) {
    echo json_encode(['result' => $error->getMessage()]);
}
PHP;
            foreach ($commands as $index => $command) {
                $process = new Process([PHP_BINARY, '-r', $worker], base_path(), [
                    'LOCS_TEST_DATABASE' => $database, 'LOCS_TEST_READY' => $directory.'/ready-'.$index,
                    'LOCS_TEST_GATE' => $directory.'/go', 'LOCS_TEST_COMMAND' => base64_encode(json_encode($command)),
                ]);
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (!is_file($directory.'/ready-0') || !is_file($directory.'/ready-1')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Independent booking workers did not reach the barrier.');
                }
                usleep(10000);
            }
            touch($directory.'/go');
            $outcomes = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $outcomes[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['result'];
            }
            sort($outcomes);
            $this->assertSame(['confirmed', 'time_conflict'], $outcomes);
            $this->assertDatabaseCount('appointments', 1);
            $this->assertSame(1, DB::table('booking_events')->where('action', 'confirm')->count());
            $this->assertSame(1, DB::table('notification_outbox')->where('template', 'appointment_confirm')->count());
            $this->assertSame(1, DB::table('booking_requests')->where('status', 'received')->count());
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            DB::purge('sqlite');
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
