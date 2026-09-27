<?php

namespace App\Enums;

use App\Models\User;

enum ReviewType: string
{
    case ArchivistReview = 'archivist_review';
    case DataAudit = 'data_audit';
    case SecondSourceNeeded = 'second_source_needed';
    case EditorialReview = 'editorial_review';
    case MaterialIntake = 'material_intake';

    /** The review_queue.* permission that gates working this queue. */
    public function permission(): string
    {
        return 'review_queue.'.$this->value;
    }

    /**
     * The queues a user can work, from their review_queue.* permissions —
     * the same scoping /proposals applies (ProposalController::reviewableTypes).
     *
     * @return array<int, string>
     */
    public static function reviewableBy(?User $user): array
    {
        return array_map(
            fn (self $type) => $type->value,
            array_filter(self::cases(), fn (self $type) => $user?->can($type->permission()) ?? false),
        );
    }
}
