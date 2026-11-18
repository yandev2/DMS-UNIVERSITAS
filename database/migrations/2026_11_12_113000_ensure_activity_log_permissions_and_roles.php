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

        $viewAny = Permission::findOrCreate('view_any:activity_log', 'web');
        $view = Permission::findOrCreate('view:activity_log', 'web');

        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo([$viewAny, $view]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $superAdmin = Role::where('name', 'super_admin')->first();
        if ($superAdmin) {
            $superAdmin->revokePermissionTo(['view_any:activity_log', 'view:activity_log']);
        }

        Permission::whereIn('name', ['view_any:activity_log', 'view:activity_log'])->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
