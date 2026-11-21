<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\UserShortcut;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserShortcutPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return (int) $authUser->getAuthIdentifier() > 0;
    }

    public function view(AuthUser $authUser, UserShortcut $userShortcut): bool
    {
        return (int) $userShortcut->user_id === (int) $authUser->getAuthIdentifier();
    }

    public function create(AuthUser $authUser): bool
    {
        return (int) $authUser->getAuthIdentifier() > 0;
    }

    public function update(AuthUser $authUser, UserShortcut $userShortcut): bool
    {
        return (int) $userShortcut->user_id === (int) $authUser->getAuthIdentifier();
    }

    public function delete(AuthUser $authUser, UserShortcut $userShortcut): bool
    {
        return (int) $userShortcut->user_id === (int) $authUser->getAuthIdentifier();
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return (int) $authUser->getAuthIdentifier() > 0;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return (int) $authUser->getAuthIdentifier() > 0;
    }
}
