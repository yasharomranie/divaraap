<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->timestamp('publish_at')->nullable();
            $table->index(['status', 'publish_at'], 'listings_scheduled_due_index');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropIndex('listings_scheduled_due_index');
            $table->dropColumn('publish_at');
        });
    }
};
