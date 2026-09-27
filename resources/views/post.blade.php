@extends('layout')
@section('title', $post->title)
@section('content')
<article class="reading"><a href="/">&larr; All posts</a><p class="eyebrow">{{ $post->classification }} / {{ $post->status }}</p><h1>{{ $post->title }}</h1>
@if($canRead)<div class="post-body">{{ $post->body }}</div>
@else
<section class="panel paywall"><span class="badge">Paid content</span><h2>This post is locked.</h2><p>{{ App\Services\TokenAmount::format($post->price_units) }} TEST</p><p>Checkout is not active. Creating a test invoice does not charge a wallet or grant access.</p>
@auth<form action="/posts/{{ $post->id }}/invoices" method="post">@csrf<input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}"><button class="button">Create test invoice</button></form>@else<a class="button" href="/login">Sign in to preview an invoice</a>@endauth
</section>
@endif
@can('manage-content')<p><a href="/studio/posts/{{ $post->id }}/edit">Edit this post</a></p>@endcan
</article>
@endsection
