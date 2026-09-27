<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('email', 254)->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->text('body');
            $table->string('classification', 20)->default('unclassified');
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('price_units')->default(0);
            $table->timestamps();
            $table->index(['classification', 'status', 'id']);
        });
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('post_id')->constrained()->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->unsignedBigInteger('gross_units');
            $table->unsignedBigInteger('fee_units');
            $table->unsignedBigInteger('creator_units');
            $table->unsignedSmallInteger('fee_bps');
            $table->string('token', 12);
            $table->string('status', 16)->default('quote');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
        });
        Schema::create('entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'post_id']);
        });
        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 300);
            $table->string('category', 20);
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('entitlements');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('users');
    }
};
