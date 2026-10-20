<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->boolean('shop_enabled')->default(false);
            $table->string('shop_name', 80)->nullable();
            $table->text('shop_description')->nullable();
            $table->string('shop_hours', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn(['shop_enabled', 'shop_name', 'shop_description', 'shop_hours']);
        });
    }
};
