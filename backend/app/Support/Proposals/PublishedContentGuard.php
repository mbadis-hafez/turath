<?php

namespace App\Support\Proposals;

use App\Models\ContentPermissionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * "A Reviewer must never be able to directly modify the publicly published
 * version without going through the required approval workflow" — and
 * neither may an Editor; only Admin bypasses this freely.
 */
class PublishedContentGuard
{
    public static function isAdminTier(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('superadmin');
    }

    /**
     * For a holder of the record's manage permission (Editor/Admin): direct
     * writes are fine on an unpublished record, same as always; once
     * published, only Admin keeps that direct access — an Editor's route to
     * a published record is the EditProposal pipeline instead.
     */
    public static function manageHolderMayActDirectly(User $user, Model $record): bool
    {
        return self::isAdminTier($user) || $record->getAttribute('publication_status') !== 'published';
    }

    /**
     * For a Reviewer (who holds no manage permission at all): the one direct
     * action they're allowed is exactly what an Admin approved a request for,
     * on that exact record — e.g. a delete, once Admin signed off on the
     * delete request. Editing goes through the proposal pipeline instead
     * (see ProposalController::store's own approved-request check), never
     * through this direct path, even once approved.
     *
     * @param  'edit'|'delete'  $requestType
     */
    public static function hasApprovedRequest(User $user, Model $record, string $requestType): bool
    {
        return ContentPermissionRequest::query()
            ->where('citable_type', $record::class)
            ->where('citable_id', $record->getKey())
            ->where('requested_by_user_id', $user->id)
            ->where('request_type', $requestType)
            ->where('status', 'approved')
            ->exists();
    }
}
