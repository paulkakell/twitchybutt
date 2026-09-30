@extends('layout')
@section('title', 'Reports')
@section('content')
<a href="/studio">&larr; Studio</a><h1>Content reports</h1><div class="notice">Operator case workflow only. Legal deadlines, provider verification, external notifications and qualified policy review remain separate release requirements.</div>
@forelse($reports as $report)<article class="panel report"><p class="eyebrow">{{ $report->category }} / {{ $report->status }} / {{ $report->created_at }} UTC</p><h2>Report #{{ $report->id }}</h2><p class="mono">{{ $report->reference }}</p><div class="post-body">{{ $report->description }}</div>
@if($report->operator_note)<p><strong>Operator note:</strong> {{ $report->operator_note }}</p>@endif
@if($report->status !== 'closed')
<form method="post" action="/studio/reports/{{ $report->id }}" class="stack">@csrf @method('put')
<label for="status-{{ $report->id }}">Case action</label><select id="status-{{ $report->id }}" name="status" required>
@foreach((['open'=>['reviewing','closed'],'reviewing'=>['removed','rejected','closed'],'removed'=>['appealed','closed'],'rejected'=>['appealed','closed'],'appealed'=>['reviewing','closed']][$report->status] ?? []) as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach
</select><label for="note-{{ $report->id }}">Operator note</label><textarea id="note-{{ $report->id }}" name="operator_note" maxlength="2000">{{ $report->operator_note }}</textarea><button class="button">Update case</button></form>
@endif</article>@empty<p>No reports received.</p>@endforelse
@include('pagination', ['page' => $reports])
@endsection
