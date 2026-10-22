<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wanted_requests', function (Blueprint $table): void {
            $table->string('kind', 16)->default('product');
            $table->string('preferred_time', 120)->nullable();
            $table->index(['kind', 'state', 'expires_at'], 'wanted_kind_state_expiry');
        });
    }

    public function down(): void
    {
        Schema::table('wanted_requests', function (Blueprint $table): void {
            $table->dropIndex('wanted_kind_state_expiry');
            $table->dropColumn(['kind', 'preferred_time']);
        });
    }
};
