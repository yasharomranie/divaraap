<?php

declare(strict_types=1);

use App\Support\Database\SoftDeleteUniqueIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rare_item_watches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('signature', 64);
            $table->json('filters');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->softDeletes();
        });
        SoftDeleteUniqueIndex::create('rare_item_watches', ['user_id', 'signature'], 'rare_item_watch_active_identity');
    }

    public function down(): void
    {
        Schema::dropIfExists('rare_item_watches');
    }
};
