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
        Schema::create('listing_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(0);
            $table->longText('payload');
            $table->longText('files');
            $table->timestamps();
            $table->softDeletes();
        });
        SoftDeleteUniqueIndex::create('listing_drafts', ['user_id'], 'listing_drafts_active_owner_unique');
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_drafts');
    }
};
