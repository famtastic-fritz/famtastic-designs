<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class NewsletterConcurrencyTest extends TestCase
{
    public function test_smtp_success_then_database_deadlock_has_one_send_and_uncertain_state_without_outer_transaction(): void
    {
        $directory = sys_get_temp_dir().'/locs-newsletter-smtp-'.Str::uuid();
        mkdir($directory, 0700);
        $database = $directory.'/newsletter.sqlite';
        touch($database);
        $original = config('database.connections.sqlite.database');
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database,
            'app.url' => 'https://tightenupyourlocs.com', 'newsletter.enabled' => true, 'locs.mail_enabled' => true]);
        DB::purge('sqlite');
        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->assertSame(0, DB::transactionLevel(), 'This regression must exercise an outermost production-style transaction.');
            app(\App\Services\NewsletterService::class)->signup('reader@example.test');
            $row = DB::table('newsletter_outbox')->first();
            $mail = (new \Symfony\Component\Mime\Email)->from('hello@example.test')->to('reader@example.test')->text('Local fixture');
            $receipt = new \Illuminate\Mail\SentMessage(new \Symfony\Component\Mailer\SentMessage($mail, \Symfony\Component\Mailer\Envelope::create($mail)));
            $sendCount = 0;
            Mail::shouldReceive('send')->andReturnUsing(function () use (&$sendCount, $receipt) { $sendCount++; return $receipt; });
            $injected = false;
            DB::connection()->beforeExecuting(function ($query, $bindings) use (&$injected) {
                if (!$injected && str_contains($query, 'update "newsletter_outbox"') && in_array('sent', $bindings, true)) {
                    $injected = true;
                    throw new \PDOException('Deadlock found when trying to get lock');
                }
            });
            $dispatcher = app(\App\Services\NewsletterDispatcher::class);
            $this->assertSame(1, $dispatcher->dispatch()['uncertain']);
            $this->assertTrue($injected);
            $this->assertSame(1, $sendCount);
            $this->assertDatabaseHas('newsletter_outbox', ['id' => $row->id, 'status' => 'uncertain', 'attempts' => 1]);
            $this->assertSame(0, $dispatcher->dispatch()['sent']);
            $this->assertSame(1, $sendCount);
            $this->assertSame(0, DB::transactionLevel());
        } finally {
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => $original]);
            foreach (glob($directory.'/*') ?: [] as $file) unlink($file);
            rmdir($directory);
        }
    }

    public function test_two_independent_signup_processes_queue_exactly_one_confirmation(): void
    {
        $directory = sys_get_temp_dir().'/locs-newsletter-race-'.Str::uuid();
        mkdir($directory, 0700);
        $database = $directory.'/newsletter.sqlite';
        touch($database);
        $original = config('database.connections.sqlite.database');
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $database]);
        DB::purge('sqlite');
        $processes = [];
        try {
            Artisan::call('migrate', ['--force' => true]);
            $worker = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => getenv('LOCS_NEWSLETTER_DATABASE'), 'app.url' => 'https://tightenupyourlocs.com']);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\DB::statement('PRAGMA busy_timeout = 10000');
Illuminate\Support\Facades\DB::listen(function ($query) {
    if (str_contains($query->sql, 'update "newsletter_locks"')) usleep(250000);
});
touch(getenv('LOCS_NEWSLETTER_READY'));
$deadline = microtime(true) + 10;
while (!is_file(getenv('LOCS_NEWSLETTER_GATE'))) {
    if (microtime(true) > $deadline) throw new RuntimeException('Newsletter race barrier timed out');
    usleep(10000);
}
echo json_encode(app(App\Services\NewsletterService::class)->signup('reader@example.test'));
PHP;
            for ($index = 0; $index < 2; $index++) {
                $process = new Process([PHP_BINARY, '-r', $worker], base_path(), [
                    'LOCS_NEWSLETTER_DATABASE' => $database, 'LOCS_NEWSLETTER_READY' => $directory.'/ready-'.$index,
                    'LOCS_NEWSLETTER_GATE' => $directory.'/go',
                ]);
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 10;
            while (!is_file($directory.'/ready-0') || !is_file($directory.'/ready-1')) {
                if (microtime(true) > $deadline) $this->fail('Newsletter workers did not reach the barrier.');
                usleep(10000);
            }
            touch($directory.'/go');
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $this->assertSame(\App\Services\NewsletterService::RESPONSE, json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR));
            }
            $this->assertDatabaseCount('newsletter_subscribers', 1);
            $this->assertDatabaseCount('newsletter_outbox', 1);
            $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'reader@example.test', 'token_version' => 1, 'confirmations_today' => 1]);
        } finally {
            foreach ($processes as $process) if ($process->isRunning()) $process->stop();
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => $original]);
            foreach (glob($directory.'/*') ?: [] as $file) unlink($file);
            rmdir($directory);
        }
    }
}
