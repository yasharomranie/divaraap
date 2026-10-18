<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_campaigns', function (Blueprint $table): void {
            $table->unsignedInteger('delivery_weight')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('advertising_campaigns', function (Blueprint $table): void {
            $table->dropColumn('delivery_weight');
        });
    }
};
