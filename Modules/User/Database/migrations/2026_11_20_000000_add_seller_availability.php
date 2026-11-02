<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->boolean('away_enabled')->default(false);
            $table->timestamp('away_until')->nullable();
            $table->string('away_reason', 24)->nullable();
            $table->unsignedInteger('away_revision')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn(['away_enabled', 'away_until', 'away_reason', 'away_revision']));
    }
};
