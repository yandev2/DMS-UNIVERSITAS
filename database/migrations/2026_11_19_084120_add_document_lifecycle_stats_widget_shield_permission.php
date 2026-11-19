<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionName = 'view:document_lifecycle_stats_widget';
        Permission::findOrCreate($permissionName, 'web');

        // Berikan izin widget siklus hidup dokumen ke seluruh peran yang memiliki akses baca dokumen atau admin
        $roles = Role::all();
        foreach ($roles as $role) {
            if ($role->name === 'super_admin' || $role->hasPermissionTo('view_any:document') || $role->hasPermissionTo('view:document_overview_widget')) {
                $role->givePermissionTo($permissionName);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::where('name', 'view:document_lifecycle_stats_widget')
            ->where('guard_name', 'web')
            ->first();

        if ($permission) {
            $permission->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
