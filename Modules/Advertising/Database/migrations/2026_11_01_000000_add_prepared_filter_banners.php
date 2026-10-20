<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_banners', fn (Blueprint $table) => $table->json('filter_choices')->nullable());
    }

    public function down(): void
    {
        Schema::table('advertising_banners', fn (Blueprint $table) => $table->dropColumn('filter_choices'));
    }
};
