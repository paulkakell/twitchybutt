@extends('layout')
@section('title', 'Save recovery codes')
@section('content')
<section class="narrow"><h1>Save your recovery codes</h1><p>Store these codes offline or in a password manager. Each code works once in place of an authenticator code. They will not be displayed again.</p>
<div class="panel">@foreach($codes as $code)<p class="mono">{{ $code }}</p>@endforeach</div>
<p>Enrollment signs other sessions out. Password recovery does not remove MFA.</p><p><a class="button" href="/account/mfa">I have stored my codes</a></p></section>
@endsection
