<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising_banners', fn (Blueprint $table) => $table->string('video_caption_language', 2)->nullable());
    }

    public function down(): void
    {
        Schema::table('advertising_banners', fn (Blueprint $table) => $table->dropColumn('video_caption_language'));
    }
};
