<?php

declare(strict_types=1);

use App\Support\Database\SoftDeleteUniqueIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Admin\Support\StaffAccess;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_events', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 30);
            $table->string('value', 120);
            $table->string('fingerprint', 64);
            $table->unsignedBigInteger('category_id')->default(0);
            $table->unsignedBigInteger('country_id')->default(0);
            $table->unsignedBigInteger('city_id')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['kind', 'created_at']);
        });
        SoftDeleteUniqueIndex::create('product_events', ['fingerprint'], 'product_event_fingerprint_active');
        Schema::table('advertising_banners', fn (Blueprint $table) => $table->string('review_status', 12)->default('approved'));
        Schema::table('reviews', fn (Blueprint $table) => $table->timestamp('reviewed_at')->nullable());
        foreach (StaffAccess::ROLES as $name => $permissions) {
            $role = Role::findOrCreate($name, 'web');
            foreach ($permissions as $permission) {
                $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
            }
        }
        foreach (['operations.analytics', 'operations.audit'] as $permission) {
            Role::findOrCreate('admin', 'web')->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('product_events');
        Schema::table('advertising_banners', fn (Blueprint $table) => $table->dropColumn('review_status'));
        Schema::table('reviews', fn (Blueprint $table) => $table->dropColumn('reviewed_at'));
    }
};
