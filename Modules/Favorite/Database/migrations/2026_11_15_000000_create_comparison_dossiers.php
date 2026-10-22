<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comparison_dossiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_key');
            $table->string('label', 100);
            $table->unsignedBigInteger('category_id');
            $table->json('listing_ids');
            $table->string('token_hash', 64)->unique();
            $table->text('share_token');
            $table->boolean('sharing')->default(true);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'request_key']);
            $table->index(['user_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_dossiers');
    }
};
