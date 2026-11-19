<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OutgoingLetterStatus;
use App\Models\OutgoingLetter;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OutgoingLetterPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any:outgoing_letter');
    }

    public function view(AuthUser $authUser, OutgoingLetter $outgoingLetter): bool
    {
        if (! $authUser->can('view:outgoing_letter')) {
            return false;
        }

        if ($authUser instanceof User) {
            return $outgoingLetter->canUserAccess($authUser);
        }

        return false;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create:outgoing_letter');
    }

    public function update(AuthUser $authUser, OutgoingLetter $outgoingLetter): bool
    {
        if (! $authUser->can('update:outgoing_letter')) {
            return false;
        }

        if ($authUser->can('letter.manage_humas')) {
            return true;
        }

        if (in_array($outgoingLetter->status, [OutgoingLetterStatus::WaitingSignature, OutgoingLetterStatus::Published], true)) {
            return false;
        }

        return $outgoingLetter->created_by === $authUser->id
            || ($authUser->unit_id !== null && $outgoingLetter->unit_id === $authUser->unit_id);
    }

    public function delete(AuthUser $authUser, OutgoingLetter $outgoingLetter): bool
    {
        if (! $authUser->can('delete:outgoing_letter')) {
            return false;
        }

        if ($authUser->can('letter.manage_humas')) {
            return true;
        }

        if (in_array($outgoingLetter->status, [OutgoingLetterStatus::WaitingSignature, OutgoingLetterStatus::Published], true)) {
            return false;
        }

        return $outgoingLetter->created_by === $authUser->id;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any:outgoing_letter');
    }

    public function restore(AuthUser $authUser, OutgoingLetter $outgoingLetter): bool
    {
        return $authUser->can('restore:outgoing_letter');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any:outgoing_letter');
    }

    public function forceDelete(AuthUser $authUser, OutgoingLetter $outgoingLetter): bool
    {
        return $authUser->can('force_delete:outgoing_letter');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any:outgoing_letter');
    }

    public function replicate(AuthUser $authUser, OutgoingLetter $outgoingLetter): bool
    {
        return $authUser->can('replicate:outgoing_letter');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder:outgoing_letter');
    }
}
