<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('subject');
            $table->string('category', 24);
            $table->string('state', 24)->default('open');
            $table->unsignedInteger('version')->default(0);
            $table->unsignedInteger('reopen_count')->default(0);
            $table->uuid('form_key')->unique();
            $table->string('creation_digest', 64);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_activity_at');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'state']);
            $table->index(['assignee_id', 'state', 'last_activity_at']);
        });
        Schema::create('support_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 24);
            $table->text('body');
            $table->uuid('operation_key')->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['ticket_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
    }
};
