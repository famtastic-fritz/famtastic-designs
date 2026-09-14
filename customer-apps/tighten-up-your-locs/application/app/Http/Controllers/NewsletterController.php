<?php

namespace App\Http\Controllers;

use App\Services\NewsletterService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class NewsletterController extends Controller
{
    public function __construct(private readonly NewsletterService $newsletter) {}

    public function signup(Request $request)
    {
        abort_unless(config('newsletter.enabled') && config('locs.mail_enabled'), 503, 'Newsletter signup is temporarily unavailable.');
        abort_unless(!app()->environment('production') || config('mail.default') === 'smtp', 503);
        $allowed = [rtrim((string) config('app.url'), '/')];
        if ($allowed[0] === 'https://tightenupyourlocs.com') $allowed[] = 'https://www.tightenupyourlocs.com';
        abort_unless(in_array($request->header('Origin'), $allowed, true), 403);
        abort_unless($request->isJson(), 415);
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:254'],
            'consent' => ['required', 'boolean'], 'website' => ['nullable', 'string', 'max:0']]);
        // Only an actual JSON boolean is accepted, not truthy strings or integers.
        abort_unless(($data['consent'] ?? null) === true, 422, 'Please choose newsletter signup consent.');
        return response()->json($this->newsletter->signup($data['email']));
    }

    public function show(string $action, string $subscriber, string $token)
    {
        try {
            $this->newsletter->linkDetails($subscriber, $token, $action);
            return response()->view('newsletter.link', ['action' => $action, 'saved' => false, 'unavailable' => false]);
        } catch (HttpExceptionInterface $error) {
            return response()->view('newsletter.link', ['action' => $action, 'saved' => false, 'unavailable' => true], $error->getStatusCode());
        }
    }

    public function respond(string $action, string $subscriber, string $token)
    {
        try {
            if ($action === 'confirm') $this->newsletter->confirm($subscriber, $token);
            else $this->newsletter->unsubscribe($subscriber, $token);
            return response()->view('newsletter.link', ['action' => $action, 'saved' => true, 'unavailable' => false]);
        } catch (HttpExceptionInterface $error) {
            return response()->view('newsletter.link', ['action' => $action, 'saved' => false, 'unavailable' => true], $error->getStatusCode());
        }
    }

    public function subscribers()
    {
        return response()->json($this->newsletter->ownerSnapshot());
    }

    public function ownerPage()
    {
        return response()->view('newsletter.owner', $this->newsletter->ownerSnapshot());
    }
}
