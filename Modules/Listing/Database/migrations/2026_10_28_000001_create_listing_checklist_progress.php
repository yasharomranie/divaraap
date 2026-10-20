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
        Schema::create('listing_checklist_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->string('revision', 64);
            $table->uuid('version');
            $table->json('checked_keys');
            $table->timestamps();
            $table->softDeletes();
        });
        SoftDeleteUniqueIndex::create('listing_checklist_progress', ['user_id', 'listing_id'], 'listing_checklist_owner_unique');
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_checklist_progress');
    }
};
