<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One contact value a reviewer proposed from a document, and what became of
 * it — see the migration. Never writes ArtistContact: the editorial draft it
 * travels in does, once a second reviewer approves it, and these rows follow
 * that draft's state (EditProposalObserver).
 */
class ArtistContactProposal extends Model
{
    public const FIELDS = ['email', 'phone', 'address'];

    public const ACTION_NEW_CONTACT = 'new_contact';

    public const ACTION_UPDATE_CONTACT = 'update_contact';

    public const STATUS_PENDING = 'pending';

    /** A reviewer sent the draft back; the proposer can propose again from the document. */
    public const STATUS_CHANGES_REQUESTED = 'changes_requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** Replaced before anyone decided it — see SUPERSEDED_* for why. */
    public const STATUS_SUPERSEDED = 'superseded';

    public const OPEN_STATUSES = [self::STATUS_PENDING, self::STATUS_CHANGES_REQUESTED];

    /** The proposer proposed again from the same document. */
    public const SUPERSEDED_BY_NEWER_PROPOSAL = 'newer_proposal';

    /** The draft carrying it was rewritten elsewhere (the artist page), so it no longer holds this value. */
    public const SUPERSEDED_DRAFT_REWRITTEN = 'draft_rewritten';

    /** Another proposal for the same artist was approved first. */
    public const SUPERSEDED_OTHER_APPROVED = 'other_proposal_approved';

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposed_value' => 'encrypted',
            'replaces_existing' => 'boolean',
            'source_bbox' => 'array',
            'machine_suggestion' => 'array',
            'has_correction_mark' => 'boolean',
            'edited_by_proposer' => 'boolean',
            'proposed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * @return BelongsTo<Artist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * @return BelongsTo<EditProposal, $this>
     */
    public function editProposal(): BelongsTo
    {
        return $this->belongsTo(EditProposal::class);
    }

    /**
     * @return BelongsTo<ArtistContact, $this>
     */
    public function targetContact(): BelongsTo
    {
        return $this->belongsTo(ArtistContact::class, 'target_contact_id');
    }

    /**
     * @return BelongsTo<FileOcrRegion, $this>
     */
    public function sourceRegion(): BelongsTo
    {
        return $this->belongsTo(FileOcrRegion::class, 'source_region_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
