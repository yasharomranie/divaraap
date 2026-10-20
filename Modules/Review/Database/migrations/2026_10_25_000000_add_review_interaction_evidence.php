<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->timestamp('interaction_verified_at')->nullable();
            $table->unsignedBigInteger('interaction_conversation_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropColumn(['interaction_verified_at', 'interaction_conversation_id']);
        });
    }
};
