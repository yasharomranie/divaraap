<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
        });
        Schema::create('user_phone_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('phone', 16)->unique();
            $table->timestamp('verified_at');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('phone_otp_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('phone');
            $table->string('phone_hash', 64)->index();
            $table->string('session_hash', 64);
            $table->string('configuration_hash', 64);
            $table->string('purpose', 16);
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('code_hash', 64);
            $table->text('payload')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->boolean('sent')->default(false);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('phone_otp_limits', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_otp_challenges');
        Schema::dropIfExists('phone_otp_limits');
        Schema::dropIfExists('user_phone_identities');
    }
};
