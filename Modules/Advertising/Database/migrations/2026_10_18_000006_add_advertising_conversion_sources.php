<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('target_listing_id')->nullable()->index();
            $table->unsignedBigInteger('source_click_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('advertising_events', function (Blueprint $table): void {
            $table->dropColumn(['target_listing_id', 'source_click_id']);
        });
    }
};
