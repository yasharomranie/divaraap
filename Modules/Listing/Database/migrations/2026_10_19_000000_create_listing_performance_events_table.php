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
        Schema::create('listing_performance_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->unsignedBigInteger('owner_id');
            $table->string('kind', 16);
            $table->date('event_day');
            $table->char('visitor_hash', 64);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['listing_id', 'owner_id', 'event_day'], 'listing_performance_report_index');
        });
        SoftDeleteUniqueIndex::create('listing_performance_events', ['listing_id', 'owner_id', 'kind', 'event_day', 'visitor_hash'], 'listing_performance_daily_unique');
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_performance_events');
    }
};
