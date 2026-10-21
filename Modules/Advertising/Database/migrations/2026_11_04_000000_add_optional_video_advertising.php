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
            $table->foreignId('video_id')->nullable()->constrained('videos')->nullOnDelete();
            $table->json('video_snapshot')->nullable();
            $table->text('video_transcript')->nullable();
            $table->string('video_captions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('advertising_banners', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('video_id');
            $table->dropColumn(['video_snapshot', 'video_transcript', 'video_captions']);
        });
    }
};
