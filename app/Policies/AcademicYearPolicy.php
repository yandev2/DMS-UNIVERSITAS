<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AcademicYear;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AcademicYearPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any:academic_year');
    }

    public function view(AuthUser $authUser, AcademicYear $academicYear): bool
    {
        return $authUser->can('view:academic_year');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create:academic_year');
    }

    public function update(AuthUser $authUser, AcademicYear $academicYear): bool
    {
        return $authUser->can('update:academic_year');
    }

    public function delete(AuthUser $authUser, AcademicYear $academicYear): bool
    {
        return $authUser->can('delete:academic_year');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any:academic_year');
    }

    public function restore(AuthUser $authUser, AcademicYear $academicYear): bool
    {
        return $authUser->can('restore:academic_year');
    }

    public function forceDelete(AuthUser $authUser, AcademicYear $academicYear): bool
    {
        return $authUser->can('force_delete:academic_year');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any:academic_year');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any:academic_year');
    }

    public function replicate(AuthUser $authUser, AcademicYear $academicYear): bool
    {
        return $authUser->can('replicate:academic_year');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder:academic_year');
    }
}
