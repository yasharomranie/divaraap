<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_bundles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('price');
            $table->json('items');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('advertising_orders', function (Blueprint $table): void {
            $table->foreignId('bundle_id')->nullable()->constrained('advertising_bundles')->restrictOnDelete();
            $table->json('bundle_snapshot')->nullable();
            $table->json('bundle_fulfillment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('advertising_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bundle_id');
            $table->dropColumn(['bundle_snapshot', 'bundle_fulfillment']);
        });
        Schema::dropIfExists('advertising_bundles');
    }
};
