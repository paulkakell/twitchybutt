<?php

namespace App\Policies;

use App\Models\Entitlement;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function view(?User $user, Post $post): bool
    {
        if ($user?->is_admin) {
            return true;
        }
        // Classification checks precede free pricing and entitlement checks.
        if ($post->status !== 'published' || $post->classification !== 'general') {
            return false;
        }
        if ($post->price_units === 0) {
            return true;
        }
        return $user !== null && Entitlement::query()
            ->where('user_id', $user->id)->where('post_id', $post->id)
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
    }
}
