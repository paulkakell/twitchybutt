@extends('layout')
@section('title', 'Account security')
@section('content')
<section class="narrow"><p class="eyebrow">Account security</p><h1>Email and recovery</h1>
<p>Email: {{ auth()->user()->email }}</p><p>Status: {{ auth()->user()->hasVerifiedEmail() ? 'Verified' : 'Not verified' }}</p>
<p class="muted">Email verification confirms access to your inbox. It does not verify age or identity and does not change your account role.</p>
@if(config('account_security.mail_enabled'))
@if(!auth()->user()->hasVerifiedEmail())<form method="post" action="/email/verification-notification" class="panel stack">@csrf<button class="button">Send verification email</button><p class="hint">Sign in to this account before opening the link. Links expire after 60 minutes.</p></form>@endif
<p><a href="/forgot-password">Request a password reset</a></p><p class="hint">A completed password reset signs out this account's existing sessions. You will need to sign in again.</p>
@else<p role="status">Account email has not been enabled by this site operator. Verification and password recovery are unavailable.</p>@endif
<p><a href="/account">Return to your account</a></p></section>
<p><a href="/account/mfa">Multi-factor authentication</a> · <a href="/account/sessions">Active sessions</a></p>
@endsection
