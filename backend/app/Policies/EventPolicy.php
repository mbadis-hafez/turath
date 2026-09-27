<?php

namespace App\Policies;

use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Models\User;
use App\Support\Proposals\PublishedContentGuard;
use App\Support\Proposals\ReviewerScope;

/**
 * Event previously had no dedicated policy — routes/EventController checked
 * `events.manage` inline instead. This gives it the same shape as
 * Artist/Artwork/ArchiveItem so it can be wired into routes the same way.
 */
class EventPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Event $event): bool
    {
        if ($event->trashed()) {
            return $user?->can('events.manage') ?? false;
        }

        if ($event->publication_status !== PublicationStatus::Published->value) {
            return ($user?->can('events.manage') ?? false) || ReviewerScope::canReview($user);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('events.manage');
    }

    public function update(User $user, Event $event): bool
    {
        return $user->can('events.manage') && PublishedContentGuard::manageHolderMayActDirectly($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        if ($user->can('events.manage')) {
            return PublishedContentGuard::manageHolderMayActDirectly($user, $event);
        }

        return PublishedContentGuard::hasApprovedRequest($user, $event, 'delete');
    }

    public function restore(User $user, ?Event $event = null): bool
    {
        return $user->can('events.manage');
    }

    public function publish(User $user, Event $event): bool
    {
        return $user->can('events.publish');
    }

    public function requestEdit(User $user, Event $event): bool
    {
        return ReviewerScope::canReview($user) && ! $user->can('events.manage');
    }

    public function requestDelete(User $user, Event $event): bool
    {
        return ReviewerScope::canReview($user) && ! $user->can('events.manage');
    }
}
