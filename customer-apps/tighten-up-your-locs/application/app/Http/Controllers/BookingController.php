<?php

namespace App\Http\Controllers;

use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $booking) {}

    public function requests(): JsonResponse
    {
        return $this->json(fn () => ['requests' => $this->booking->requests(), 'has_more' => $this->booking->hasMore('requests')]);
    }

    public function appointments(): JsonResponse
    {
        return $this->json(fn () => ['appointments' => $this->booking->appointments(), 'has_more' => $this->booking->hasMore('appointments')]);
    }

    public function openings(): JsonResponse
    {
        return $this->json(fn () => ['openings' => $this->booking->openings(), 'has_more' => $this->booking->hasMore('openings')]);
    }

    public function availability(string $siteKey): JsonResponse
    {
        abort_unless(hash_equals(BookingService::SITE_KEY, $siteKey), 404);
        return $this->json(fn () => ['ok' => true, 'site_key' => BookingService::SITE_KEY,
            'windows' => array_map(fn ($row) => array_intersect_key($row, array_flip(['label', 'starts_at', 'ends_at'])), $this->booking->openings(true))]);
    }

    public function receive(Request $request, string $siteKey): JsonResponse
    {
        abort_unless(hash_equals(BookingService::SITE_KEY, $siteKey), 404);
        abort_unless(config('locs.public_booking_enabled') === true, 503, 'Online booking requests are temporarily unavailable.');
        // Public requests have no session/CSRF token. Exact Origin and JSON are mandatory instead.
        $this->origin($request);
        abort_unless($request->isJson(), 415);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'service_key' => ['required', Rule::in(['retightening', 'consultation', 'establishment', 'question'])],
            'requested_window' => ['required', 'string', 'max:180'],
            'message' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'string', 'max:0'],
            'consent' => ['required', 'accepted'],
            'source' => ['sometimes', Rule::in(['tighten-up-your-locs-site'])],
            'idempotency_key' => ['sometimes', 'string', 'min:16', 'max:100', 'regex:/^[A-Za-z0-9:_-]+$/'],
        ]);
        return $this->json(fn () => $this->booking->receive($data));
    }

    public function command(Request $request): JsonResponse
    {
        $hasAppointment = $request->filled('appointment_id');
        $needsTime = $request->input('action') === 'reschedule' || (!$hasAppointment && in_array($request->input('action'), ['confirm', 'propose'], true));
        $data = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'propose', 'reschedule', 'cancel', 'complete'])],
            'appointment_id' => ['sometimes', 'nullable', 'uuid'],
            'request_id' => [Rule::requiredIf(!$hasAppointment), 'uuid'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:100', 'regex:/^[A-Za-z0-9:_-]+$/'],
            'starts_at' => [Rule::requiredIf($needsTime), 'integer', 'min:1'],
            'ends_at' => [Rule::requiredIf($needsTime), 'integer', 'min:1'],
        ]);
        return $this->json(fn () => $this->booking->command($data, (int) $request->user()->id));
    }

    public function openingCommand(Request $request): JsonResponse
    {
        $modifies = $request->input('action') !== 'create';
        $needsFields = $request->input('action') !== 'delete';
        $data = $request->validate([
            'action' => ['required', Rule::in(['create', 'update', 'delete'])],
            'id' => [Rule::requiredIf($modifies), 'uuid'],
            'expected_version' => [Rule::requiredIf($modifies), 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:100', 'regex:/^[A-Za-z0-9:_-]+$/'],
            'label' => [Rule::requiredIf($needsFields), 'string', 'max:100'],
            'starts_at' => [Rule::requiredIf($needsFields), 'integer', 'min:1'],
            'ends_at' => [Rule::requiredIf($needsFields), 'integer', 'min:1'],
            'published' => [Rule::requiredIf($needsFields), 'boolean'],
        ]);
        return $this->json(fn () => $this->booking->openingCommand($data, (int) $request->user()->id));
    }

    public function responsePage(Request $request, string $appointment, string $token)
    {
        $details = $this->booking->responseDetails($appointment, $token);
        $when = CarbonImmutable::createFromTimestamp($details['starts_at'])->setTimezone('America/New_York')->format('l, F j, Y · g:i a T');
        $end = CarbonImmutable::createFromTimestamp($details['ends_at'])->setTimezone('America/New_York')->format('g:i a T');
        $content = '<p>'.e($when).'–'.e($end).'</p>';
        if ($details['kind']) {
            $content .= '<p>This time is not reserved until your acceptance is saved. A proposed reschedule keeps your original appointment until you accept the replacement.</p>';
            $content .= '<form method="post"><input type="hidden" name="_token" value="'.e(csrf_token()).'"><button class="button" type="submit" name="decision" value="accept">Accept this time</button> <button class="button button-quiet" type="submit" name="decision" value="decline">Decline this time</button></form>';
        } else {
            $content .= '<p>Your response has already been recorded.</p>';
        }
        return $this->html('Your appointment', $content);
    }

    public function respond(Request $request, string $appointment, string $token)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['accept', 'decline'])]]);
        try {
            $result = $this->booking->respond($appointment, $token, $data['decision']);
            return $this->html('Response saved', '<p>'.($result['status'] === 'confirmed'
                ? 'Your appointment is confirmed. A confirmation email is queued.'
                : 'You declined the proposed time. If this was a reschedule, your original confirmed appointment remains unchanged.').'</p>');
        } catch (HttpExceptionInterface $error) {
            return $this->html('Unable to save your response', '<p>'.e($this->message($error->getMessage())).'</p><p>Please contact Shay to arrange a time.</p>', $error->getStatusCode());
        }
    }

    private function origin(Request $request): void
    {
        $configured = rtrim((string) config('app.url'), '/');
        $allowed = [$configured];
        if ($configured === 'https://tightenupyourlocs.com') {
            $allowed[] = 'https://www.tightenupyourlocs.com';
        }
        abort_unless(in_array($request->header('Origin'), $allowed, true), 403);
    }

    private function json(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback())->withHeaders(['Cache-Control' => 'no-store, private']);
        } catch (HttpExceptionInterface $error) {
            return response()->json(['ok' => false, 'code' => $error->getMessage(),
                'message' => $this->message($error->getMessage())], $error->getStatusCode())->withHeaders(['Cache-Control' => 'no-store, private']);
        }
    }

    private function html(string $title, string $content, int $status = 200)
    {
        return response('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>'.e($title).' | Tighten Up Your Locs</title><link rel="stylesheet" href="/admin-assets/admin.css"></head><body class="auth-page"><main class="auth-card"><p class="wordmark">Tighten Up Your Locs</p><h1>'.e($title).'</h1>'.$content.'<p><a href="mailto:hello@tightenupyourlocs.com">Contact Shay</a></p></main></body></html>', $status)
            ->withHeaders(['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    private function message(string $code): string
    {
        return match ($code) {
            'time_conflict' => 'That time is no longer available. The original reservation has not changed.',
            'stale_version' => 'This record changed. Refresh before trying again.',
            'idempotency_conflict' => 'This operation key was already used with different details. Refresh before trying again.',
            'invalid_transition' => 'That action is not available for the current appointment state.',
            'request_already_scheduled' => 'This request already has an appointment. Open that appointment to manage it.',
            'response_already_recorded' => 'A response was already recorded for this link.',
            'response_link_unavailable' => 'This response link is unavailable or expired.',
            default => 'The requested booking record was not found.',
        };
    }
}
