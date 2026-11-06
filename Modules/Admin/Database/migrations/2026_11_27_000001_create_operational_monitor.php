<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_monitors', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->json('recipients');
            $table->json('thresholds');
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_scheduled_at')->nullable();
            $table->bigInteger('sample_slot')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('operational_monitors')->insert(['id' => 1, 'recipients' => '[]', 'thresholds' => json_encode(['queue_minutes' => 10, 'queue_limit' => 100, 'window_minutes' => 15, 'error_limit' => 5, 'heartbeat_minutes' => 5, 'disk_mb' => 256, 'deploy_minutes' => 30]), 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('operational_checks', function (Blueprint $table): void {
            $table->id();
            $table->string('check_key', 32)->unique();
            $table->string('status', 24);
            $table->string('code', 32);
            $table->unsignedBigInteger('value')->nullable();
            $table->unsignedInteger('bad_streak')->default(0);
            $table->unsignedInteger('good_streak')->default(0);
            $table->timestamp('sampled_at');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('operational_incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('check_key', 32)->index();
            $table->string('active_key', 32)->nullable()->unique();
            $table->timestamp('opened_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('operational_error_buckets', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('bucket_at')->unique();
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        foreach (['operational_error_buckets', 'operational_incidents', 'operational_checks', 'operational_monitors'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
