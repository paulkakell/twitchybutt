@extends('layout')
@section('title', 'Report content')
@section('content')
<section class="narrow"><p class="eyebrow">Site safety</p><h1>Report content</h1><p>You do not need an account. This report is stored for this site's operator, not sent to a central content platform.</p>
<form class="panel stack" action="/report" method="post">@csrf
<label for="reference">Post URL or reference</label><input id="reference" name="reference" maxlength="300" value="{{ old('reference') }}" required>
<label for="category">Reason</label><select id="category" name="category">@foreach(['rights' => 'Copyright or other rights', 'consent' => 'Consent concern', 'safety' => 'Safety concern', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>@endforeach</select>
<label for="description">What should the operator review?</label><textarea id="description" name="description" rows="7" minlength="10" maxlength="10000" required>{{ old('description') }}</textarea><p class="hint">Do not upload or paste identity documents, payment keys, or illegal material. There are no attachment uploads. This prototype inbox does not provide emergency response or a complete statutory notice workflow.</p><button class="button">Submit report</button></form></section>
@endsection
