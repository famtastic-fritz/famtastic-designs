<?php
namespace App\Notifications;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
class OwnerPasswordReset extends ResetPassword {
    public function toMail($notifiable) {
        $url=route('password.reset',['token'=>$this->token,'email'=>$notifiable->getEmailForPasswordReset()]);
        return (new MailMessage)->subject('Your Tighten Up Your Locs admin access')->greeting('Hi Shay,')
          ->line('Set up or reset the password for your private Tighten Up Your Locs admin.')
          ->action('Choose your Locs password',$url)
          ->line('This private link expires in 60 minutes. Your admin is separate from FAMtastic Designs.')
          ->line('If you did not request this, you can ignore this email.')->salutation('Tighten Up Your Locs');
    }
}
