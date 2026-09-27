@extends('layout')
@section('title', $post->exists ? 'Edit post' : 'New post')
@section('content')
<div class="page-heading"><div><a href="/studio">&larr; Studio</a><h1>{{ $post->exists ? 'Edit post' : 'New post' }}</h1></div></div>
<form class="panel stack editor" method="post" action="{{ $post->exists ? '/studio/posts/'.$post->id : '/studio/posts' }}">@csrf @if($post->exists)@method('PUT')@endif
<label for="title">Title</label><input id="title" name="title" maxlength="160" value="{{ old('title', $post->title) }}" required>
<label for="body">Post text</label><textarea id="body" name="body" rows="12" maxlength="50000" required>{{ old('body', $post->body) }}</textarea><p class="hint">Plain text only. HTML is displayed as text, not executed. Media uploads are not included in this release.</p>
<div class="form-grid"><div><label for="classification">Classification</label><select id="classification" name="classification">@foreach(['unclassified', 'general', 'restricted'] as $choice)<option value="{{ $choice }}" @selected(old('classification', $post->classification ?? 'unclassified') === $choice)>{{ ucfirst($choice) }}</option>@endforeach</select></div>
<div><label for="status">Publication</label><select id="status" name="status">@foreach(['draft', 'published'] as $choice)<option value="{{ $choice }}" @selected(old('status', $post->status ?? 'draft') === $choice)>{{ ucfirst($choice) }}</option>@endforeach</select></div>
<div><label for="price">Price in TEST</label><input id="price" name="price" inputmode="decimal" value="{{ old('price', App\Services\TokenAmount::format($post->price_units ?? 0)) }}" required></div></div>
<p class="hint">Zero is free. Paid prices range from 0.000050 to 1000000 TEST, with at most six decimal places. The 2% fee is rounded down to the smallest token unit. No real token is configured.</p>
<div class="notice">General is a content classification, not an exemption from applicable rules. Restricted and unclassified posts cannot be published.</div><button class="button">Save post</button>
</form>
@endsection
