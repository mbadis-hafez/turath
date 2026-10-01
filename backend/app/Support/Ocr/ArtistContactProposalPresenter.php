<?php

namespace App\Support\Ocr;

use App\Models\ArtistContactProposal;
use App\Models\EditProposal;
use App\Models\File;
use App\Models\User;

/**
 * Document-sourced contact proposals for two readers: the archive page, where
 * a proposer follows what became of each value, and the review queue, where
 * the approving reviewer sees where each value came from.
 *
 * Existing contact values are internal-only (D100): the value a proposal
 * would replace is shown only to users who can see it on the artist page
 * (artists.manage), and only while the proposal is undecided — once replaced
 * it is not kept anywhere. Crops need archive.manage, like the crop route.
 */
class ArtistContactProposalPresenter
{
    /**
     * Every value proposed from this document, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function forFile(File $file, User $viewer): array
    {
        $proposals = ArtistContactProposal::query()
            ->with(['targetContact', 'sourceRegion', 'proposedBy', 'reviewedBy'])
            ->where('file_id', $file->id)
            ->orderByDesc('id')
            ->get();

        return $proposals->map(fn (ArtistContactProposal $p) => $this->present($p, $viewer, true))->values()->all();
    }

    /**
     * The documents behind an editorial draft, for the reviewer deciding it —
     * null when the draft wasn't proposed from a document.
     *
     * @return list<array<string, mixed>>|null
     */
    public function evidence(EditProposal $proposal, User $viewer): ?array
    {
        $proposals = ArtistContactProposal::query()
            ->with(['file.archiveItem', 'sourceRegion', 'proposedBy', 'reviewedBy'])
            ->where('edit_proposal_id', $proposal->id)
            // Earlier rounds the draft no longer carries are history, not evidence.
            ->where(fn ($q) => $q->where('status', '!=', ArtistContactProposal::STATUS_SUPERSEDED)
                ->orWhere('superseded_reason', ArtistContactProposal::SUPERSEDED_OTHER_APPROVED))
            ->orderBy('id')
            ->get();

        if ($proposals->isEmpty()) {
            return null;
        }

        return $proposals->groupBy('file_id')->map(function ($group) use ($viewer) {
            $item = $group->first()?->file?->archiveItem;

            return [
                'file_id' => $group->first()?->file_id,
                'archive_item' => $item === null ? null : [
                    'id' => $item->id,
                    'legacy_ref' => $item->legacy_ref,
                    'title' => ['ar' => $item->title_ar, 'en' => $item->title_en],
                ],
                'can_open_document' => $viewer->can('archive.manage'),
                // The section diff already shows the reviewer every value; this is where they came from.
                'values' => $group->map(fn (ArtistContactProposal $p) => $this->present($p, $viewer, false))->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ArtistContactProposal $p, User $viewer, bool $withCurrent): array
    {
        $contact = $p->targetContact;
        $showCurrent = $withCurrent
            && $p->isOpen()
            && $p->action === ArtistContactProposal::ACTION_UPDATE_CONTACT
            && $viewer->can('artists.manage');
        $region = $p->sourceRegion;

        return [
            'id' => $p->id,
            'field' => $p->field,
            'action' => $p->action,
            'target_contact_id' => $p->target_contact_id,
            'proposed_value' => $p->proposed_value,
            'current_value' => $showCurrent ? $contact?->getAttribute($p->field) : null,
            'current_value_shown' => $showCurrent && $contact !== null,
            'replaces_existing' => $p->replaces_existing,
            'status' => $p->status,
            'superseded_reason' => $p->superseded_reason,
            'edit_proposal_id' => $p->edit_proposal_id,
            'source' => [
                'file_id' => $p->file_id,
                'page' => $p->source_page,
                'region_id' => $region?->id,
                'bbox' => $p->source_bbox,
                'label' => $p->source_label,
                'has_crop' => $region?->crop_path !== null && $viewer->can('archive.manage'),
            ],
            'extraction_method' => $p->extraction_method,
            'confidence' => $p->confidence,
            'machine_suggestion' => $p->machine_suggestion,
            'has_correction_mark' => $p->has_correction_mark,
            'edited_by_proposer' => $p->edited_by_proposer,
            'proposed_by' => $p->proposedBy === null ? null : ['id' => $p->proposedBy->id, 'name' => $p->proposedBy->name],
            'proposed_at' => $p->proposed_at->toIso8601String(),
            'reviewed_by' => $p->reviewedBy === null ? null : ['id' => $p->reviewedBy->id, 'name' => $p->reviewedBy->name],
            'reviewed_at' => $p->reviewed_at?->toIso8601String(),
            'review_note' => $p->review_note,
        ];
    }
}
