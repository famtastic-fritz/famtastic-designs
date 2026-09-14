<?php

namespace App\Console\Commands;

use App\Services\NewsletterDispatcher;
use Illuminate\Console\Command;

class DispatchNewsletter extends Command
{
    protected $signature = 'locs:dispatch-newsletter {--retry= : Exact uncertain notice UUID; retry may duplicate a message already accepted by SMTP}';
    protected $description = 'Dispatch only requested double-opt-in newsletter confirmations from the independent Locs database';

    public function handle(NewsletterDispatcher $dispatcher): int
    {
        if (($id = $this->option('retry')) && !$dispatcher->retry($id)) {
            $this->error('No uncertain newsletter notice matched.');
            return self::FAILURE;
        }
        $result = $dispatcher->dispatch();
        $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        return $result['uncertain'] ? self::FAILURE : self::SUCCESS;
    }
}
