<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>The Locs Letter · Tighten Up Your Locs</title><link rel="stylesheet" href="/admin-assets/admin.css"></head>
<body class="auth-page"><main class="auth-card"><a href="/" class="wordmark">TIGHTEN UP<span>YOUR LOCS</span></a><p class="eyebrow">The Locs Letter · From Shay</p>
@if($unavailable)
<h1>This link is unavailable.</h1><p>A confirmation link expires after 24 hours and works once. You can request a fresh one on our website.</p><p>For help leaving the list, contact <a href="mailto:hello@tightenupyourlocs.com">hello@tightenupyourlocs.com</a>.</p>
@elseif($saved)
<h1>{{ $action === 'confirm' ? 'You’re on the list.' : 'You’re off the list.' }}</h1><p>{{ $action === 'confirm' ? 'Your email is confirmed for loc-care notes and updates from Tighten Up Your Locs. You can unsubscribe at any time.' : 'Your newsletter subscription is stopped. This does not change any appointment you have with Shay.' }}</p>
@else
<h1>{{ $action === 'confirm' ? 'A little loc love, by email.' : 'Leave the newsletter?' }}</h1><p>{{ $action === 'confirm' ? 'Confirm that you would like loc-care notes, business news and updates from Tighten Up Your Locs.' : 'Choose below to stop newsletter emails. Your appointments stay unchanged.' }}</p>
<form method="post">@csrf<button class="button" type="submit">{{ $action === 'confirm' ? 'Confirm my subscription' : 'Unsubscribe' }}</button></form>
@endif
<p><a href="/">Back to Tighten Up Your Locs</a></p><p><a href="/privacy/">Privacy Policy</a></p></main></body></html>
