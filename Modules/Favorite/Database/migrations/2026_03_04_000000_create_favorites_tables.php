<?php

declare(strict_types=1);

use App\Support\Database\SoftDeleteUniqueIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorite_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'listing_id']);
        });

        Schema::create('favorite_sellers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'seller_id']);
        });

        Schema::create('favorite_searches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('search_term')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->json('filters')->nullable();
            $table->string('signature', 64);
            $table->timestamps();
            $table->softDeletes();
        });

        SoftDeleteUniqueIndex::create('favorite_searches', ['user_id', 'signature'], 'favorite_searches_user_id_signature_unique');
    }

    public function down(): void
    {
        Schema::dropIfExists('favorite_searches');
        Schema::dropIfExists('favorite_sellers');
        Schema::dropIfExists('favorite_listings');
    }
};
