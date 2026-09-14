<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#efe6d4">
    <title>The Locs Letter · Your private admin</title>
    <link rel="stylesheet" href="/admin-assets/admin.css?v=20260914-1">
</head>
<body>
<a class="skip-link" href="#newsletter-main">Skip to your newsletter</a>
<header class="desk-header shell">
    <a class="wordmark" href="/" aria-label="Tighten Up Your Locs public website">TIGHTEN UP<span>YOUR LOCS</span></a>
    <div class="header-actions"><a class="quiet-link" href="/">View website</a><form method="POST" action="/admin/logout">@csrf<button class="button button-quiet" type="submit">Sign out</button></form></div>
</header>
<main id="newsletter-main" class="shell desk-main record-list" data-component-instance="locs.independent-newsletter-desk.v1">
    <div class="desk-intro"><div><p class="eyebrow">Tighten Up Your Locs · Private admin</p><h1>The Locs Letter.</h1><p class="intro-copy">Your readers. Their choice to stay connected.</p></div></div>
    <nav class="desk-toolbar" aria-label="Private admin navigation"><a class="button button-quiet" href="/admin">Back to booking desk</a><a class="button button-quiet" href="/admin/newsletter">Refresh list</a></nav>
    <section aria-labelledby="newsletter-counts-title">
        <div class="section-heading"><div><p class="eyebrow">Your growing community</p><h2 id="newsletter-counts-title">At a glance</h2></div><p>Only readers who confirm their email are subscribed. Appointment requests never join this list automatically.</p></div>
        <div class="panel"><dl class="record-details">
            <div><dt>Subscribed</dt><dd>{{ $counts['subscribed'] }}</dd></div>
            <div><dt>Awaiting confirmation</dt><dd>{{ $counts['pending'] }}</dd></div>
            <div><dt>Unsubscribed</dt><dd>{{ $counts['unsubscribed'] }}</dd></div>
        </dl><p class="section-note">{{ $delivery['queued'] }} confirmation {{ $delivery['queued'] === 1 ? 'email is' : 'emails are' }} queued. Queued means waiting to send, not delivered. This page is read-only; writing and sending newsletter campaigns is not included yet.</p></div>
    </section>
    @if($delivery['uncertain'] > 0)
        <p class="notice notice-error" role="status">{{ $delivery['uncertain'] }} confirmation {{ $delivery['uncertain'] === 1 ? 'email needs' : 'emails need' }} delivery review. The mail service did not return a certain outcome, so these messages are not automatically resent. Contact your website support before retrying.</p>
    @endif
    <section aria-labelledby="newsletter-readers-title">
        <div class="section-heading"><div><p class="eyebrow">Email signup records</p><h2 id="newsletter-readers-title">Your readers</h2></div><p>Dates below use Port St. Lucie time. Refresh to see the latest saved changes.</p></div>
        @if($has_more)<p class="notice">Showing the newest 100 signup records. The totals above include everyone. Older records remain saved but are not shown on this page.</p>@endif
        <div class="record-list">
            @forelse($subscribers as $subscriber)
                <article class="record-card">
                    <div class="record-header"><h3>{{ $subscriber->email }}</h3><span class="badge {{ $subscriber->status === 'subscribed' ? 'badge-confirmed' : ($subscriber->status === 'unsubscribed' ? 'badge-cancelled' : 'badge-proposed') }}">{{ $subscriber->status === 'subscribed' ? 'Subscribed' : ($subscriber->status === 'unsubscribed' ? 'Unsubscribed' : 'Awaiting confirmation') }}</span></div>
                    <dl class="record-details">
                        <div><dt>Signup requested</dt><dd>{{ \Carbon\CarbonImmutable::parse($subscriber->consent_requested_at)->setTimezone('America/New_York')->format('M j, Y · g:i a T') }}</dd></div>
                        @if($subscriber->confirmed_at)<div><dt>Email confirmed</dt><dd>{{ \Carbon\CarbonImmutable::parse($subscriber->confirmed_at)->setTimezone('America/New_York')->format('M j, Y · g:i a T') }}</dd></div>@endif
                        @if($subscriber->unsubscribed_at)<div><dt>Unsubscribed</dt><dd>{{ \Carbon\CarbonImmutable::parse($subscriber->unsubscribed_at)->setTimezone('America/New_York')->format('M j, Y · g:i a T') }}</dd></div>@endif
                    </dl>
                </article>
            @empty
                <div class="empty-state"><h3>A fresh page, ready for readers.</h3><p>No newsletter signups have been saved yet. New signups will appear here after someone requests The Locs Letter on your website.</p></div>
            @endforelse
        </div>
    </section>
</main>
<footer class="desk-footer shell"><span>© {{ date('Y') }} Tighten Up Your Locs. All rights reserved.</span><nav aria-label="Website policies"><a href="/privacy/">Privacy</a><a href="/terms/">Terms</a></nav></footer>
</body>
</html>
