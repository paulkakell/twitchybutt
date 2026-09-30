@extends('layout')
@section('title', 'Manage private media')
@section('content')
<h1>Media for {{ $post->title }}</h1><p><a href="/studio/posts/{{ $post->id }}/edit">Edit post</a> · <a href="/posts/{{ $post->id }}">View post</a></p>
<p>Stored on this creator's server, outside its public folder. Reserved storage: {{ number_format($reserved / 1048576, 1) }} of {{ config('media.quota_mb') }} MiB. Processing reservations temporarily include maximum output sizes.</p>
@if(config('media.enabled'))
<form method="post" action="/studio/posts/{{ $post->id }}/media" enctype="multipart/form-data" class="panel stack">@csrf
<label for="file">Image{{ config('media.video_enabled') ? ' or MP4 video' : '' }}</label><input id="file" type="file" name="file" accept="image/jpeg,image/png,image/webp{{ config('media.video_enabled') ? ',video/mp4' : '' }}" required>
<label for="alt_text">Description for readers</label><input id="alt_text" name="alt_text" maxlength="240" required>
<p>Images: JPEG, PNG or WebP, at most 8 MiB and 20 million pixels. {{ config('media.video_enabled') ? 'MP4: at most 64 MiB and ten minutes. Video is converted to H.264/AAC.' : 'Video uploads are disabled.' }} Uploads are not resumable yet. New media remains unavailable until conversion succeeds.</p><button class="button">Upload privately</button></form>
@else<p role="status">Media upload and delivery are disabled until this site's operator configures them.</p>@endif
@forelse($assets as $asset)<section class="panel"><h2>{{ $asset->kind === 'image' ? 'Image' : 'Video' }}: {{ $asset->state }}</h2>
@if($asset->state === 'failed')<p>Processing or upload failed. Remove this item and upload a supported file. No source file is available to readers.</p>@endif
<form method="post" action="/studio/posts/{{ $post->id }}/media/{{ $asset->id }}" class="stack">@csrf @method('put')
<label for="alt-{{ $asset->id }}">Description</label><input id="alt-{{ $asset->id }}" name="alt_text" value="{{ $asset->alt_text }}" maxlength="240" required>
<label for="position-{{ $asset->id }}">Display order</label><input id="position-{{ $asset->id }}" name="position" type="number" value="{{ $asset->position }}" min="0" max="9999" required><button class="button small">Save details</button></form>
@if(!in_array($asset->state, ['processing', 'uploading'], true))<form method="post" action="/studio/posts/{{ $post->id }}/media/{{ $asset->id }}">@csrf @method('delete')<p>Removal revokes delivery immediately and deletes this installation's files. External downloads and backups are separate.</p><button class="button small">Remove media</button></form>@else<p>Wait for processing to finish before removal. This item is not public.</p>@endif</section>
@empty<p>No media attached.</p>@endforelse
@endsection
