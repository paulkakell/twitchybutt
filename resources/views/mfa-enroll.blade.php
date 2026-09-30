@extends('layout')
@section('title', 'Enroll an authenticator')
@section('content')
<section class="narrow"><h1>Enroll your authenticator</h1>
<p>Add a time-based entry to your authenticator app using this setup key. Select six digits and a 30-second interval. This key is never sent to a QR-code service.</p>
<p class="mono">{{ $secret }}</p><p>Enrollment expires in ten minutes. Keep the key private. Restart enrollment if you leave this page.</p>
<form action="/account/mfa/confirm" method="post" class="panel stack">@csrf
<label for="password">Current password</label><input type="password" name="password" id="password" autocomplete="current-password" required>
<label for="code">Authenticator code</label><input name="code" id="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
<button class="button">Confirm MFA</button></form></section>
@endsection
