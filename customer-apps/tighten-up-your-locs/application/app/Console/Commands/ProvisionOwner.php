<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
class ProvisionOwner extends Command {
    protected $signature='locs:provision-owner {--apply}';
    protected $description='Create the configured independent owner without printing or sending credentials';
    public function handle(): int {
        $email=config('locs.owner_email');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) { $this->error('Owner email configuration required.'); return 1; }
        $existing=User::where('email',$email)->first();
        if($existing) { $this->info($existing->is_owner?'Owner already exists; no change.':'Existing non-owner collision; no change.'); return $existing->is_owner?0:1; }
        if(!$this->option('apply')) { $this->info('Plan: create one owner; no email and no password output.'); return 0; }
        $user=new User; $user->forceFill(['name'=>'Shay','email'=>$email,'password'=>Hash::make(bin2hex(random_bytes(48))),'is_owner'=>true])->save();
        $this->info('Owner created, pending private password setup. No email sent.'); return 0;
    }
}
