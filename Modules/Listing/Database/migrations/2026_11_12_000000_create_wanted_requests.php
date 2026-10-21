<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wanted_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->uuid('request_key');
            $table->string('title', 120);
            $table->text('description');
            $table->unsignedBigInteger('category_id')->index();
            $table->unsignedBigInteger('city_id')->nullable()->index();
            $table->unsignedBigInteger('budget')->nullable();
            $table->string('state', 16)->default('pending');
            $table->unsignedInteger('revision')->default(1);
            $table->string('moderation_note', 1000)->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'request_key']);
            $table->index(['state', 'expires_at']);
        });
        Schema::create('wanted_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wanted_request_id')->constrained('wanted_requests')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('listing_id');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['wanted_request_id', 'revision', 'seller_id'], 'wanted_response_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wanted_responses');
        Schema::dropIfExists('wanted_requests');
    }
};
