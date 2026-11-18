<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PostCategory;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PostCategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any:post_category');
    }

    public function view(AuthUser $authUser, PostCategory $postCategory): bool
    {
        return $authUser->can('view:post_category');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create:post_category');
    }

    public function update(AuthUser $authUser, PostCategory $postCategory): bool
    {
        return $authUser->can('update:post_category');
    }

    public function delete(AuthUser $authUser, PostCategory $postCategory): bool
    {
        return $authUser->can('delete:post_category');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any:post_category');
    }

    public function restore(AuthUser $authUser, PostCategory $postCategory): bool
    {
        return $authUser->can('restore:post_category');
    }

    public function forceDelete(AuthUser $authUser, PostCategory $postCategory): bool
    {
        return $authUser->can('force_delete:post_category');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any:post_category');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any:post_category');
    }

    public function replicate(AuthUser $authUser, PostCategory $postCategory): bool
    {
        return $authUser->can('replicate:post_category');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder:post_category');
    }
}
