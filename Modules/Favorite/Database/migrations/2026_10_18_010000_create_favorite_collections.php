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
        Schema::create('favorite_collections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('signature', 64);
            $table->timestamps();
            $table->softDeletes();
        });
        SoftDeleteUniqueIndex::create('favorite_collections', ['user_id', 'signature'], 'favorite_collections_owner_name_unique');
        Schema::table('favorite_listings', function (Blueprint $table): void {
            $table->foreignId('collection_id')->nullable()->constrained('favorite_collections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('favorite_listings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('collection_id');
        });
        Schema::dropIfExists('favorite_collections');
    }
};
