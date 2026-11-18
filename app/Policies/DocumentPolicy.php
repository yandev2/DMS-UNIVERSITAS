<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\VisibilityLevel;
use App\Models\Document;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any:document');
    }

    public function view(AuthUser $authUser, Document $document): bool
    {
        if (! $authUser->can('view:document')) {
            return false;
        }

        // Dokumen rahasia pimpinan memerlukan izin khusus atau merupakan pengunggah dokumen tersebut
        if ($document->visibility === VisibilityLevel::Confidential) {
            $canViewConfidential = $authUser->can('document.view_confidential') || $document->uploaded_by === $authUser->id;
            if (! $canViewConfidential) {
                return false;
            }
        }

        // Dokumen bertaraf publik atau berkas borang akreditasi dapat diakses luas oleh pemegang izin view
        if ($document->visibility === VisibilityLevel::Public || $document->is_borang) {
            return true;
        }

        // Pengguna dengan hak lintas unit (Rektorat, Warek, LPM, Super Admin)
        if ($authUser->can('document.bypass_unit_scope')) {
            return true;
        }

        // Unit tenancy scope: Dokumen unit sendiri, diunggah oleh pengguna, atau prodi bawahan dekan
        if ($document->unit_id === $authUser->unit_id || $document->uploaded_by === $authUser->id) {
            return true;
        }

        if ($authUser->hasRole('dekan') && $document->unit?->parent_id === $authUser->unit_id) {
            return true;
        }

        return false;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create:document');
    }

    public function update(AuthUser $authUser, Document $document): bool
    {
        if (! $authUser->can('update:document')) {
            return false;
        }

        // Jika dokumen sudah disahkan (Approved), staf biasa tidak boleh menyunting kecuali memiliki hak approval/bypass
        if ($document->status === DocumentStatus::Approved && ! $authUser->can('document.approve') && ! $authUser->can('document.bypass_unit_scope')) {
            return false;
        }

        if ($authUser->can('document.bypass_unit_scope')) {
            return true;
        }

        if ($document->unit_id === $authUser->unit_id || $document->uploaded_by === $authUser->id) {
            return true;
        }

        if ($authUser->hasRole('dekan') && $document->unit?->parent_id === $authUser->unit_id) {
            return true;
        }

        return false;
    }

    public function delete(AuthUser $authUser, Document $document): bool
    {
        if (! $authUser->can('delete:document')) {
            return false;
        }

        if ($authUser->can('document.bypass_unit_scope')) {
            return true;
        }

        if ($document->unit_id === $authUser->unit_id || $document->uploaded_by === $authUser->id) {
            return true;
        }

        if ($authUser->hasRole('dekan') && $document->unit?->parent_id === $authUser->unit_id) {
            return true;
        }

        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any:document');
    }

    public function restore(AuthUser $authUser, Document $document): bool
    {
        if (! $authUser->can('restore:document')) {
            return false;
        }

        if ($authUser->can('document.bypass_unit_scope')) {
            return true;
        }

        if ($document->unit_id === $authUser->unit_id || $document->uploaded_by === $authUser->id) {
            return true;
        }

        if ($authUser->hasRole('dekan') && $document->unit?->parent_id === $authUser->unit_id) {
            return true;
        }

        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any:document');
    }

    public function forceDelete(AuthUser $authUser, Document $document): bool
    {
        if (! $authUser->can('force_delete:document')) {
            return false;
        }

        if ($authUser->can('document.bypass_unit_scope')) {
            return true;
        }

        if ($document->unit_id === $authUser->unit_id || $document->uploaded_by === $authUser->id) {
            return true;
        }

        if ($authUser->hasRole('dekan') && $document->unit?->parent_id === $authUser->unit_id) {
            return true;
        }

        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any:document');
    }

    public function replicate(AuthUser $authUser, Document $document): bool
    {
        return $authUser->can('replicate:document');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder:document');
    }

    public function downloadPrivate(AuthUser $authUser, Document $document): bool
    {
        if (! $this->view($authUser, $document)) {
            return false;
        }

        return $authUser->can('document.download_private');
    }

    public function approve(AuthUser $authUser, Document $document): bool
    {
        if (! $authUser->can('document.approve')) {
            return false;
        }

        if ($authUser->can('document.bypass_unit_scope')) {
            return true;
        }

        if ($authUser->hasRole('dekan') && $document->unit?->parent_id === $authUser->unit_id) {
            return true;
        }

        return $document->unit_id === $authUser->unit_id;
    }
}
