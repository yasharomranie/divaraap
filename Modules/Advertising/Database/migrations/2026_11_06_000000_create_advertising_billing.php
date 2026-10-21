<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('advertising_campaigns')->nullOnDelete();
            $table->json('customer');
            $table->string('description', 500);
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('IRR');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('advertising_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('advertising_invoices')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('reference', 80)->unique();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_payments');
        Schema::dropIfExists('advertising_invoices');
    }
};
