<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DocumentCategory;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DocumentCategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any:document_category');
    }

    public function view(AuthUser $authUser, DocumentCategory $documentCategory): bool
    {
        return $authUser->can('view:document_category');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create:document_category');
    }

    public function update(AuthUser $authUser, DocumentCategory $documentCategory): bool
    {
        return $authUser->can('update:document_category');
    }

    public function delete(AuthUser $authUser, DocumentCategory $documentCategory): bool
    {
        return $authUser->can('delete:document_category');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any:document_category');
    }

    public function restore(AuthUser $authUser, DocumentCategory $documentCategory): bool
    {
        return $authUser->can('restore:document_category');
    }

    public function forceDelete(AuthUser $authUser, DocumentCategory $documentCategory): bool
    {
        return $authUser->can('force_delete:document_category');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any:document_category');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any:document_category');
    }

    public function replicate(AuthUser $authUser, DocumentCategory $documentCategory): bool
    {
        return $authUser->can('replicate:document_category');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder:document_category');
    }
}
