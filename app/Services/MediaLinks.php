<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\URL;

final class MediaLinks
{
    public function subject(Post $post): string
    {
        $user = request()->user();
        $public = $post->status === 'published' && $post->classification === 'general' && $post->price_units === 0;
        $identity = $public ? 'public' : ($user instanceof User ? $user->id.':'.$user->auth_version : 'guest');

        return hash_hmac('sha256', 'media:'.$post->id.':'.$identity, (string) config('app.key'));
    }

    public function url(MediaAsset $asset, Post $post, string $variant): string
    {
        return URL::temporarySignedRoute('media.show', now()->addMinutes(5), ['asset' => $asset->id, 'variant' => $variant, 'subject' => $this->subject($post)], absolute: false);
    }
}
