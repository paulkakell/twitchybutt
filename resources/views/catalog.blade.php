@extends('layout')
@section('title', 'Explore')
@section('content')
<section class="hero"><p class="eyebrow">A direct connection</p><h1>Independent work.<br>Your own audience.</h1><p class="lede">Read the latest work from this creator. Choose free posts or explore members-only content.</p></section>
<div class="section-title"><h2>Latest posts</h2><span>Published on this site</span></div>
<div class="cards">
@forelse($posts as $post)
    <article class="card"><span class="badge">{{ $post->price_units === 0 ? 'Open access' : 'Paid access' }}</span><h3><a href="/posts/{{ $post->id }}">{{ $post->title }}</a></h3><p>{{ $post->price_units === 0 ? 'Free to read' : App\Services\TokenAmount::format($post->price_units).' TEST' }}</p><a class="read-link" href="/posts/{{ $post->id }}">View post <span aria-hidden="true">&rarr;</span></a></article>
@empty
    <div class="empty"><h3>The first post is still being prepared.</h3><p>Published general-content posts will appear here. Drafts and restricted content are private.</p>@can('manage-content')<a class="button" href="/studio/posts/new">Create your first post</a>@endcan</div>
@endforelse
</div>
@include('pagination', ['page' => $posts])
@endsection
