<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_warnings', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('subject_type', 10);
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_title', 150);
            $table->string('code', 30);
            $table->boolean('is_open')->default(true);
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('last_seen_at');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_warnings');
    }
};
