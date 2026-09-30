@extends('layout')
@section('title', 'Reset your password')
@section('content')
<section class="narrow"><p class="eyebrow">Account recovery</p><h1>Choose a new password</h1>
@if(config('account_security.mail_enabled') && $token !== '')
<form method="post" action="/reset-password" class="panel stack">@csrf<input type="hidden" name="token" value="{{ $token }}">
<label for="email">Account email</label><input id="email" type="email" name="email" maxlength="254" autocomplete="email" required>
<label for="password">New password</label><input id="password" type="password" name="password" maxlength="72" autocomplete="new-password" required>
<p class="hint">Use at least 12 characters, with letters and numbers, and no more than 72 UTF-8 bytes. Do not reuse a password.</p>
<label for="password_confirmation">Confirm new password</label><input id="password_confirmation" type="password" name="password_confirmation" maxlength="72" autocomplete="new-password" required>
<button class="button">Reset password and sign out existing sessions</button></form>
@else<p role="status">This recovery link is unavailable or incomplete. Request a new link.</p>@endif
<p><a href="/forgot-password">Request another recovery email</a></p></section>
@endsection
