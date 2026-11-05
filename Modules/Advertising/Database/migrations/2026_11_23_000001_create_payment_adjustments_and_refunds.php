<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_refund_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('advertising_invoices')->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('advertising_payments')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->uuid('form_key')->unique();
            $table->unsignedBigInteger('amount');
            $table->text('reason');
            $table->string('state', 16)->default('requested');
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('decision_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['payment_id', 'state']);
            $table->index(['invoice_id', 'state']);
        });
        Schema::create('advertising_payment_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('advertising_invoices')->restrictOnDelete();
            $table->foreignId('payment_id')->constrained('advertising_payments')->restrictOnDelete();
            $table->foreignId('refund_request_id')->nullable()->unique()->constrained('advertising_refund_requests')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('kind', 16);
            $table->unsignedBigInteger('amount');
            $table->string('reference', 80);
            $table->uuid('operation_key')->unique();
            $table->text('reason');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['kind', 'reference']);
            $table->index(['invoice_id', 'kind']);
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_payment_adjustments');
        Schema::dropIfExists('advertising_refund_requests');
    }
};
