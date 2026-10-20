<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_banners', function (Blueprint $table): void {
            $table->string('creative_format', 16)->default('image');
            $table->foreignId('shop_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('shop_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('advertising_banners', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shop_user_id');
            $table->dropColumn(['creative_format', 'shop_snapshot']);
        });
    }
};
