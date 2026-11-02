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
        Schema::create('listing_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->unsignedBigInteger('asker_id')->index();
            $table->string('fingerprint', 64);
            $table->text('question');
            $table->text('answer')->nullable();
            $table->unsignedBigInteger('answered_by')->nullable();
            $table->string('state', 16)->default('pending');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['listing_id', 'state', 'id']);
        });
        SoftDeleteUniqueIndex::create('listing_questions', ['listing_id', 'asker_id', 'fingerprint'], 'listing_questions_active_unique');
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_questions');
    }
};
