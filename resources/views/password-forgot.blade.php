@extends('layout')
@section('title', 'Password recovery')
@section('content')
<section class="narrow"><p class="eyebrow">Account recovery</p><h1>Forgot your password?</h1>
@if(config('account_security.mail_enabled'))
<p>Enter the email address for your account on this site. The response will not disclose whether an account exists.</p>
<form method="post" action="/forgot-password" class="panel stack">@csrf<label for="email">Account email</label><input id="email" type="email" name="email" maxlength="254" autocomplete="email" required><button class="button">Request recovery email</button></form>
@else<p role="status">Account email is unavailable. Contact this site operator for help.</p>@endif
<p><a href="/login">Return to sign in</a></p></section>
@endsection
