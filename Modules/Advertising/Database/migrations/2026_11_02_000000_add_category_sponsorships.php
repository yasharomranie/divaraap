<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_banners', fn (Blueprint $table) => $table->foreignId('sponsored_category_id')->nullable()->constrained('categories')->nullOnDelete());
        if (! DB::table('advertising_placements')->where('key', 'category-top')->whereNull('deleted_at')->exists()) {
            DB::table('advertising_placements')->insert(['key' => 'category-top', 'is_active' => true, 'first_after' => 1, 'interval' => 1, 'ordering' => 'priority', 'device' => 'both', 'repeat' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('advertising_banners', fn (Blueprint $table) => $table->dropConstrainedForeignId('sponsored_category_id'));
    }
};
