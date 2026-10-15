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
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('seller_id')->index();
            $table->unsignedBigInteger('author_id')->index();
            $table->unsignedBigInteger('listing_id')->nullable()->index();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['seller_id', 'is_approved']);
        });

        SoftDeleteUniqueIndex::create('reviews', ['author_id', 'listing_id'], 'reviews_author_listing_unique');
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
