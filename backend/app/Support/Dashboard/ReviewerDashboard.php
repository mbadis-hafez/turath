<?php

namespace App\Support\Dashboard;

use App\Enums\ReviewType;
use App\Http\Resources\ReviewQueueItemResource;
use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\EditProposal;
use App\Models\MaterialSubmission;
use App\Models\ReviewQueueItem;
use App\Models\SourceConflict;
use App\Models\User;
use App\Support\Completeness\CitableTypeResolver;
use App\Support\Curation\ArtistCurationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates the reviewer dashboard for exactly one user. Unlike
 * EditorDashboard (self-authorship-scoped: "my records"), everything here
 * is scoped by which review_queue.* permissions the user holds
 * (ReviewType::reviewableBy) — the same scoping ProposalController and
 * ReviewQueueIndexController already apply. A user with no review_queue.*
 * permission gets an all-zero payload, never an error.
 *
 * Completeness/Verification/Review/Publication stay separate concepts:
 * completeness_pct/severity come from the materialized record_completeness
 * table (never recalculated here); verification issues come from
 * ArtistCurationService::verifyErrors() filtered to non-completeness keys;
 * review state comes from EditProposal/ReviewQueueItem status; publication
 * state is never inferred from an approved review.
 */
class ReviewerDashboard
{
    /** Entity types that have record_completeness rows. */
    private const CITABLE_TYPES = [
        'artist' => Artist::class,
        'artwork' => Artwork::class,
        'archive_item' => ArchiveItem::class,
    ];

    private const PREVIEW_LIMIT = 8;

    private const PRIORITY_LIMIT = 3;

    public function forUser(User $user): array
    {
        $reviewTypes = ReviewType::reviewableBy($user);

        if ($reviewTypes === []) {
            return $this->emptyPayload($user);
        }

        $needsReview = $this->needsReview($reviewTypes);
        $returned = $this->returnedContent($reviewTypes);
        $verificationIssues = $this->verificationIssues($user);

        return [
            'summary' => [
                'waiting_review' => $this->pendingQueueQuery($reviewTypes)->count(),
                'verification_issues' => count($verificationIssues),
                'changes_returned' => $this->returnedContentQuery($reviewTypes)->count(),
                'reviewed_today' => $this->reviewedTodayCount($user),
            ],
            'needs_review' => $needsReview,
            'priority_queue' => array_slice($needsReview, 0, self::PRIORITY_LIMIT),
            'verification_issues' => $verificationIssues,
            'returned_content' => $returned,
            'pipeline' => $this->pipeline($reviewTypes),
            'content_overview' => $this->contentOverview($reviewTypes),
            'recently_reviewed' => $this->recentlyReviewed($user),
            'recent_activity' => $this->recentActivity($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(User $user): array
    {
        return [
            'summary' => [
                'waiting_review' => 0,
                'verification_issues' => 0,
                'changes_returned' => 0,
                'reviewed_today' => 0,
            ],
            'needs_review' => [],
            'priority_queue' => [],
            'verification_issues' => [],
            'returned_content' => [],
            'pipeline' => ['ready_for_review' => 0, 'changes_requested' => 0, 'approved' => 0, 'rejected' => 0],
            'content_overview' => ['artists' => 0, 'artworks' => 0, 'archive_items' => 0, 'material_submissions' => 0],
            'recently_reviewed' => $this->recentlyReviewed($user),
            'recent_activity' => $this->recentActivity($user),
        ];
    }

    /**
     * @param  array<int, string>  $reviewTypes
     * @return Builder<ReviewQueueItem>
     */
    private function pendingQueueQuery(array $reviewTypes): Builder
    {
        return ReviewQueueItem::query()
            ->whereIn('review_type', $reviewTypes)
            ->where('status', 'pending');
    }

    /**
     * @param  array<int, string>  $reviewTypes
     * @return array<int, array<string, mixed>>
     */
    private function needsReview(array $reviewTypes): array
    {
        $items = $this->pendingQueueQuery($reviewTypes)
            ->with(['submittedBy', 'citable'])
            ->orderBy('submitted_at')
            ->limit(self::PREVIEW_LIMIT)
            ->get();

        $completeness = $this->completenessFor($items);

        return $items->map(function (ReviewQueueItem $item) use ($completeness) {
            $row = (new ReviewQueueItemResource($item))->toArray(request());
            $key = "{$item->citable_type}:{$item->citable_id}";
            $c = $completeness[$key] ?? null;

            if ($item->citable_type === MaterialSubmission::class) {
                $row['citable_type'] = 'material_submission';
            }

            $row['completeness_pct'] = $c !== null ? (int) $c->completeness_pct : null;
            $row['severity'] = $c->severity ?? null;
            $row['verification_status'] = $item->citable instanceof Artist ? $item->citable->verified_status : null;

            return $row;
        })->all();
    }

    /**
     * @param  array<int, string>  $reviewTypes
     * @return Builder<EditProposal>
     */
    private function returnedContentQuery(array $reviewTypes): Builder
    {
        return EditProposal::query()
            ->whereIn('review_type', $reviewTypes)
            ->where('status', 'pending')
            ->whereNotNull('review_note');
    }

    /**
     * @param  array<int, string>  $reviewTypes
     * @return array<int, array<string, mixed>>
     */
    private function returnedContent(array $reviewTypes): array
    {
        $proposals = $this->returnedContentQuery($reviewTypes)
            ->with(['proposedBy', 'citable'])
            ->latest('created_at')
            ->limit(self::PREVIEW_LIMIT)
            ->get();

        return $proposals->map(fn (EditProposal $proposal) => [
            'id' => $proposal->id,
            'citable_type' => CitableTypeResolver::segmentFor($proposal->citable_type),
            'citable_id' => $proposal->citable_id,
            'title' => $this->titleFor($proposal->citable),
            'proposed_by' => $proposal->proposedBy ? ['id' => $proposal->proposedBy->id, 'name' => $proposal->proposedBy->name] : null,
            'review_type' => $proposal->review_type,
            'previous_review_note' => $proposal->review_note,
        ])->all();
    }

    /**
     * Artist rows whose completeness is already 100% (so this is a
     * verification gap, not a completeness gap) but whose verification is
     * unresolved per ArtistCurationService::verifyErrors, plus open source
     * conflicts if the reviewer holds source_conflicts.resolve.
     *
     * @return array<int, array<string, mixed>>
     */
    private function verificationIssues(User $user): array
    {
        $issues = [];

        $candidates = DB::table('artists')
            ->join('record_completeness as rc', function ($join) {
                $join->on('rc.citable_id', '=', 'artists.id')->where('rc.citable_type', '=', Artist::class);
            })
            ->whereIn('artists.verified_status', ['unverified', 'disputed'])
            ->where('rc.completeness_pct', 100)
            ->orderByDesc('artists.updated_at')
            ->limit(40)
            ->get(['artists.id', 'artists.name_ar', 'artists.name_en', 'artists.slug', 'artists.verified_status']);

        $service = new ArtistCurationService;
        foreach ($candidates as $row) {
            if (count($issues) >= self::PREVIEW_LIMIT) {
                break;
            }

            $artist = Artist::find($row->id);
            if ($artist === null) {
                continue;
            }

            $errors = array_filter(
                $service->verifyErrors($artist),
                fn (string $key) => ! str_starts_with($key, 'data.'),
                ARRAY_FILTER_USE_KEY,
            );
            if ($errors === []) {
                continue;
            }

            $issues[] = [
                'kind' => 'verification',
                'citable_type' => CitableTypeResolver::segmentFor(Artist::class),
                'id' => (int) $row->id,
                'slug' => $row->slug,
                'title' => ['ar' => $row->name_ar, 'en' => $row->name_en],
                'completeness_pct' => 100,
                'verification_status' => $row->verified_status,
                'issues' => array_map(fn (array $messages) => $messages[0], array_values($errors)),
            ];
        }

        if ($user->can('source_conflicts.resolve')) {
            $remaining = self::PREVIEW_LIMIT - count($issues);
            if ($remaining > 0) {
                $conflicts = SourceConflict::query()
                    ->where('status', 'open')
                    ->with('citable')
                    ->latest('created_at')
                    ->limit($remaining)
                    ->get();

                foreach ($conflicts as $conflict) {
                    $issues[] = [
                        'kind' => 'conflict',
                        'citable_type' => CitableTypeResolver::segmentFor($conflict->citable_type),
                        'id' => $conflict->citable_id,
                        'slug' => null,
                        'title' => $this->titleFor($conflict->citable),
                        'field_key' => $conflict->field_key,
                        'conflict_id' => $conflict->id,
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * @param  array<int, string>  $reviewTypes
     * @return array{ready_for_review: int, changes_requested: int, approved: int, rejected: int}
     */
    private function pipeline(array $reviewTypes): array
    {
        $since = Carbon::now()->subDays(30);

        return [
            'ready_for_review' => EditProposal::query()
                ->whereIn('review_type', $reviewTypes)
                ->where('status', 'pending')
                ->whereNull('review_note')
                ->count(),
            'changes_requested' => EditProposal::query()
                ->whereIn('review_type', $reviewTypes)
                ->where('status', 'changes_requested')
                ->count(),
            'approved' => EditProposal::query()
                ->whereIn('review_type', $reviewTypes)
                ->where('status', 'approved')
                ->where('reviewed_at', '>=', $since)
                ->count(),
            'rejected' => EditProposal::query()
                ->whereIn('review_type', $reviewTypes)
                ->where('status', 'rejected')
                ->where('reviewed_at', '>=', $since)
                ->count(),
        ];
    }

    /**
     * @param  array<int, string>  $reviewTypes
     * @return array{artists: int, artworks: int, archive_items: int, material_submissions: int}
     */
    private function contentOverview(array $reviewTypes): array
    {
        $counts = $this->pendingQueueQuery($reviewTypes)
            ->select('citable_type', DB::raw('count(*) as c'))
            ->groupBy('citable_type')
            ->pluck('c', 'citable_type');

        return [
            'artists' => (int) ($counts[Artist::class] ?? 0),
            'artworks' => (int) ($counts[Artwork::class] ?? 0),
            'archive_items' => (int) ($counts[ArchiveItem::class] ?? 0),
            'material_submissions' => (int) ($counts[MaterialSubmission::class] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentlyReviewed(User $user): array
    {
        $proposals = EditProposal::query()
            ->where('reviewed_by_user_id', $user->id)
            ->whereIn('status', ['approved', 'rejected', 'changes_requested'])
            ->with('citable')
            ->latest('reviewed_at')
            ->limit(self::PREVIEW_LIMIT)
            ->get();

        return $proposals->map(fn (EditProposal $proposal) => [
            'id' => $proposal->id,
            'citable_type' => CitableTypeResolver::segmentFor($proposal->citable_type),
            'citable_id' => $proposal->citable_id,
            'title' => $this->titleFor($proposal->citable),
            'decision' => $proposal->status,
            'reviewed_at' => $proposal->reviewed_at?->toIso8601String(),
        ])->all();
    }

    private function reviewedTodayCount(User $user): int
    {
        return EditProposal::query()
            ->where('reviewed_by_user_id', $user->id)
            ->whereIn('status', ['approved', 'rejected', 'changes_requested'])
            ->whereDate('reviewed_at', Carbon::today())
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(User $user): array
    {
        $rows = DB::table('activity_log')
            ->where('causer_type', User::class)
            ->where('causer_id', (string) $user->id)
            ->whereIn('subject_type', [Artist::class, Artwork::class, ArchiveItem::class])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::PREVIEW_LIMIT)
            ->get(['event', 'subject_type', 'subject_id', 'created_at', 'properties']);

        $items = [];
        foreach ($rows as $row) {
            $entityType = CitableTypeResolver::segmentFor($row->subject_type);
            $record = $entityType !== null ? CitableTypeResolver::resolveRecord($entityType, $row->subject_id) : null;
            if ($record === null) {
                continue;
            }

            $items[] = [
                'citable_type' => $entityType,
                'id' => (int) $row->subject_id,
                'title' => $this->titleFor($record),
                'event' => $row->event,
                'created_at' => Carbon::parse($row->created_at)->toIso8601String(),
            ];
        }

        return $items;
    }

    /**
     * @param  Collection<int, ReviewQueueItem>  $items
     * @return array<string, object>
     */
    private function completenessFor(Collection $items): array
    {
        $idsByType = [];
        foreach ($items as $item) {
            $idsByType[$item->citable_type][] = $item->citable_id;
        }

        $completeness = [];
        foreach ($idsByType as $citableType => $ids) {
            if (! in_array($citableType, self::CITABLE_TYPES, true)) {
                continue;
            }
            $rows = DB::table('record_completeness')
                ->where('citable_type', $citableType)
                ->whereIn('citable_id', $ids)
                ->get(['citable_id', 'completeness_pct', 'severity']);
            foreach ($rows as $row) {
                $completeness["{$citableType}:{$row->citable_id}"] = $row;
            }
        }

        return $completeness;
    }

    /**
     * @return array{ar: string|null, en: string|null}|null
     */
    private function titleFor(?object $record): ?array
    {
        return match (true) {
            $record instanceof Artist => ['ar' => $record->name_ar, 'en' => $record->name_en],
            $record instanceof Artwork, $record instanceof ArchiveItem => ['ar' => $record->title_ar, 'en' => $record->title_en],
            default => null,
        };
    }
}
