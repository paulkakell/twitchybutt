<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\User;
use App\Services\MediaLinks;
use App\Services\MediaService;
use App\Services\PrivateMediaStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class MediaController
{
    public function index(Post $post): View
    {
        Gate::authorize('manage-content');

        return view('media-studio', ['post' => $post, 'assets' => MediaAsset::query()->where('post_id', $post->id)->where('state', '!=', 'deleted')->orderBy('position')->orderBy('id')->get(), 'reserved' => (int) DB::table('media_storage')->where('id', 1)->value('reserved_bytes')]);
    }

    public function upload(Request $request, Post $post, MediaService $service): RedirectResponse
    {
        Gate::authorize('manage-content');
        abort_unless(config('media.enabled'), 503, 'Media is not enabled on this site.');
        $data = $request->validate(['file' => ['required', 'file', 'max:65536'], 'alt_text' => ['required', 'string', 'max:240']]);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $service->upload($post, $user, $data['file'], $data['alt_text']);

        return redirect('/studio/posts/'.$post->id.'/media')->with('status', 'Upload queued for private processing. It is not available to readers yet.');
    }

    public function update(Request $request, Post $post, MediaAsset $asset): RedirectResponse
    {
        Gate::authorize('manage-content');
        abort_unless($asset->post_id === $post->id && $asset->state !== 'deleted', 404);
        $data = $request->validate(['alt_text' => ['required', 'string', 'max:240'], 'position' => ['required', 'integer', 'min:0', 'max:9999']]);
        $asset->forceFill($data)->save();

        return redirect('/studio/posts/'.$post->id.'/media');
    }

    public function delete(Post $post, MediaAsset $asset, MediaService $service): RedirectResponse
    {
        Gate::authorize('manage-content');
        abort_unless($asset->post_id === $post->id, 404);
        $service->delete($asset);

        return redirect('/studio/posts/'.$post->id.'/media')->with('status', 'Media removed from this installation.');
    }

    public function show(Request $request, MediaAsset $asset, string $variant, MediaLinks $links, PrivateMediaStore $store): BinaryFileResponse
    {
        abort_unless(config('media.enabled') && $asset->state === 'ready' && in_array($variant, ['content', 'thumbnail'], true), 404);
        $post = Post::query()->findOrFail($asset->post_id);
        abort_unless(Gate::allows('view', $post), 404);
        $subject = $request->query('subject');
        abort_unless(is_string($subject) && hash_equals($links->subject($post), $subject), 403);
        $filename = $variant === 'thumbnail' ? 'thumbnail.jpg' : ($asset->kind === 'image' ? 'content.jpg' : 'content.mp4');
        $path = $store->path($asset->id, $filename);
        $expectedSize = $variant === 'thumbnail' ? $asset->thumbnail_bytes : $asset->content_bytes;
        clearstatcache(true, $path);
        abort_unless(is_file($path) && filesize($path) === $expectedSize, 404);
        $range = $request->header('Range');
        if ($range !== null && ! $request->hasHeader('If-Range')) {
            $valid = preg_match('/\Abytes=([0-9]{0,18})-([0-9]{0,18})\z/', $range, $parts) === 1;
            $valid = $valid && ($parts[1] !== '' || $parts[2] !== '');
            $valid = $valid && ($parts[1] === '' ? (int) $parts[2] > 0 : ((int) $parts[1] < $expectedSize && ($parts[2] === '' || (int) $parts[2] >= (int) $parts[1])));
            abort_unless($valid, 416, 'Requested media range is unavailable.', ['Content-Range' => 'bytes */'.$expectedSize]);
        }
        $response = new BinaryFileResponse($path, 200, ['Content-Type' => str_ends_with($filename, '.mp4') ? 'video/mp4' : 'image/jpeg', 'Cache-Control' => 'private, no-store, max-age=0'], false);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);
        // Never delegate to client-selected X-Sendfile or expose the quarantined original.

        return $response;
    }
}
