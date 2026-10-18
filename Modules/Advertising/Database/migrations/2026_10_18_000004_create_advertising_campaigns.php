<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->string('goal', 30)->default('awareness');
            $table->boolean('is_active')->default(false);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedBigInteger('planned_budget')->nullable();
            $table->json('category_ids')->nullable();
            $table->json('city_ids')->nullable();
            $table->json('page_paths')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('advertising_banners', function (Blueprint $table): void {
            $table->foreignId('campaign_id')->nullable()->constrained('advertising_campaigns')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('advertising_banners', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('campaign_id');
        });
        Schema::dropIfExists('advertising_campaigns');
    }
};
