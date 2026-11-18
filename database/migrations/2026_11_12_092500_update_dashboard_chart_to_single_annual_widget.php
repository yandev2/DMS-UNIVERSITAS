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

        // 1. Hapus izin kedua widget chart lama
        $oldPermissions = [
            'view:monthly_document_trend_chart_widget',
            'view:document_category_distribution_chart_widget',
        ];

        foreach ($oldPermissions as $permissionName) {
            $perm = Permission::where('name', $permissionName)->where('guard_name', 'web')->first();
            if ($perm) {
                $perm->delete();
            }
        }

        // 2. Buat izin widget chart tunggal tahunan (Annual Published Document Chart)
        $newPermission = Permission::findOrCreate('view:annual_published_document_chart_widget', 'web');

        // 3. Berikan izin ke peran super_admin dan semua peran yang memiliki akses baca dokumen
        $roles = Role::all();
        foreach ($roles as $role) {
            if ($role->name === 'super_admin' || $role->hasPermissionTo('view_any:document')) {
                $role->givePermissionTo($newPermission);
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

        $perm = Permission::where('name', 'view:annual_published_document_chart_widget')->where('guard_name', 'web')->first();
        if ($perm) {
            $perm->delete();
        }

        $oldPermissions = [
            'view:monthly_document_trend_chart_widget',
            'view:document_category_distribution_chart_widget',
        ];

        foreach ($oldPermissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
