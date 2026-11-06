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
        Schema::create('staff_security_policies', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->json('roles');
            $table->unsignedInteger('step_up_minutes')->default(15);
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('staff_security_policies')->insert(['id' => 1, 'enabled' => false, 'roles' => json_encode(['support', 'moderator', 'advertising-manager', 'panel_access']), 'step_up_minutes' => 15, 'version' => 0, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('staff_security_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('source_hash', 64)->nullable();
            $table->unsignedBigInteger('last_totp_step')->nullable();
            $table->unsignedInteger('failures')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('staff_security_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('fingerprint', 64);
            $table->timestamp('last_seen_at');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'fingerprint']);
        });
        Schema::create('staff_security_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 32);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_security_events');
        Schema::dropIfExists('staff_security_contexts');
        Schema::dropIfExists('staff_security_states');
        Schema::dropIfExists('staff_security_policies');
    }
};
