@extends('layout')
@section('title', 'Reports')
@section('content')
<a href="/studio">&larr; Studio</a><h1>Content reports</h1><div class="notice">Prototype intake only. No deadline automation, verification, notifications, or case-resolution workflow is implemented.</div>
@forelse($reports as $report)<article class="panel report"><p class="eyebrow">{{ $report->category }} / {{ $report->created_at }} UTC</p><h2>Report #{{ $report->id }}</h2><p class="mono">{{ $report->reference }}</p><div class="post-body">{{ $report->description }}</div></article>@empty<p>No reports received.</p>@endforelse
@include('pagination', ['page' => $reports])
@endsection
