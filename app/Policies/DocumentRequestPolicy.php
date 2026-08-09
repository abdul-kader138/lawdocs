<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DocumentRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class DocumentRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_document::request');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DocumentRequest $documentRequest): bool
    {
        return $user->can('view_document::request');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_document::request');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DocumentRequest $documentRequest): bool
    {
        return $user->can('update_document::request');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DocumentRequest $documentRequest): bool
    {
        return $user->can('delete_document::request');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_document::request');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, DocumentRequest $documentRequest): bool
    {
        return $user->can('force_delete_document::request');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_document::request');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, DocumentRequest $documentRequest): bool
    {
        return $user->can('restore_document::request');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_document::request');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, DocumentRequest $documentRequest): bool
    {
        return $user->can('replicate_document::request');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_document::request');
    }

    /**
     * Determine whether the user can download the generated .docx.
     * Deliberately mirrors view() — per the confirmed product decision, all
     * staff who can see a request can also download it (small trusted team).
     */
    public function download(User $user, DocumentRequest $documentRequest): bool
    {
        return $this->view($user, $documentRequest);
    }
}
