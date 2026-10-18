<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_publications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('listing_id')->unique();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('buyer_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 12);
            $table->unsignedBigInteger('source_id');
            $table->boolean('enabled')->default(true);
            $table->unsignedBigInteger('cursor')->default(0);
            $table->decimal('lowest_price', 18, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'kind', 'source_id']);
        });
        Schema::create('buyer_alert_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('signature', 64);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'signature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_alert_receipts');
        Schema::dropIfExists('buyer_alerts');
        Schema::dropIfExists('buyer_publications');
    }
};
