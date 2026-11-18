<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IncomingLetter;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class IncomingLetterPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any:incoming_letter');
    }

    public function view(AuthUser $authUser, IncomingLetter $incomingLetter): bool
    {
        if (! $authUser->can('view:incoming_letter')) {
            return false;
        }

        if ($authUser instanceof User) {
            return $incomingLetter->canUserAccess($authUser);
        }

        return false;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create:incoming_letter');
    }

    public function update(AuthUser $authUser, IncomingLetter $incomingLetter): bool
    {
        return $authUser->can('update:incoming_letter');
    }

    public function delete(AuthUser $authUser, IncomingLetter $incomingLetter): bool
    {
        return $authUser->can('delete:incoming_letter');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any:incoming_letter');
    }

    public function restore(AuthUser $authUser, IncomingLetter $incomingLetter): bool
    {
        return $authUser->can('restore:incoming_letter');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any:incoming_letter');
    }

    public function forceDelete(AuthUser $authUser, IncomingLetter $incomingLetter): bool
    {
        return $authUser->can('force_delete:incoming_letter');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any:incoming_letter');
    }

    public function replicate(AuthUser $authUser, IncomingLetter $incomingLetter): bool
    {
        return $authUser->can('replicate:incoming_letter');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder:incoming_letter');
    }
}
