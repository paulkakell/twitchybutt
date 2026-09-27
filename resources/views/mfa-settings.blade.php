@extends('layout')
@section('title', 'Multi-factor authentication')
@section('content')
<section class="narrow"><h1>Multi-factor authentication</h1>
<p>An authenticator app generates a six-digit code. MFA is required for administrator tools and optional for members.</p>
@if($enrolled)
<p>MFA is enabled. {{ $remaining }} recovery codes remain. Codes are shown only when issued.</p>
<form action="/account/mfa/recovery-codes" method="post" class="panel stack">@csrf
<label for="password">Current password</label><input type="password" id="password" name="password" autocomplete="current-password" required>
<label for="code">New authenticator code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
<p class="hint">Generate a replacement set to invalidate all previous recovery codes. Wait for a new code if the current code was just used.</p>
<button class="button">Replace recovery codes</button></form>
<form action="/account/mfa/replace" method="post" class="panel stack">@csrf
<h2>Replace your authenticator</h2><p>Sign in and complete MFA within the last five minutes, using your authenticator or a saved recovery code. Your current factor stays active until you confirm the replacement. Confirmation signs out other sessions and replaces your recovery codes.</p>
<label for="replace-password">Current password</label><input type="password" id="replace-password" name="password" autocomplete="current-password" required>
<button class="button">Replace authenticator</button></form>
@else
<form action="/account/mfa/enroll" method="post" class="panel stack">@csrf
<label for="password">Current password</label><input type="password" id="password" name="password" autocomplete="current-password" required>
<button class="button">Start MFA enrollment</button></form>
@endif
<p><a href="/account/security">Account security</a> · <a href="/account/sessions">Active sessions</a></p></section>
@endsection
