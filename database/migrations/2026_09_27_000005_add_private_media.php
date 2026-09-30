<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_storage', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('reserved_bytes')->default(0);
        });
        DB::table('media_storage')->insert(['id' => 1, 'reserved_bytes' => 0]);
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('post_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('kind', 8);
            $table->string('state', 16)->default('uploading');
            $table->string('alt_text', 240)->default('');
            $table->unsignedInteger('position')->default(0);
            $table->unsignedBigInteger('source_bytes');
            $table->unsignedBigInteger('reserved_bytes');
            $table->unsignedBigInteger('content_bytes')->default(0);
            $table->unsignedBigInteger('thumbnail_bytes')->default(0);
            $table->string('content_sha256', 64)->nullable();
            $table->string('thumbnail_sha256', 64)->nullable();
            $table->string('failure_code', 32)->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->uuid('processing_token')->nullable();
            $table->timestamps();
            $table->index(['post_id', 'state', 'position']);
        });
    }

    public function down(): void
    {
        // Files are deliberately not deleted by a schema rollback.
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('media_storage');
    }
};
