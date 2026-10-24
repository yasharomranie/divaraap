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
        Schema::create('neighborhood_shops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('city_id');
            $table->string('neighborhood', 80);
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->string('shop_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['city_id', 'is_active', 'expires_at']);
        });
        SoftDeleteUniqueIndex::create('neighborhood_shops', ['shop_user_id', 'city_id'], 'neighborhood_shop_active_identity');
    }

    public function down(): void
    {
        Schema::dropIfExists('neighborhood_shops');
    }
};
