<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['advertising_banners', 'advertising_campaigns'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->unsignedInteger('daily_frequency_cap')->nullable();
            });
        }
        Schema::create('advertising_frequency_counts', function (Blueprint $table): void {
            $table->id();
            $table->string('visitor', 64);
            $table->string('scope', 10);
            $table->unsignedBigInteger('subject_id');
            $table->date('count_date');
            $table->unsignedInteger('impressions')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['visitor', 'scope', 'subject_id', 'count_date'], 'advertising_frequency_unique');
            $table->index('count_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_frequency_counts');
        foreach (['advertising_banners', 'advertising_campaigns'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn('daily_frequency_cap');
            });
        }
    }
};
