@extends('layout')
@section('title', 'Studio')
@section('content')
<div class="page-heading"><div><p class="eyebrow">Creator studio</p><h1>Your publishing desk</h1></div><a class="button" href="/studio/posts/new">New post</a></div>
<div class="notice">Payments are disabled. Restricted and unclassified posts can be saved as private drafts only.</div>
<div class="section-title"><h2>Posts</h2><a href="/studio/reports">Reports ({{ $reportCount }})</a></div>
<div class="table-wrap"><table><thead><tr><th>Title</th><th>Classification</th><th>Status</th><th>Price</th><th>Action</th></tr></thead><tbody>
@forelse($posts as $post)<tr><td><a href="/posts/{{ $post->id }}">{{ $post->title }}</a></td><td>{{ $post->classification }}</td><td>{{ $post->status }}</td><td>{{ App\Services\TokenAmount::format($post->price_units) }} TEST</td><td><a href="/studio/posts/{{ $post->id }}/edit">Edit</a></td></tr>@empty<tr><td colspan="5">No posts yet. Create a draft to begin.</td></tr>@endforelse
</tbody></table></div>@include('pagination', ['page' => $posts])
@endsection
