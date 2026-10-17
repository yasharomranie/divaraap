<?php

declare(strict_types=1);

use App\Support\Database\SoftDeleteUniqueIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Advertising\Models\BannerPlacement;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_banners', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->string('image');
            $table->string('mobile_image')->nullable();
            $table->string('alt', 200);
            $table->string('url', 2048)->nullable();
            $table->boolean('new_tab')->default(false);
            $table->string('device', 10)->default('both');
            $table->boolean('is_active')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('advertising_placements', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 50);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('first_after')->default(4);
            $table->unsignedInteger('interval')->default(6);
            $table->string('ordering', 10)->default('priority');
            $table->string('device', 10)->default('both');
            $table->boolean('repeat')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        SoftDeleteUniqueIndex::create('advertising_placements', ['key'], 'advertising_placements_key_active');
        Schema::create('advertising_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('placement_id')->constrained('advertising_placements');
            $table->foreignId('banner_id')->constrained('advertising_banners');
            $table->unsignedInteger('priority')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
        SoftDeleteUniqueIndex::create('advertising_assignments', ['placement_id', 'banner_id'], 'advertising_assignment_active');
        foreach (BannerPlacement::KEYS as $key) {
            DB::table('advertising_placements')->insert(['key' => $key, 'created_at' => now(), 'updated_at' => now()]);
        }
        $permission = Permission::findOrCreate('advertising.manage', 'web');
        Role::findOrCreate('admin', 'web')->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_assignments');
        Schema::dropIfExists('advertising_placements');
        Schema::dropIfExists('advertising_banners');
        Permission::query()->where('name', 'advertising.manage')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
