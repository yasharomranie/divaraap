<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_banners', function (Blueprint $table): void {
            $table->foreignId('guide_page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('guide_signature', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('advertising_banners', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('guide_page_id');
            $table->dropColumn('guide_signature');
        });
    }
};
