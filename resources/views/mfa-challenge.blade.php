@extends('layout')
@section('title', 'Verify your sign-in')
@section('content')
<section class="narrow"><h1>Verify your sign-in</h1><form action="/account/mfa/challenge" method="post" class="panel stack">@csrf
<label for="code">Authenticator or recovery code</label><input id="code" name="code" autocomplete="one-time-code" maxlength="35" required>
<label><input type="checkbox" name="recovery" value="1"> I am using a recovery code</label>
<button class="button">Verify</button></form><p>Already-used authenticator codes are rejected. Wait for the next code. Password recovery does not disable MFA.</p></section>
@endsection
