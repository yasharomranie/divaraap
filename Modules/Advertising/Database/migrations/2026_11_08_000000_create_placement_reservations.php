<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('placement_id')->constrained('advertising_placements')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('advertising_orders')->restrictOnDelete();
            $table->string('title', 120);
            $table->string('device', 10);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('state', 12)->default('confirmed');
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['placement_id', 'state', 'starts_at', 'ends_at'], 'advertising_reservation_window');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_reservations');
    }
};
