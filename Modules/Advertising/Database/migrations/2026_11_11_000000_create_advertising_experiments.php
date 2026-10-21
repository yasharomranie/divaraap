<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_experiments', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('placement_id')->constrained('advertising_placements')->restrictOnDelete();
            $table->foreignId('banner_a_id')->constrained('advertising_banners')->restrictOnDelete();
            $table->foreignId('banner_b_id')->constrained('advertising_banners')->restrictOnDelete();
            $table->unsignedSmallInteger('duration_days');
            $table->string('state', 16)->default('draft');
            $table->json('snapshot')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['placement_id', 'state']);
        });
        Schema::table('advertising_events', function (Blueprint $table): void {
            $table->foreignId('experiment_id')->nullable()->constrained('advertising_experiments')->restrictOnDelete();
            $table->string('experiment_arm', 1)->nullable();
            $table->index(['experiment_id', 'experiment_arm', 'kind'], 'advertising_experiment_metrics');
        });
    }

    public function down(): void
    {
        Schema::table('advertising_events', function (Blueprint $table): void {
            $table->dropIndex('advertising_experiment_metrics');
            $table->dropConstrainedForeignId('experiment_id');
            $table->dropColumn('experiment_arm');
        });
        Schema::dropIfExists('advertising_experiments');
    }
};
