<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'guard_name',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Role $role): bool {
            if ($role->getDeletionRestrictionReason()) {
                return false;
            }

            return true;
        });
    }

    public function getDeletionRestrictionReason(): ?string
    {
        if ($this->name === 'super_admin') {
            return 'peran Super Admin adalah peran sistem permanen yang tidak boleh dihapus';
        }

        if ($this->users()->exists()) {
            return $this->users()->count().' pengguna aktif yang memiliki peran ini';
        }

        return null;
    }
}
