<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Owner sign-in · Tighten Up Your Locs</title><link rel="stylesheet" href="/admin-assets/admin.css"></head>
<body class="auth-page"><main class="auth-card"><a href="/" class="wordmark">TIGHTEN UP<span>YOUR LOCS</span></a><p class="eyebrow">Your private business admin</p>
<h1>{{ $mode==='login' ? 'Welcome back, Shay.' : ($mode==='forgot' ? 'Set up or reset your password.' : 'Choose your password.') }}</h1>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="post" action="{{ $mode==='login'?'/admin/login':($mode==='forgot'?'/admin/forgot-password':'/admin/reset-password') }}">@csrf
@if($mode==='reset')<input type="hidden" name="token" value="{{ $token }}">@endif
<label class="field">Owner email<input name="email" type="email" autocomplete="username" required maxlength="254" value="{{ old('email',request('email','')) }}"></label>
@if($mode!=='forgot')<label class="field">Password<input name="password" type="password" autocomplete="{{ $mode==='reset'?'new-password':'current-password' }}" required @if($mode==='reset')minlength="12"@endif maxlength="1024"></label>@endif
@if($mode==='reset')<label class="field">Confirm password<input name="password_confirmation" type="password" autocomplete="new-password" required minlength="12" maxlength="1024"></label><p>Use at least 12 characters. This password is only for your Locs admin.</p>@endif
<button class="button" type="submit">{{ $mode==='login'?'Sign in':($mode==='forgot'?'Send private setup link':'Save password') }}</button></form>
@if($mode==='login')<p><a href="/admin/forgot-password">Set up or reset password</a></p>@else<p><a href="/admin/login">Back to sign-in</a></p>@endif
<p>Your appointments and records stay in the Tighten Up Your Locs system.</p></main></body></html>
