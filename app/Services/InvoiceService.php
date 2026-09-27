<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class InvoiceService
{
    public function create(User $user, Post $post, string $key): Invoice
    {
        return DB::transaction(function () use ($user, $post, $key): Invoice {
            // Serializes invoices per buyer; a DB uniqueness constraint is the second guard.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = Invoice::query()->where('user_id', $user->id)->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                abort_if($existing->post_id !== $post->id, 409, 'Idempotency key belongs to a different purchase.');
                return $existing;
            }
            $current = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->status === 'published' && $current->classification === 'general' && $current->price_units > 0, 404);
            $split = TokenAmount::split($current->price_units);
            $invoice = new Invoice;
            $invoice->forceFill([
                'id' => (string) Str::uuid(), 'user_id' => $user->id, 'post_id' => $current->id,
                'idempotency_key' => $key, 'gross_units' => $split['gross'], 'fee_units' => $split['fee'],
                'creator_units' => $split['creator'], 'token' => 'TEST', 'fee_bps' => TokenAmount::FEE_BPS,
                'status' => 'quote', 'expires_at' => now()->addMinutes(30),
            ])->save();
            Log::info('cms.invoice.quoted', ['invoice_id' => $invoice->id, 'actor_id' => $user->id]);
            return $invoice;
        });
    }
}
