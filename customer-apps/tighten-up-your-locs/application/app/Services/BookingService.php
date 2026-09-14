<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** The dedicated Locs database is the only booking authority. No provider calls occur in transactions. */
class BookingService
{
    public const SITE_KEY = 'site-dffd4cb9c3aa47fd';

    public function requests(): array
    {
        return DB::table('booking_requests')->orderByDesc('created_at')->limit(500)->get()
            ->map(fn ($row) => $this->requestView($row))->all();
    }

    public function appointments(): array
    {
        return DB::table('appointments')->orderBy('starts_at')->limit(1000)->get()
            ->map(fn ($row) => $this->appointmentView($row))->all();
    }

    public function openings(bool $public = false): array
    {
        $query = DB::table('openings')->orderBy('starts_at');
        if ($public) {
            $query->where('published', true)->where('ends_at', '>', time());
        }
        return $query->limit($public ? 12 : 500)->get()->map(fn ($row) => $this->openingView($row))->all();
    }

    public function hasMore(string $collection): bool
    {
        return match ($collection) {
            'requests' => DB::table('booking_requests')->count() > 500,
            'appointments' => DB::table('appointments')->count() > 1000,
            'openings' => DB::table('openings')->count() > 500,
        };
    }

    public function receive(array $data): array
    {
        $payload = array_intersect_key($data, array_flip(['name', 'email', 'phone', 'service_key', 'requested_window', 'message', 'source']));
        foreach ($payload as $key => $value) {
            $payload[$key] = trim((string) $value);
        }
        $payload['email'] = strtolower($payload['email']);
        $payload += ['phone' => '', 'message' => '', 'source' => 'tighten-up-your-locs-site'];
        $hash = $this->hash($payload);
        $key = $data['idempotency_key'] ?? 'body:'.hash_hmac('sha256', $hash, (string) config('app.key'));

        return $this->locked(function () use ($payload, $hash, $key) {
            $existing = DB::table('booking_requests')->where('idempotency_key', $key)->first();
            if ($existing) {
                $this->sameHash($existing->payload_hash, $hash);
                return ['ok' => true, 'status' => 'received', 'reference' => $existing->id];
            }
            $id = (string) Str::uuid();
            DB::table('booking_requests')->insert($payload + [
                'id' => $id, 'idempotency_key' => $key, 'payload_hash' => $hash,
                'status' => 'received', 'version' => 1, 'consent_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $result = ['ok' => true, 'status' => 'received', 'reference' => $id];
            $event = $this->event('intake:'.$key, $hash, $id, 'request_received', null, $result);
            $this->notify($event, (string) config('locs.owner_email'), 'request_received', [
                'request_id' => $id, 'admin_url' => url('/admin/'),
            ]);
            return $result;
        });
    }

    public function command(array $data, int $actor): array
    {
        $hash = $this->hash($data + ['actor_id' => $actor]);
        return $this->locked(function () use ($data, $actor, $hash) {
            $eventKey = 'appointment:'.$actor.':'.hash('sha256', $data['idempotency_key']);
            if ($replay = $this->replay($eventKey, $hash)) {
                return $replay;
            }
            $action = $data['action'];
            $token = null;
            $appointment = isset($data['appointment_id'])
                ? DB::table('appointments')->where('id', $data['appointment_id'])->first() : null;
            if (isset($data['appointment_id']) && !$appointment) {
                $this->fail('appointment_not_found', 404);
            }
            if ($appointment) {
                $this->version($appointment, $data['expected_version']);
                $request = DB::table('booking_requests')->where('id', $appointment->request_id)->first();
                $values = [];
                if ($action === 'confirm' && $appointment->status === 'proposed') {
                    $this->interval($appointment->starts_at, $appointment->ends_at);
                    $this->conflict($appointment->starts_at, $appointment->ends_at, $appointment->id);
                    $values = ['status' => 'confirmed'] + $this->clearResponse();
                } elseif ($action === 'reschedule' && $appointment->status === 'confirmed') {
                    $this->interval($data['starts_at'], $data['ends_at']);
                    $this->conflict($data['starts_at'], $data['ends_at'], $appointment->id);
                    $token = bin2hex(random_bytes(32));
                    $values = ['pending_starts_at' => $data['starts_at'], 'pending_ends_at' => $data['ends_at']]
                        + $this->responseFields($token, 'reschedule');
                } elseif ($action === 'cancel' && in_array($appointment->status, ['proposed', 'confirmed'], true)) {
                    $values = ['status' => 'cancelled'] + $this->clearResponse();
                } elseif ($action === 'complete' && $appointment->status === 'confirmed' && $appointment->starts_at <= time()) {
                    $values = ['status' => 'completed'] + $this->clearResponse();
                } else {
                    $this->fail('invalid_transition');
                }
                DB::table('appointments')->where('id', $appointment->id)->update($values + [
                    'version' => $appointment->version + 1, 'updated_at' => now(),
                ]);
                $id = $appointment->id;
            } else {
                if (!in_array($action, ['confirm', 'propose'], true) || empty($data['request_id'])) {
                    $this->fail('invalid_transition');
                }
                $request = DB::table('booking_requests')->where('id', $data['request_id'])->first();
                if (!$request) {
                    $this->fail('request_not_found', 404);
                }
                $this->version($request, $data['expected_version']);
                if (DB::table('appointments')->where('request_id', $request->id)->exists()) {
                    $this->fail('request_already_scheduled');
                }
                $this->interval($data['starts_at'], $data['ends_at']);
                $this->conflict($data['starts_at'], $data['ends_at']);
                $id = (string) Str::uuid();
                $values = [];
                if ($action === 'propose') {
                    $token = bin2hex(random_bytes(32));
                    $values = $this->responseFields($token, 'proposal');
                }
                DB::table('appointments')->insert($values + [
                    'id' => $id, 'request_id' => $request->id,
                    'status' => $action === 'confirm' ? 'confirmed' : 'proposed',
                    'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'],
                    'version' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('booking_requests')->where('id', $request->id)->update([
                    'status' => 'responded', 'version' => $request->version + 1, 'updated_at' => now(),
                ]);
            }
            // Older unsent proposals cannot be delivered after their token has been revoked.
            $this->supersede($id);
            $result = ['ok' => true, 'appointment' => $this->appointmentView(DB::table('appointments')->where('id', $id)->first()), 'notification_status' => 'queued'];
            $result['appointment']['notification_status'] = 'queued';
            $event = $this->event($eventKey, $hash, $id, $action, $actor, $result);
            $this->notify($event, $request->email, 'appointment_'.$action, $this->notificationPayload($id, $token));
            return $result;
        });
    }

    public function openingCommand(array $data, int $actor): array
    {
        $hash = $this->hash($data + ['actor_id' => $actor]);
        return $this->locked(function () use ($data, $actor, $hash) {
            $eventKey = 'opening:'.$actor.':'.hash('sha256', $data['idempotency_key']);
            if ($replay = $this->replay($eventKey, $hash)) {
                return $replay;
            }
            $row = $data['action'] === 'create' ? null : DB::table('openings')->where('id', $data['id'])->first();
            if ($data['action'] !== 'create') {
                if (!$row) {
                    $this->fail('opening_not_found', 404);
                }
                $this->version($row, $data['expected_version']);
            }
            $id = $row?->id ?? (string) Str::uuid();
            if ($data['action'] === 'delete') {
                DB::table('openings')->where('id', $id)->delete();
                $result = ['ok' => true, 'opening' => null, 'deleted_id' => $id];
            } else {
                $this->interval($data['starts_at'], $data['ends_at']);
                $values = [
                    'label' => trim($data['label']), 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'],
                    'published' => (bool) $data['published'], 'version' => ($row?->version ?? 0) + 1, 'updated_at' => now(),
                ];
                if ($row) {
                    DB::table('openings')->where('id', $id)->update($values);
                } else {
                    DB::table('openings')->insert($values + ['id' => $id, 'created_at' => now()]);
                }
                $result = ['ok' => true, 'opening' => $this->openingView(DB::table('openings')->where('id', $id)->first())];
            }
            $this->event($eventKey, $hash, $id, 'opening_'.$data['action'], $actor, $result);
            return $result;
        });
    }

    /** GET does not consume the token or expose contact information. */
    public function responseDetails(string $id, string $token): array
    {
        $row = $this->tokenAppointment($id, $token);
        return ['id' => $row->id, 'status' => $row->status, 'kind' => $row->response_kind,
            'starts_at' => (int) ($row->pending_starts_at ?? $row->starts_at),
            'ends_at' => (int) ($row->pending_ends_at ?? $row->ends_at)];
    }

    public function respond(string $id, string $token, string $decision): array
    {
        if (!in_array($decision, ['accept', 'decline'], true)) {
            $this->fail('invalid_transition', 422);
        }
        $key = 'response:'.hash('sha256', $id.$token);
        $hash = $this->hash(['decision' => $decision]);
        return $this->locked(function () use ($id, $token, $decision, $key, $hash) {
            // A later cancellation or replacement revokes even a formerly valid replay link.
            $row = $this->tokenAppointment($id, $token);
            if ($replay = $this->replay($key, $hash)) {
                return $replay;
            }
            if (!$row->response_kind || !in_array($row->status, ['proposed', 'confirmed'], true)) {
                $this->fail('response_already_recorded');
            }
            $values = $this->clearResponse(false);
            if ($decision === 'accept') {
                $start = $row->pending_starts_at ?? $row->starts_at;
                $end = $row->pending_ends_at ?? $row->ends_at;
                $this->interval($start, $end);
                $this->conflict($start, $end, $id);
                $values += ['status' => 'confirmed', 'starts_at' => $start, 'ends_at' => $end];
            } elseif ($row->status === 'proposed') {
                $values += ['status' => 'cancelled'];
            }
            // Declining a reschedule leaves the confirmed original reservation unchanged.
            DB::table('appointments')->where('id', $id)->update($values + ['version' => $row->version + 1, 'updated_at' => now()]);
            $this->supersede($id);
            $result = ['ok' => true, 'status' => $decision === 'accept' ? 'confirmed' : 'declined'];
            $event = $this->event($key, $hash, $id, 'customer_'.$decision, null, $result);
            $request = DB::table('booking_requests')->where('id', $row->request_id)->first();
            $payload = $this->notificationPayload($id);
            $this->notify($event, $request->email, 'appointment_customer_'.$decision, $payload);
            $this->notify($event, (string) config('locs.owner_email'), 'appointment_customer_'.$decision, $payload);
            return $result;
        });
    }

    private function locked(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            // Write first avoids SQLite read-to-write upgrade deadlocks. The same statement
            // obtains MySQL's exclusive row lock before any reservation state is read.
            $changed = DB::table('booking_resource_locks')->where('id', 1)->increment('lock_version');
            if ($changed !== 1) {
                throw new \RuntimeException('Booking resource authority is not initialized.');
            }
            DB::table('booking_resource_locks')->where('id', 1)->lockForUpdate()->first();
            return $callback();
        }, 5);
    }

    private function interval(int $start, int $end): void
    {
        if ($start <= time() || $end <= $start || $end - $start > 86400 || $start > time() + 366 * 86400) {
            throw ValidationException::withMessages(['starts_at' => 'Choose a future appointment within one year, lasting no more than 24 hours.']);
        }
    }

    private function conflict(int $start, int $end, ?string $except = null): void
    {
        $query = DB::table('appointments')->where('status', 'confirmed')
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start);
        if ($except) {
            $query->where('id', '!=', $except);
        }
        if ($query->exists()) {
            $this->fail('time_conflict');
        }
    }

    private function version(object $row, int $version): void
    {
        if ((int) $row->version !== $version) {
            $this->fail('stale_version');
        }
    }

    private function replay(string $key, string $hash): ?array
    {
        $event = DB::table('booking_events')->where('idempotency_key', $key)->first();
        if (!$event) {
            return null;
        }
        $this->sameHash($event->payload_hash, $hash);
        return json_decode($event->result, true, flags: JSON_THROW_ON_ERROR);
    }

    private function sameHash(string $old, string $new): void
    {
        if (!hash_equals($old, $new)) {
            $this->fail('idempotency_conflict');
        }
    }

    private function event(string $key, string $hash, string $entity, string $action, ?int $actor, array $result): string
    {
        $id = (string) Str::uuid();
        DB::table('booking_events')->insert(['id' => $id, 'idempotency_key' => $key,
            'payload_hash' => $hash, 'entity_id' => $entity, 'action' => $action,
            'actor_id' => $actor, 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => now()]);
        return $id;
    }

    private function notify(string $event, string $recipient, string $template, array $payload): void
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('A valid Locs notification recipient is required.');
        }
        DB::table('notification_outbox')->insert(['id' => (string) Str::uuid(), 'event_id' => $event,
            'recipient' => $recipient, 'template' => $template, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => 'queued', 'attempts' => 0, 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function supersede(string $id): void
    {
        $events = DB::table('booking_events')->where('entity_id', $id)->pluck('id');
        DB::table('notification_outbox')->whereIn('event_id', $events)->whereIn('status', ['queued', 'failed'])
            ->update(['status' => 'superseded', 'updated_at' => now()]);
    }

    private function notificationPayload(string $id, ?string $token = null): array
    {
        $row = DB::table('appointments')->where('id', $id)->first();
        $payload = ['appointment_id' => $id, 'version' => (int) $row->version, 'status' => $row->status,
            'starts_at' => (int) $row->starts_at, 'ends_at' => (int) $row->ends_at,
            'pending_starts_at' => $row->pending_starts_at ? (int) $row->pending_starts_at : null,
            'pending_ends_at' => $row->pending_ends_at ? (int) $row->pending_ends_at : null,
            'time_zone' => 'America/New_York'];
        if ($token) {
            $payload['response_url'] = url('/appointment/'.$id.'/'.$token);
        }
        return $payload;
    }

    private function tokenAppointment(string $id, string $token): object
    {
        $row = DB::table('appointments')->where('id', $id)->first();
        if (!$row || !preg_match('/^[a-f0-9]{64}$/', $token) || !$row->response_token_hash
            || !hash_equals($row->response_token_hash, hash('sha256', $token)) || $row->response_expires_at < time()) {
            $this->fail('response_link_unavailable', 404);
        }
        return $row;
    }

    private function responseFields(string $token, string $kind): array
    {
        return ['response_token_hash' => hash('sha256', $token), 'response_kind' => $kind,
            'response_expires_at' => time() + 7 * 86400];
    }

    private function clearResponse(bool $revoke = true): array
    {
        $values = ['pending_starts_at' => null, 'pending_ends_at' => null, 'response_kind' => null];
        return $revoke ? $values + ['response_token_hash' => null, 'response_expires_at' => null] : $values;
    }

    private function requestView(object $row): array
    {
        $data = array_intersect_key((array) $row, array_flip(['id', 'name', 'email', 'phone', 'service_key',
            'requested_window', 'message', 'status', 'version', 'created_at']));
        $data['version'] = (int) $data['version'];
        $data['created_at'] = CarbonImmutable::parse($row->created_at, config('app.timezone', 'UTC'))->toIso8601String();
        return $data;
    }

    private function appointmentView(object $row): array
    {
        $request = DB::table('booking_requests')->where('id', $row->request_id)->first();
        $notification = DB::table('notification_outbox')->where('payload->appointment_id', $row->id)
            ->where('payload->version', (int) $row->version)->where('recipient', $request->email)
            ->whereNot('status', 'superseded')->orderByDesc('created_at')->first();
        $data = array_intersect_key((array) $row, array_flip(['id', 'request_id', 'status', 'starts_at', 'ends_at',
            'pending_starts_at', 'pending_ends_at', 'version']));
        foreach (['starts_at', 'ends_at', 'pending_starts_at', 'pending_ends_at', 'version'] as $key) {
            $data[$key] = $data[$key] === null ? null : (int) $data[$key];
        }
        return $data + ['name' => $request->name, 'email' => $request->email, 'service_key' => $request->service_key,
            'notification_status' => $notification?->status ?? 'not_queued'];
    }

    private function openingView(object $row): array
    {
        return ['id' => $row->id, 'label' => $row->label, 'starts_at' => (int) $row->starts_at,
            'ends_at' => (int) $row->ends_at, 'published' => (bool) $row->published, 'version' => (int) $row->version];
    }

    private function hash(array $data): string
    {
        ksort($data);
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function fail(string $code, int $status = 409): never
    {
        throw new HttpException($status, $code);
    }
}
