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

        $permissions = [
            'view:document_overview_widget',
            'view:pending_approvals_widget',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        // Berikan izin widget dashboard ke semua peran yang memiliki akses baca dokumen
        $roles = Role::all();
        foreach ($roles as $role) {
            if ($role->name === 'super_admin' || $role->hasPermissionTo('view_any:document')) {
                $role->givePermissionTo($permissions);
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

        $permissions = [
            'view:document_overview_widget',
            'view:pending_approvals_widget',
        ];

        foreach ($permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->where('guard_name', 'web')->first();
            if ($permission) {
                $permission->delete();
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
