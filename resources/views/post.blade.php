@extends('layout')
@section('title', $post->title)
@section('content')
<article class="reading"><a href="/">&larr; All posts</a><p class="eyebrow">{{ $post->classification }} / {{ $post->status }}</p><h1>{{ $post->title }}</h1>
@if($canRead)<div class="post-body">{{ $post->body }}</div>
@foreach($assets as $asset)
<figure>
@if($asset->kind === 'image')<img src="{{ $mediaLinks->url($asset, $post, 'content') }}" alt="{{ $asset->alt_text }}" loading="lazy" class="post-media">
@else<video controls preload="none" poster="{{ $mediaLinks->url($asset, $post, 'thumbnail') }}" aria-label="{{ $asset->alt_text }}" class="post-media"><source src="{{ $mediaLinks->url($asset, $post, 'content') }}" type="video/mp4">Your browser does not support this video format.</video>@endif
<figcaption>{{ $asset->alt_text }}</figcaption></figure>
@endforeach
@if($assets->isNotEmpty())<p class="hint">Media links last five minutes. Reload the page to resume if a link expires.</p>@endif
@else
<section class="panel paywall"><span class="badge">Paid content</span><h2>This post is locked.</h2><p>{{ App\Services\TokenAmount::format($post->price_units) }} TEST</p><p>Checkout is not active. Creating a test invoice does not charge a wallet or grant access.</p>
@auth<form action="/posts/{{ $post->id }}/invoices" method="post">@csrf<input type="hidden" name="idempotency_key" value="{{ Illuminate\Support\Str::uuid() }}"><button class="button">Create test invoice</button></form>@else<a class="button" href="/login">Sign in to preview an invoice</a>@endauth
</section>
@endif
@can('manage-content')<p><a href="/studio/posts/{{ $post->id }}/edit">Edit this post</a></p>@endcan
</article>
@endsection
