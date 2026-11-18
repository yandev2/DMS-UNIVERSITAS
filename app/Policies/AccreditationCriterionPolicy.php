<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AccreditationCriterion;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AccreditationCriterionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any:accreditation_criterion');
    }

    public function view(AuthUser $authUser, AccreditationCriterion $accreditationCriterion): bool
    {
        return $authUser->can('view:accreditation_criterion');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create:accreditation_criterion');
    }

    public function update(AuthUser $authUser, AccreditationCriterion $accreditationCriterion): bool
    {
        return $authUser->can('update:accreditation_criterion');
    }

    public function delete(AuthUser $authUser, AccreditationCriterion $accreditationCriterion): bool
    {
        return $authUser->can('delete:accreditation_criterion');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any:accreditation_criterion');
    }

    public function restore(AuthUser $authUser, AccreditationCriterion $accreditationCriterion): bool
    {
        return $authUser->can('restore:accreditation_criterion');
    }

    public function forceDelete(AuthUser $authUser, AccreditationCriterion $accreditationCriterion): bool
    {
        return $authUser->can('force_delete:accreditation_criterion');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any:accreditation_criterion');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any:accreditation_criterion');
    }

    public function replicate(AuthUser $authUser, AccreditationCriterion $accreditationCriterion): bool
    {
        return $authUser->can('replicate:accreditation_criterion');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder:accreditation_criterion');
    }
}
