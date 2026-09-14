<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NewsletterService
{
    public const RESPONSE = ['status' => 'pending', 'message' => 'Check your email to confirm your subscription.'];

    /** A write-first mutex works on both MySQL and SQLite; duplicate signup queues once. */
    public function locked(callable $operation, int $attempts = 5): mixed
    {
        return DB::transaction(function () use ($operation) {
            if (DB::table('newsletter_locks')->where('id', 1)->increment('version') !== 1) {
                throw new \RuntimeException('newsletter_authority_missing');
            }
            return $operation();
        }, $attempts);
    }

    public function signup(string $email): array
    {
        return $this->locked(function () use ($email) {
            $email = Str::lower(trim($email));
            $now = now();
            $row = DB::table('newsletter_subscribers')->where('email', $email)->first();
            // Existing subscriptions are never disclosed or enrolled twice.
            if ($row && $row->status === 'subscribed') return self::RESPONSE;
            if ($row && $row->last_confirmation_queued_at
                && CarbonImmutable::parse($row->last_confirmation_queued_at)->addMinutes(config('newsletter.resend_minutes'))->isAfter($now)) return self::RESPONSE;
            $todayCount = $row && $row->confirmation_day === $now->toDateString() ? (int) $row->confirmations_today : 0;
            if ($todayCount >= config('newsletter.daily_confirmation_limit')) return self::RESPONSE;

            $id = $row?->id ?? (string) Str::uuid();
            $unsubscribeToken = $row ? Crypt::decryptString($row->unsubscribe_token_encrypted) : bin2hex(random_bytes(32));
            $confirmationToken = bin2hex(random_bytes(32));
            $version = (int) ($row?->token_version ?? 0) + 1;
            $values = [
                // A previously unsubscribed address stays suppressed until fresh confirmation.
                'status' => $row?->status ?? 'pending', 'token_version' => $version,
                'confirmation_token_hash' => hash('sha256', $confirmationToken),
                'confirmation_expires_at' => $now->copy()->addHours(config('newsletter.confirmation_hours')),
                'consent_version' => config('newsletter.consent_version'), 'consent_requested_at' => $now,
                'last_confirmation_queued_at' => $now, 'confirmation_day' => $now->toDateString(),
                'confirmations_today' => $todayCount + 1, 'updated_at' => $now,
            ];
            if ($row) {
                DB::table('newsletter_subscribers')->where('id', $id)->update($values);
            } else {
                DB::table('newsletter_subscribers')->insert($values + ['id' => $id, 'email' => $email,
                    'unsubscribe_token_hash' => hash('sha256', $unsubscribeToken),
                    'unsubscribe_token_encrypted' => Crypt::encryptString($unsubscribeToken), 'created_at' => $now]);
            }
            DB::table('newsletter_outbox')->where('subscriber_id', $id)->whereIn('status', ['queued', 'uncertain'])
                ->update(['status' => 'superseded', 'updated_at' => $now]);
            $base = rtrim(config('app.url'), '/').'/api/newsletter/';
            $payload = ['confirmation_url' => $base.'confirm/'.$id.'/'.$confirmationToken,
                'unsubscribe_url' => $base.'unsubscribe/'.$id.'/'.$unsubscribeToken];
            DB::table('newsletter_outbox')->insert(['id' => (string) Str::uuid(), 'subscriber_id' => $id,
                'token_version' => $version, 'template_id' => 'locs_newsletter_confirmation', 'template_version' => 1,
                'payload_encrypted' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)),
                'status' => 'queued', 'created_at' => $now, 'updated_at' => $now]);
            return self::RESPONSE;
        });
    }

    public function linkDetails(string $id, string $token, string $action): object
    {
        abort_unless(Str::isUuid($id) && preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        $row = DB::table('newsletter_subscribers')->where('id', $id)->first();
        $hash = $action === 'confirm' ? $row?->confirmation_token_hash : $row?->unsubscribe_token_hash;
        abort_unless($hash && hash_equals($hash, hash('sha256', $token)), 404);
        if ($action === 'confirm') {
            abort_unless($row->confirmation_expires_at && CarbonImmutable::parse($row->confirmation_expires_at)->isFuture(), 410, 'confirmation_expired');
        }
        return $row;
    }

    public function confirm(string $id, string $token): void
    {
        $this->locked(function () use ($id, $token) {
            $this->linkDetails($id, $token, 'confirm');
            DB::table('newsletter_subscribers')->where('id', $id)->update(['status' => 'subscribed',
                'confirmed_at' => now(), 'unsubscribed_at' => null, 'confirmation_token_hash' => null,
                'confirmation_expires_at' => null, 'updated_at' => now()]);
            $this->suppressQueued($id);
        });
    }

    public function unsubscribe(string $id, string $token): void
    {
        $this->locked(function () use ($id, $token) {
            $row = $this->linkDetails($id, $token, 'unsubscribe');
            DB::table('newsletter_subscribers')->where('id', $id)->update(['status' => 'unsubscribed',
                'unsubscribed_at' => $row->unsubscribed_at ?? now(), 'confirmation_token_hash' => null,
                'confirmation_expires_at' => null, 'updated_at' => now()]);
            $this->suppressQueued($id);
        });
    }

    private function suppressQueued(string $id): void
    {
        DB::table('newsletter_outbox')->where('subscriber_id', $id)->whereIn('status', ['queued', 'uncertain'])
            ->update(['status' => 'superseded', 'updated_at' => now()]);
    }

    public function ownerSnapshot(): array
    {
        $counts = ['pending' => 0, 'subscribed' => 0, 'unsubscribed' => 0];
        foreach (DB::table('newsletter_subscribers')->selectRaw('status, count(*) as total')->groupBy('status')->get() as $row) {
            if (array_key_exists($row->status, $counts)) $counts[$row->status] = (int) $row->total;
        }
        return ['counts' => $counts,
            'subscribers' => DB::table('newsletter_subscribers')->orderByDesc('created_at')->orderBy('id')->limit(100)
                ->get(['id', 'email', 'status', 'consent_requested_at', 'confirmed_at', 'unsubscribed_at']),
            'has_more' => array_sum($counts) > 100,
            'delivery' => ['queued' => DB::table('newsletter_outbox')->where('status', 'queued')->count(),
                'uncertain' => DB::table('newsletter_outbox')->where('status', 'uncertain')->count()]];
    }
}
