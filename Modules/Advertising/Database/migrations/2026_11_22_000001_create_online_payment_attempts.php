<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_payment_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('invoice_id')->constrained('advertising_invoices')->restrictOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->string('gateway', 16);
            $table->boolean('sandbox');
            $table->unsignedBigInteger('amount');
            $table->text('merchant');
            $table->text('authority')->nullable();
            $table->string('authority_hash', 64)->nullable()->unique();
            $table->text('callback_token');
            $table->string('callback_hash', 64);
            $table->string('status', 20)->default('creating');
            $table->string('gateway_reference', 50)->nullable();
            $table->string('response_code', 20)->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps(6);
            $table->softDeletes();
            $table->index(['invoice_id', 'status']);
        });
        Schema::table('advertising_payments', function (Blueprint $table): void {
            $table->uuid('online_attempt_id')->nullable()->unique();
            $table->foreign('online_attempt_id')->references('id')->on('online_payment_attempts')->restrictOnDelete();
            $table->string('gateway', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('advertising_payments', function (Blueprint $table): void {
            $table->dropForeign(['online_attempt_id']);
            $table->dropUnique(['online_attempt_id']);
            $table->dropColumn(['online_attempt_id', 'gateway']);
        });
        Schema::dropIfExists('online_payment_attempts');
    }
};
