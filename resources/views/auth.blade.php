@extends('layout')
@section('title', $mode === 'register' ? 'Join this site' : 'Sign in')
@section('content')
<section class="narrow"><p class="eyebrow">Your creator connection</p><h1>{{ $mode === 'register' ? 'Join this site' : 'Welcome back' }}</h1><p class="muted">Your account belongs to this independently operated site.</p>
<form action="/{{ $mode }}" method="post" class="panel stack">@csrf
@if($mode === 'register')<label for="name">Display name</label><input id="name" name="name" value="{{ old('name') }}" maxlength="80" autocomplete="nickname" required>@endif
<label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required>
<label for="password">Password</label><input id="password" name="password" type="password" maxlength="72" autocomplete="{{ $mode === 'register' ? 'new-password' : 'current-password' }}" required>
@if($mode === 'register')<p class="hint">Use at least 12 characters with letters and numbers, and at most 72 UTF-8 bytes. Do not reuse a password.</p><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" maxlength="72" autocomplete="new-password" required>@endif
<button class="button">{{ $mode === 'register' ? 'Create account' : 'Sign in' }}</button>
</form><p class="hint">{{ $mode === 'register' ? 'Already registered?' : 'New here?' }} <a href="{{ $mode === 'register' ? '/login' : '/register' }}">{{ $mode === 'register' ? 'Sign in' : 'Create an account' }}</a></p>
@if($mode === 'login')<p><a href="/forgot-password">Forgot your password?</a></p>@endif</section>
@endsection
