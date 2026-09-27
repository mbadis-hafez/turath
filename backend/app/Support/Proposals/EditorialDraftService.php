<?php

namespace App\Support\Proposals;

use App\Enums\ProposalStatus;
use App\Enums\ReviewType;
use App\Enums\RevisionSource;
use App\Http\Requests\ArchiveItem\UpdateArchiveItemRequest;
use App\Http\Requests\Artist\SyncArtistEntriesRequest;
use App\Http\Requests\Artist\SyncArtistSocialLinksRequest;
use App\Http\Requests\Artist\UpdateArtistCurationRequest;
use App\Http\Requests\Artist\UpdateArtistRequest;
use App\Http\Requests\Artwork\UpdateArtworkRequest;
use App\Http\Requests\Event\EventRequest;
use App\Http\Requests\Event\SyncEventParticipantsRequest;
use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\ArtworkPipelineStage;
use App\Models\EditProposal;
use App\Models\Event;
use App\Models\ReviewQueueItem;
use App\Models\Revision;
use App\Models\User;
use App\Support\Curation\ArchiveItemSections;
use App\Support\Curation\ArtistSections;
use App\Support\Curation\ArtworkSections;
use App\Support\Curation\EventSections;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The editorial draft workflow: an editor stages section-structured edits in
 * an EditProposal row (status draft), submits it for review (pending), the
 * reviewer requests changes (changes_requested) or approves (applies every
 * section through the same services the direct-edit controllers use, in one
 * transaction and one revision).
 */
class EditorialDraftService
{
    private const OPEN_STATUSES = [
        ProposalStatus::Draft->value,
        ProposalStatus::Pending->value,
        ProposalStatus::ChangesRequested->value,
    ];

    /**
     * Create or replace the user's own draft for the record.
     *
     * @param  array<string, mixed>  $payload  section payloads keyed by section name
     */
    public function upsert(Model $record, User $user, array $payload, ?string $rationale): EditProposal
    {
        // Validation runs first so an invalid payload never displaces a draft.
        $mapped = $this->mapSections($record, $payload);
        $reviewType = $this->deriveReviewType($mapped);

        $open = $this->openProposals($record)->get();

        foreach ($open as $other) {
            if ($other->proposed_by_user_id !== $user->id) {
                throw new HttpResponseException(response()->json([
                    'message' => 'This record already has an open editorial draft by another user.',
                    'proposal_id' => $other->id,
                ], 409));
            }
        }

        /** @var EditProposal|null $mine */
        $mine = $open->firstWhere('proposed_by_user_id', $user->id);

        if ($mine !== null && $mine->status === ProposalStatus::Pending->value) {
            throw new HttpResponseException(response()->json([
                'message' => 'This draft is already awaiting review.',
                'proposal_id' => $mine->id,
            ], 409));
        }

        $attributes = [
            'payload' => $payload,
            'rationale' => $rationale ?? '',
            'review_type' => $reviewType->value,
        ];

        if ($mine !== null) {
            // Resubmission after changes_requested: fresh review stamps; the
            // old review_note stays visible to the editor until they resubmit.
            $mine->update([...$attributes, 'reviewed_by_user_id' => null, 'reviewed_at' => null]);

            return $mine;
        }

        return EditProposal::create([
            'citable_type' => $record::class,
            'citable_id' => $record->getKey(),
            'proposed_by_user_id' => $user->id,
            'status' => ProposalStatus::Draft->value,
            'field_diffs' => [],
            ...$attributes,
        ]);
    }

    /**
     * Send the user's draft to the review queue. Refuses when nothing in the
     * draft differs from the live record.
     */
    public function submit(Model $record, User $user): EditProposal
    {
        /** @var EditProposal|null $proposal */
        $proposal = $this->openProposals($record)
            ->where('proposed_by_user_id', $user->id)
            ->whereIn('status', [ProposalStatus::Draft->value, ProposalStatus::ChangesRequested->value])
            ->latest('created_at')
            ->first();
        abort_if($proposal === null, 404);

        $mapped = $this->mapSections($record, $proposal->payload ?? []);

        [$diffs, $differs] = $this->computeDiffs($record, $mapped);

        if (! $differs) {
            throw ValidationException::withMessages(['payload' => ['Nothing in this draft differs from the current record.']]);
        }

        $reviewType = $this->deriveReviewType($mapped);
        $rationale = trim($proposal->rationale ?? '');
        $note = $rationale !== '' ? mb_substr($rationale, 0, 255) : '(no rationale provided)';

        return DB::transaction(function () use ($proposal, $diffs, $reviewType, $user, $note) {
            $proposal->update([
                'status' => ProposalStatus::Pending->value,
                'field_diffs' => $diffs,
                'review_type' => $reviewType->value,
                'review_note' => null,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
            ]);

            ReviewQueueItem::create([
                'citable_type' => $proposal->citable_type,
                'citable_id' => $proposal->citable_id,
                'edit_proposal_id' => $proposal->id,
                'review_type' => $reviewType->value,
                'submitted_by_user_id' => $user->id,
                'note' => $note,
                'status' => 'pending',
            ]);

            return $proposal;
        });
    }

    public function requestChanges(EditProposal $proposal, User $reviewer, string $note): void
    {
        abort_unless($proposal->status === ProposalStatus::Pending->value, 422);

        DB::transaction(function () use ($proposal, $reviewer, $note) {
            $proposal->update([
                'status' => ProposalStatus::ChangesRequested->value,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
            $this->closeQueueItem($proposal, 'dismissed', $reviewer->id);
        });
    }

    /**
     * Apply every section of an approved-bound draft in one transaction, merge
     * the flat diffs into a single revision, and supersede the record's other
     * pending proposals.
     *
     * @throws ConflictRequired when flat fields drifted and the reviewer has not confirmed
     */
    public function apply(EditProposal $proposal, Model $record, User $reviewer, bool $confirmConflict): ?Revision
    {
        $conflicts = (new ProposalService)->conflicts($proposal, $record);
        if ($conflicts !== [] && ! $confirmConflict) {
            throw new ConflictRequired($conflicts);
        }
        unset($conflicts);

        return RevisionTracking::withoutTracking(fn () => DB::transaction(function () use ($proposal, $record, $reviewer) {
            try {
                $mapped = $this->mapSections($record, $proposal->payload ?? []);
            } catch (ValidationException $e) {
                // The record or its relations drifted since submission.
                throw new HttpException(409, 'The draft no longer validates against the current record.', $e);
            }

            $appliedDiffs = $this->applySections($record, $mapped, $reviewer, $proposal);

            $revision = $appliedDiffs !== []
                ? RecordUpdater::recordRevision($record, $appliedDiffs, RevisionSource::ApprovedProposal, $reviewer->id, $proposal->id)
                : null;

            $proposal->update([
                'status' => ProposalStatus::Approved->value,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'resulting_revision_id' => $revision?->id,
            ]);
            $this->closeQueueItem($proposal, 'acknowledged', $reviewer->id);
            $this->supersedeOtherPending($proposal, $reviewer->id);

            return $revision;
        }));
    }

    /**
     * Validate every present section against its endpoint FormRequest and
     * return the mapped/validated payloads keyed by section name. Public so
     * the reviewer-side diff builder maps payloads exactly like apply does.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function mapSections(Model $record, array $payload): array
    {
        $id = $record->getKey();
        $mapped = [];

        if ($record instanceof Artist) {
            $this->assertKnownSections($payload, ['fields', 'curation', 'educations', 'activities', 'social_links']);

            if (isset($payload['fields'])) {
                $mapped['fields'] = $this->validateSection(UpdateArtistRequest::class, 'artist', $id, $payload['fields'], 'fields', true);
            }
            if (isset($payload['curation'])) {
                $curation = $this->validateSection(UpdateArtistCurationRequest::class, 'artist', $id, $payload['curation'], 'curation');
                unset($curation['edit_summary']);
                $mapped['curation'] = $curation;
            }
            $entries = array_intersect_key($payload, array_flip(['educations', 'activities']));
            if ($entries !== []) {
                $validated = SectionValidator::validated(SyncArtistEntriesRequest::class, 'artist', $id, $entries);
                unset($validated['edit_summary']);
                if (array_key_exists('educations', $validated)) {
                    $mapped['educations'] = $validated['educations'];
                }
                if (array_key_exists('activities', $validated)) {
                    $mapped['activities'] = $validated['activities'];
                }
            }
            if (isset($payload['social_links'])) {
                $mapped['social_links'] = $this->validateSocialLinks($id, $payload['social_links']);
            }
        } elseif ($record instanceof Artwork) {
            $this->assertKnownSections($payload, ['fields', 'pipeline']);

            if (isset($payload['fields'])) {
                $mapped['fields'] = $this->validateSection(UpdateArtworkRequest::class, 'artwork', $id, $payload['fields'], 'fields', true);
            }
            if (isset($payload['pipeline'])) {
                $mapped['pipeline'] = $this->validatePipeline($payload['pipeline']);
            }
        } elseif ($record instanceof Event) {
            $this->assertKnownSections($payload, ['fields', 'participants']);

            if (isset($payload['fields'])) {
                $mapped['fields'] = $this->validateSection(EventRequest::class, 'event', $id, $payload['fields'], 'fields', true);
            }
            if (isset($payload['participants'])) {
                $validated = SectionValidator::validated(SyncEventParticipantsRequest::class, 'event', $id, ['participants' => $payload['participants']]);
                $mapped['participants'] = $validated['participants'] ?? [];
            }
        } elseif ($record instanceof ArchiveItem) {
            $this->assertKnownSections($payload, ['fields']);

            if (isset($payload['fields'])) {
                $mapped['fields'] = $this->validateSection(UpdateArchiveItemRequest::class, 'archive_item', $id, $payload['fields'], 'fields', true);
            }
        } else {
            throw ValidationException::withMessages(['payload' => ['This record type does not support editorial drafts.']]);
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $allowed
     */
    private function assertKnownSections(array $payload, array $allowed): void
    {
        $unknown = array_diff(array_keys($payload), $allowed);
        if ($unknown !== []) {
            throw ValidationException::withMessages(['payload' => ['Unknown draft sections: '.implode(', ', $unknown).'.']]);
        }
    }

    /**
     * Validate one section through its endpoint FormRequest, keying any
     * errors back into the draft's section namespace.
     *
     * @param  class-string<FormRequest>  $class
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateSection(string $class, string $routeParameter, int|string $recordId, array $payload, string $section, bool $map = false): array
    {
        try {
            return $map
                ? SectionValidator::mapped($class, $routeParameter, $recordId, $payload)
                : SectionValidator::validated($class, $routeParameter, $recordId, $payload);
        } catch (ValidationException $e) {
            $messages = [];
            foreach ($e->errors() as $key => $msgs) {
                $messages[str_starts_with($key, "{$section}.") ? $key : "{$section}.{$key}"] = $msgs;
            }
            throw ValidationException::withMessages($messages);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validateSocialLinks(int|string $recordId, mixed $links): array
    {
        try {
            $validated = SectionValidator::validated(SyncArtistSocialLinksRequest::class, 'artist', $recordId, ['links' => $links]);
        } catch (ValidationException $e) {
            // The endpoint body nests the list under `links`; a draft names the
            // section `social_links`, so key errors back into the draft shape.
            $messages = [];
            foreach ($e->errors() as $key => $msgs) {
                $messages['social_links.'.(str_starts_with($key, 'links.') ? substr($key, 6) : $key)] = $msgs;
            }
            throw ValidationException::withMessages($messages);
        }

        return $validated['links'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validatePipeline(mixed $stages): array
    {
        $validator = Validator::make(['pipeline' => $stages], [
            'pipeline' => ['required', 'array', 'max:20'],
            'pipeline.*.stage_key' => ['required', Rule::in(ArtworkPipelineStage::KEYS)],
            'pipeline.*.status' => ['sometimes', Rule::in(ArtworkPipelineStage::STATUSES)],
            'pipeline.*.note' => ['nullable', 'string', 'max:2000'],
            'pipeline.*.linked_file_id' => ['nullable', 'integer', 'exists:files,id'],
        ]);
        $validator->validate();

        return $validator->validated()['pipeline'];
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function deriveReviewType(array $mapped): ReviewType
    {
        // Child sections with an archivist character win outright.
        if (isset($mapped['pipeline']) || isset($mapped['curation']['contacts'])) {
            return ReviewType::ArchivistReview;
        }

        $flatColumns = array_keys($mapped['fields'] ?? []);
        if (isset($mapped['curation'])) {
            $flatColumns = [...$flatColumns, ...array_diff(array_keys($mapped['curation']), ['contacts'])];
        }

        return ProposableFields::reviewTypeFor($flatColumns);
    }

    /**
     * Flat sections diffed against the live record in the existing proposal
     * shape, plus whether any section (flat or child) changes anything.
     *
     * @param  array<string, mixed>  $mapped
     * @return array{0: array<string, array{old_value_at_proposal_time: mixed, proposed_value: mixed}>, 1: bool}
     */
    private function computeDiffs(Model $record, array $mapped): array
    {
        $diffs = [];
        $differs = false;

        foreach ([$mapped['fields'] ?? [], array_diff_key($mapped['curation'] ?? [], ['contacts' => true])] as $flat) {
            foreach ($flat as $column => $proposed) {
                $current = $record->getAttribute($column);
                if (FieldValues::differ($current, $proposed)) {
                    $diffs[$column] = [
                        'old_value_at_proposal_time' => FieldValues::normalize($current),
                        'proposed_value' => FieldValues::normalize($proposed),
                    ];
                    $differs = true;
                }
            }
        }

        $artist = new ArtistSections;
        $artwork = new ArtworkSections;
        $event = new EventSections;

        if ($record instanceof Artist
            && ($artist->entriesDiffer($record, $mapped['educations'] ?? null, $mapped['activities'] ?? null)
                || (isset($mapped['social_links']) && $artist->socialLinksDiffer($record, $mapped['social_links']))
                || (isset($mapped['curation']['contacts']) && $artist->contactsDiffer($record, $mapped['curation']['contacts'])))) {
            $differs = true;
        }

        if ($record instanceof Artwork && isset($mapped['pipeline']) && $artwork->pipelineDiffer($record, $mapped['pipeline'])) {
            $differs = true;
        }

        if ($record instanceof Event && isset($mapped['participants']) && $event->participantsDiffer($record, $mapped['participants'])) {
            $differs = true;
        }

        return [$diffs, $differs];
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, array{old: mixed, new: mixed}> merged flat diffs from the fields + curation sections
     */
    private function applySections(Model $record, array $mapped, User $reviewer, EditProposal $proposal): array
    {
        $applied = [];

        if ($record instanceof Artist) {
            if (isset($mapped['fields'])) {
                $applied += (new ArtistSections)->applyFields($record, $mapped['fields']);
            }
            if (isset($mapped['curation'])) {
                $mapped['curation']['edit_summary'] = 'Approved edit proposal '.$proposal->id;
                $applied += (new ArtistSections)->applyCuration($record, $mapped['curation'], $reviewer);
            }
            if (isset($mapped['educations']) || isset($mapped['activities'])) {
                (new ArtistSections)->syncEntries($record, $mapped['educations'] ?? null, $mapped['activities'] ?? null);
            }
            if (isset($mapped['social_links'])) {
                (new ArtistSections)->syncSocialLinks($record, $mapped['social_links']);
            }
        } elseif ($record instanceof Artwork) {
            if (isset($mapped['fields'])) {
                $applied += (new ArtworkSections)->applyFields($record, $mapped['fields']);
            }
            if (isset($mapped['pipeline'])) {
                (new ArtworkSections)->applyPipeline($record, $mapped['pipeline'], $reviewer);
            }
        } elseif ($record instanceof Event) {
            if (isset($mapped['fields'])) {
                $applied += (new EventSections)->applyFields($record, $mapped['fields']);
            }
            if (isset($mapped['participants'])) {
                (new EventSections)->syncParticipants($record, $mapped['participants']);
            }
        } elseif ($record instanceof ArchiveItem && isset($mapped['fields'])) {
            $applied += (new ArchiveItemSections)->applyFields($record, $mapped['fields']);
        }

        return $applied;
    }

    /**
     * @return Builder<EditProposal>
     */
    private function openProposals(Model $record): Builder
    {
        return EditProposal::query()
            ->where('citable_type', $record::class)
            ->where('citable_id', $record->getKey())
            ->whereIn('status', self::OPEN_STATUSES);
    }

    private function closeQueueItem(EditProposal $proposal, string $status, int $actedByUserId): void
    {
        ReviewQueueItem::where('edit_proposal_id', $proposal->id)
            ->update(['status' => $status, 'acted_by_user_id' => $actedByUserId, 'acted_at' => now()]);
    }

    /** Child sections have no per-field overlap notion: every other pending proposal on the record is stale. */
    private function supersedeOtherPending(EditProposal $proposal, int $actedByUserId): void
    {
        EditProposal::query()
            ->where('citable_type', $proposal->citable_type)
            ->where('citable_id', $proposal->citable_id)
            ->where('status', ProposalStatus::Pending->value)
            ->where('id', '!=', $proposal->id)
            ->get()
            ->each(function (EditProposal $other) use ($actedByUserId) {
                $other->update(['status' => ProposalStatus::Superseded->value]);
                $this->closeQueueItem($other, 'dismissed', $actedByUserId);
            });
    }
}
