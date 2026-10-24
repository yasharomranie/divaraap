<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('favorite_collections', function (Blueprint $table): void {
            $table->text('share_token')->nullable();
            $table->string('share_hash', 64)->nullable()->unique();
            $table->json('share_ids')->nullable();
            $table->boolean('share_enabled')->default(false);
            $table->unsignedInteger('share_revision')->default(0);
            $table->timestamp('share_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('favorite_collections', function (Blueprint $table): void {
            $table->dropUnique(['share_hash']);
            $table->dropColumn(['share_token', 'share_hash', 'share_ids', 'share_enabled', 'share_revision', 'share_expires_at']);
        });
    }
};
