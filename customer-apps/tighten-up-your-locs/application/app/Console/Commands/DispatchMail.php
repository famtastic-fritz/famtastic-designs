<?php
namespace App\Console\Commands;
use App\Services\OutboxDispatcher;
use Illuminate\Console\Command;
class DispatchMail extends Command {
    protected $signature='locs:dispatch-mail {--retry=}';
    protected $description='Dispatch the independent Locs outbox; explicit retry may resend an uncertain SMTP outcome';
    public function handle(OutboxDispatcher $dispatcher):int {
        if($id=$this->option('retry')) {if(!$dispatcher->retry($id)){$this->error('No eligible notice for explicit retry.');return 1;}}
        $result=$dispatcher->dispatch();$this->line(json_encode($result));return $result['uncertain']?1:0;
    }
}
