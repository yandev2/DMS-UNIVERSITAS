<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Unit;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UnitPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any:unit');
    }

    public function view(AuthUser $authUser, Unit $unit): bool
    {
        return $authUser->can('view:unit');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create:unit');
    }

    public function update(AuthUser $authUser, Unit $unit): bool
    {
        return $authUser->can('update:unit');
    }

    public function delete(AuthUser $authUser, Unit $unit): bool
    {
        return $authUser->can('delete:unit');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any:unit');
    }

    public function restore(AuthUser $authUser, Unit $unit): bool
    {
        return $authUser->can('restore:unit');
    }

    public function forceDelete(AuthUser $authUser, Unit $unit): bool
    {
        return $authUser->can('force_delete:unit');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any:unit');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any:unit');
    }

    public function replicate(AuthUser $authUser, Unit $unit): bool
    {
        return $authUser->can('replicate:unit');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder:unit');
    }
}
