<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 80);
            $table->text('body');
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'deleted_at', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_replies');
    }
};
