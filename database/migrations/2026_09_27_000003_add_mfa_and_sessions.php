<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('mfa_secret')->nullable();
            $table->text('mfa_pending_secret')->nullable();
            $table->unsignedInteger('mfa_pending_version')->nullable();
            $table->string('mfa_pending_purpose', 12)->nullable();
            $table->timestamp('mfa_pending_expires_at')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->unsignedBigInteger('mfa_last_counter')->nullable();
        });
        Schema::create('mfa_recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->timestamp('created_at');
        });
        Schema::create('account_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('auth_version');
            $table->timestamp('created_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('revoked_at')->nullable();
            $table->index(['user_id', 'revoked_at', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_sessions');
        Schema::dropIfExists('mfa_recovery_codes');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['mfa_secret', 'mfa_pending_secret', 'mfa_pending_version', 'mfa_pending_purpose', 'mfa_pending_expires_at', 'mfa_confirmed_at', 'mfa_last_counter']);
        });
    }
};
