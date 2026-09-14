<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NewsletterDispatcher
{
    public function __construct(private readonly NewsletterService $newsletter) {}

    public function dispatch(int $limit = 10): array
    {
        $counts = ['status' => 'disabled', 'sent' => 0, 'uncertain' => 0, 'superseded' => 0];
        if (!config('newsletter.enabled') || !config('locs.mail_enabled')) return $counts;
        if (app()->environment('production') && config('mail.default') !== 'smtp') throw new \RuntimeException('production_smtp_required');
        $counts['status'] = 'processed';
        $this->newsletter->locked(function () {
            // A sender crash can follow provider acceptance. Never automatically resend.
            DB::table('newsletter_outbox')->where('status', 'sending')->where('leased_until', '<', now())
                ->update(['status' => 'uncertain', 'last_error' => 'expired_sender_requires_review', 'updated_at' => now()]);
        });
        for ($i = 0; $i < max(0, min($limit, 25)); $i++) {
            $notice = $this->newsletter->locked(function () {
                $row = DB::table('newsletter_outbox')->where('status', 'queued')->orderBy('created_at')->orderBy('id')->first();
                if (!$row) return null;
                $lease = (string) Str::uuid();
                DB::table('newsletter_outbox')->where('id', $row->id)->update(['status' => 'sending',
                    'lease_token' => $lease, 'leased_until' => now()->addMinutes(2), 'attempts' => $row->attempts + 1, 'updated_at' => now()]);
                $row->lease_token = $lease;
                return $row;
            });
            if (!$notice) break;
            try {
                $result = $this->newsletter->locked(function () use ($notice) {
                    $fresh = DB::table('newsletter_outbox')->where('id', $notice->id)->first();
                    if (!$fresh || $fresh->status !== 'sending' || $fresh->lease_token !== $notice->lease_token) return null;
                    $subscriber = DB::table('newsletter_subscribers')->where('id', $notice->subscriber_id)->first();
                    if (!$subscriber || !$subscriber->confirmation_token_hash
                        || (int) $subscriber->token_version !== (int) $notice->token_version
                        || !CarbonImmutable::parse($subscriber->confirmation_expires_at)->isFuture()) {
                        DB::table('newsletter_outbox')->where('id', $notice->id)->update(['status' => 'superseded',
                            'lease_token' => null, 'leased_until' => null, 'updated_at' => now()]);
                        return 'superseded';
                    }
                    if ($notice->template_id !== 'locs_newsletter_confirmation' || (int) $notice->template_version !== 1) {
                        throw new \RuntimeException('unknown_newsletter_template');
                    }
                    $payload = json_decode(Crypt::decryptString($notice->payload_encrypted), true, flags: JSON_THROW_ON_ERROR);
                    $base = rtrim(config('app.url'), '/').'/api/newsletter/';
                    if (!str_starts_with($payload['confirmation_url'], $base.'confirm/'.$subscriber->id.'/')
                        || !str_starts_with($payload['unsubscribe_url'], $base.'unsubscribe/'.$subscriber->id.'/')) {
                        throw new \RuntimeException('invalid_newsletter_destination');
                    }
                    // The dedicated newsletter lock serializes confirmation changes/unsubscribe
                    // against the final send check. It never blocks the booking authority.
                    $sent = Mail::send(['html' => 'mail.newsletter-confirmation', 'text' => 'mail.newsletter-confirmation-text'],
                        $payload, function ($message) use ($subscriber, $notice) {
                            $message->to($subscriber->email)->subject('Confirm The Locs Letter · Tighten Up Your Locs');
                            $message->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', $notice->id.'@tightenupyourlocs.com');
                        });
                    if (!$sent) throw new \RuntimeException('no_transport_receipt');
                    DB::table('newsletter_outbox')->where('id', $notice->id)->update(['status' => 'sent', 'sent_at' => now(),
                        'provider_message_id' => $sent->getMessageId(), 'last_error' => null,
                        'lease_token' => null, 'leased_until' => null, 'updated_at' => now()]);
                    return 'sent';
                }, 1); // Never retry a transaction that may already have handed a message to SMTP.
                if ($result) $counts[$result]++;
            } catch (\Throwable) {
                DB::table('newsletter_outbox')->where('id', $notice->id)->where('lease_token', $notice->lease_token)
                    ->update(['status' => 'uncertain', 'last_error' => 'transport_outcome_requires_review', 'updated_at' => now()]);
                $counts['uncertain']++;
            }
        }
        return $counts;
    }

    public function retry(string $id): bool
    {
        if (!Str::isUuid($id)) return false;
        return $this->newsletter->locked(fn () => DB::table('newsletter_outbox')->where('id', $id)->where('status', 'uncertain')
            ->update(['status' => 'queued', 'lease_token' => null, 'leased_until' => null, 'updated_at' => now()]) === 1);
    }
}
