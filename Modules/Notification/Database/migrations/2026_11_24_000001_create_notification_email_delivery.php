<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_email_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->boolean('enabled')->default(false);
            $table->text('recipient')->nullable();
            $table->json('types');
            $table->string('locale', 8)->default('fa');
            $table->unsignedInteger('version')->default(0);
            $table->text('unsubscribe_token')->nullable();
            $table->string('unsubscribe_hash', 64)->nullable()->unique();
            $table->timestamp('consented_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('notification_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->unique()->constrained('user_notifications')->restrictOnDelete();
            $table->foreignId('preference_id')->constrained('notification_email_preferences')->restrictOnDelete();
            $table->unsignedInteger('preference_version');
            $table->string('state', 16)->default('pending');
            $table->string('outcome', 32)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['state', 'id']);
        });
        Schema::create('notification_email_budgets', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 64)->unique();
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_email_deliveries');
        Schema::dropIfExists('notification_email_preferences');
        Schema::dropIfExists('notification_email_budgets');
    }
};
