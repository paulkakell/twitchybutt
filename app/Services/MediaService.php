<?php

namespace App\Services;

use App\Jobs\ProcessMedia;
use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MediaService
{
    public function __construct(private readonly PrivateMediaStore $store) {}

    public function upload(Post $post, User $user, UploadedFile $file, string $alt): MediaAsset
    {
        abort_unless(config('media.enabled'), 503, 'Media is not enabled on this site.');
        MediaProcessor::validateConfiguration();
        if (! $file->isValid()) {
            throw ValidationException::withMessages(['file' => 'The upload is invalid.']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        $kind = in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? 'image' : 'video';
        $size = filesize($file->getPathname());
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'video/mp4'], true) || ($kind === 'video' && ! config('media.video_enabled')) || ! is_int($size) || $size < 1 || $size > ($kind === 'image' ? MediaProcessor::IMAGE_MAX : MediaProcessor::VIDEO_MAX)) {
            throw ValidationException::withMessages(['file' => 'Upload a supported file within the site size limit.']);
        }
        $reservation = $size + ($kind === 'image' ? MediaProcessor::IMAGE_OUTPUT_MAX : MediaProcessor::VIDEO_OUTPUT_MAX) + MediaProcessor::THUMBNAIL_MAX;
        $asset = DB::transaction(function () use ($post, $user, $kind, $size, $alt, $reservation): MediaAsset {
            // UPDATE is the first statement: serialize writers without a SQLite read-to-write upgrade.
            $reserved = DB::table('media_storage')->where('id', 1)->where('reserved_bytes', '<=', config('media.quota_mb') * 1048576 - $reservation)->increment('reserved_bytes', $reservation);
            abort_unless($reserved === 1, 507, 'Media storage quota is exhausted.');
            abort_if(MediaAsset::query()->where('post_id', $post->id)->where('state', '!=', 'deleted')->count() >= 50, 422, 'This post has reached its media limit.');
            $asset = new MediaAsset;
            $asset->forceFill(['id' => (string) Str::uuid(), 'post_id' => $post->id, 'user_id' => $user->id, 'kind' => $kind, 'source_bytes' => $size, 'reserved_bytes' => $reservation, 'alt_text' => $alt, 'state' => 'uploading'])->save();

            return $asset;
        }, 5);
        try {
            $this->store->receive($asset->id, $file);
            $asset->state = 'queued';
            $asset->save();
            Bus::dispatch(new ProcessMedia($asset->id));
        } catch (Throwable) {
            // Keep the reservation until explicit cleanup succeeds. Never serve a failed upload.
            MediaAsset::query()->whereKey($asset->id)->whereIn('state', ['uploading', 'queued'])->update(['state' => 'failed', 'failure_code' => 'upload_or_queue_failed']);
            Log::error('cms.media.failed', ['media_id' => $asset->id]);
            abort(503, 'Media upload could not be completed. The failed item can be removed in the studio.');
        }
        Log::info('cms.media.queued', ['media_id' => $asset->id, 'post_id' => $post->id, 'actor_id' => $user->id]);

        return $asset;
    }

    public function process(string $id, MediaProcessor $processor, ?string $claimToken = null): void
    {
        if (! config('media.enabled')) {
            return;
        }
        $claimToken ??= (string) Str::uuid();
        if (MediaAsset::query()->whereKey($id)->where('state', 'queued')->update(['state' => 'processing', 'processing_started_at' => now(), 'processing_token' => $claimToken]) !== 1) {
            return;
        }
        $asset = MediaAsset::query()->findOrFail($id);
        try {
            $output = $processor->process($asset);
            $this->store->removeSource($asset->id);
            DB::transaction(function () use ($asset, $output, $claimToken): void {
                DB::table('media_storage')->where('id', 1)->increment('reserved_bytes', 0);
                $current = MediaAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
                if ($current->state !== 'processing' || $current->processing_token !== $claimToken) {
                    throw new \RuntimeException('Media state changed during processing.');
                }
                $actual = $output['content_bytes'] + $output['thumbnail_bytes'];
                if ($actual > $current->reserved_bytes) {
                    throw new \RuntimeException('Media exceeded its reservation.');
                }
                DB::table('media_storage')->where('id', 1)->decrement('reserved_bytes', $current->reserved_bytes - $actual);
                $current->forceFill(array_merge($output, ['reserved_bytes' => $actual, 'state' => 'ready', 'failure_code' => null]))->save();
                DB::afterCommit(fn () => Log::info('cms.media.ready', ['media_id' => $asset->id, 'post_id' => $asset->post_id]));
            }, 5);
        } catch (Throwable) {
            $this->fail($id, $claimToken);
        }
    }

    public function fail(string $id, ?string $claimToken = null): void
    {
        MediaAsset::query()->whereKey($id)->where(function ($query) use ($claimToken): void {
            $query->where('state', 'queued');
            if ($claimToken !== null) {
                $query->orWhere(fn ($processing) => $processing->where('state', 'processing')->where('processing_token', $claimToken));
            }
        })->update(['state' => 'failed', 'failure_code' => 'processing_failed']);
        Log::error('cms.media.failed', ['media_id' => $id]);
    }

    public function delete(MediaAsset $snapshot): void
    {
        DB::transaction(function () use ($snapshot): void {
            $asset = MediaAsset::query()->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            abort_if(in_array($asset->state, ['processing', 'uploading'], true), 409, 'Wait for the current operation to finish before removing this item.');
            if ($asset->state !== 'deleted') {
                $asset->state = 'deleting';
                $asset->save();
            }
        }, 5);
        // Deleting state revokes all URLs before filesystem cleanup; failed cleanup stays revoked.
        $this->store->remove($snapshot->id);
        DB::transaction(function () use ($snapshot): void {
            DB::table('media_storage')->where('id', 1)->increment('reserved_bytes', 0);
            $asset = MediaAsset::query()->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();
            if ($asset->state === 'deleted') {
                return;
            }
            abort_unless($asset->state === 'deleting', 409);
            DB::table('media_storage')->where('id', 1)->decrement('reserved_bytes', $asset->reserved_bytes);
            $asset->forceFill(['state' => 'deleted', 'reserved_bytes' => 0, 'alt_text' => '', 'failure_code' => null])->save();
            DB::afterCommit(fn () => Log::info('cms.media.deleted', ['media_id' => $asset->id, 'post_id' => $asset->post_id]));
        }, 5);
    }
}
