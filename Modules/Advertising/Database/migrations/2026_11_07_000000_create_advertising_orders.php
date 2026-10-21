<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('request_key');
            $table->string('title', 120);
            $table->text('brief');
            $table->string('goal', 20);
            $table->string('placement', 40);
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedBigInteger('requested_budget')->nullable();
            $table->string('state', 20)->default('submitted');
            $table->foreignId('invoice_id')->nullable()->unique()->constrained('advertising_invoices')->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('advertising_campaigns')->nullOnDelete();
            $table->text('manager_note')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'request_key']);
            $table->index(['user_id', 'state', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_orders');
    }
};
