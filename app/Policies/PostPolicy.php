<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PostPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any:post');
    }

    public function view(AuthUser $authUser, Post $post): bool
    {
        if (! $authUser->can('view:post')) {
            return false;
        }

        // Artikel yang sudah terbit dapat dilihat oleh semua pengguna yang berhak melihat berita
        if ($post->status === PostStatus::Published) {
            return true;
        }

        // Humas dan Administrator memiliki akses peninjauan lintas unit
        if ($authUser->can('post.moderate_humas') || $authUser->can('document.bypass_unit_scope')) {
            return true;
        }

        // Tenancy Unit: Artikel unit sendiri, penulis sendiri, atau prodi binaan dekan
        if ($post->unit_id === $authUser->unit_id || $post->author_id === $authUser->id) {
            return true;
        }

        if ($authUser->hasRole('dekan') && $post->unit?->parent_id === $authUser->unit_id) {
            return true;
        }

        return false;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create:post');
    }

    public function update(AuthUser $authUser, Post $post): bool
    {
        if (! $authUser->can('update:post')) {
            return false;
        }

        // Humas dan Administrator memiliki hak kurasi penuh
        if ($authUser->can('post.moderate_humas') || $authUser->can('document.bypass_unit_scope')) {
            return true;
        }

        // Artikel yang sudah tayang publik (Published) hanya dapat diedit oleh tim Humas/Admin
        if ($post->status === PostStatus::Published && ! $authUser->can('post.moderate_humas')) {
            return false;
        }

        // Penulis dan Staf Unit yang sama
        if ($post->unit_id === $authUser->unit_id || $post->author_id === $authUser->id) {
            return true;
        }

        if ($authUser->hasRole('dekan') && $post->unit?->parent_id === $authUser->unit_id) {
            return true;
        }

        return false;
    }

    public function delete(AuthUser $authUser, Post $post): bool
    {
        if (! $authUser->can('delete:post')) {
            return false;
        }

        if ($authUser->can('post.moderate_humas') || $authUser->can('document.bypass_unit_scope')) {
            return true;
        }

        // Staf hanya dapat menghapus artikel draf/milik unitnya sendiri
        if ($post->unit_id === $authUser->unit_id || $post->author_id === $authUser->id) {
            return true;
        }

        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any:post');
    }

    public function restore(AuthUser $authUser, Post $post): bool
    {
        if (! $authUser->can('restore:post')) {
            return false;
        }

        return $authUser->can('post.moderate_humas') || $authUser->can('document.bypass_unit_scope');
    }

    public function forceDelete(AuthUser $authUser, Post $post): bool
    {
        return $authUser->can('force_delete:post');
    }
}
