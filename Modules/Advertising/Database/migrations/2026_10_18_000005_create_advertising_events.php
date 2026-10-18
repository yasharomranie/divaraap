<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('banner_id')->index();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->string('placement', 50);
            $table->string('device', 10);
            $table->string('kind', 10);
            $table->string('receipt', 64)->unique();
            $table->date('event_date')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['event_date', 'kind', 'placement']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_events');
    }
};
