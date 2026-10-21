<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80);
            $table->string('description', 500)->nullable();
            $table->foreignId('promotion_plan_id')->constrained('promotion_plans')->restrictOnDelete();
            $table->unsignedBigInteger('price');
            $table->unsignedSmallInteger('credits');
            $table->unsignedSmallInteger('validity_days');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('seller_package_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('package_id')->constrained('seller_packages')->restrictOnDelete();
            $table->json('snapshot');
            $table->string('state', 12)->default('requested');
            $table->foreignId('invoice_id')->nullable()->unique()->constrained('advertising_invoices')->restrictOnDelete();
            $table->unsignedSmallInteger('used_credits')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'state', 'ends_at']);
        });
        Schema::create('seller_package_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained('seller_package_purchases')->restrictOnDelete();
            $table->foreignId('promotion_order_id')->unique()->constrained('promotion_orders')->restrictOnDelete();
            $table->unsignedBigInteger('listing_id');
            $table->uuid('request_key');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['purchase_id', 'request_key'], 'seller_redemption_identity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_package_redemptions');
        Schema::dropIfExists('seller_package_purchases');
        Schema::dropIfExists('seller_packages');
    }
};
