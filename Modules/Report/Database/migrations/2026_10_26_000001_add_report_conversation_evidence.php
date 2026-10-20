<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->unsignedBigInteger('reported_message_id')->nullable();
            $table->text('conversation_evidence')->nullable();
            $table->index(['reporter_id', 'conversation_id'], 'reports_conversation_context_index');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropIndex('reports_conversation_context_index');
            $table->dropColumn(['conversation_id', 'reported_message_id', 'conversation_evidence']);
        });
    }
};
