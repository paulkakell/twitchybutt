@extends('layout')
@section('title', 'Active sessions')
@section('content')
<h1>Active sessions</h1><p>Only your unexpired sessions are shown. Revocation takes effect on the next request. Already-running responses cannot be recalled. Times use UTC.</p>
<div class="table-wrap"><table><thead><tr><th>Session</th><th>Started</th><th>Last active</th><th>Action</th></tr></thead><tbody>
@foreach($sessions as $session)<tr><td>{{ $session->id === $current ? 'This session' : 'Other session' }}</td><td>{{ $session->created_at }}</td><td>{{ $session->last_seen_at }}</td><td>
<form action="/account/sessions/{{ $session->id }}/revoke" method="post">@csrf<label for="password-{{ $session->id }}">Current password</label><input id="password-{{ $session->id }}" name="password" type="password" autocomplete="current-password" required><button class="button small">{{ $session->id === $current ? 'Sign out here' : 'Revoke session' }}</button></form>
</td></tr>@endforeach</tbody></table></div>
@include('pagination', ['page' => $sessions])
<p><a href="/account/security">Account security</a></p>
<form method="post" action="/account/sessions/revoke-all" class="panel stack">@csrf
<h2>Sign out everywhere</h2><p>This includes this browser. It does not disable MFA.</p>
<label for="all-password">Current password</label><input type="password" id="all-password" name="password" autocomplete="current-password" maxlength="72" required>
<button class="button">Revoke all sessions</button></form>
@endsection
