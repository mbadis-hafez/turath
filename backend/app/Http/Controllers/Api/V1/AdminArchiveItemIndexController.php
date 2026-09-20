<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ArchiveItem;
use App\Models\RecordCompleteness;
use App\Models\ReviewQueueItem;
use App\Models\User;
use App\Support\ArabicNormalizer;
use App\ValueObjects\PartialDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminArchiveItemIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'item_type' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'in:draft,published,hidden,incomplete,under_review'],
            'rights_status' => ['nullable', 'in:public_domain,licensed,all_rights_reserved,unknown'],
            'year_from' => ['nullable', 'integer'],
            'year_to' => ['nullable', 'integer'],
            'mine' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $userId = (string) $request->user()?->getKey();

        $query = ArchiveItem::query()->with(['links.linkable']);

        if (! empty($data['item_type'])) {
            $query->where('item_type', $data['item_type']);
        }
        if (! empty($data['rights_status'])) {
            $query->where('rights_status', $data['rights_status']);
        }
        if (! empty($data['year_from'])) {
            $query->where(fn (Builder $q) => $q->whereNull('content_year_to')->orWhere('content_year_to', '>=', $data['year_from']));
        }
        if (! empty($data['year_to'])) {
            $query->where(fn (Builder $q) => $q->whereNull('content_year_from')->orWhere('content_year_from', '<=', $data['year_to']));
        }
        if ($request->boolean('mine')) {
            $query->whereIn('id', $this->createdBy($userId));
        }
        match ($data['status'] ?? null) {
            'draft', 'published', 'hidden' => $query->where('publication_status', $data['status']),
            'incomplete' => $query->whereIn('id', $this->incompleteIds()),
            'under_review' => $query->whereIn('id', $this->underReviewIds()),
            default => null,
        };
        if (! empty($data['q'])) {
            foreach (array_filter(preg_split('/\s+/u', ArabicNormalizer::normalize(mb_substr($data['q'], 0, 100))) ?: []) as $token) {
                $query->where('search_text', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $token).'%');
            }
        }

        $paginated = $query->orderByDesc('id')->paginate((int) ($data['per_page'] ?? 20));
        $ids = $paginated->pluck('id');

        $completeness = RecordCompleteness::where('citable_type', ArchiveItem::class)->whereIn('citable_id', $ids)->get()->keyBy('citable_id');
        $review = ReviewQueueItem::where('citable_type', ArchiveItem::class)->where('status', 'pending')->whereIn('citable_id', $ids)->pluck('citable_id')->flip();

        $rows = $paginated->getCollection()->map(function (ArchiveItem $i) use ($completeness, $review) {
            $c = $completeness->get($i->id);
            $gaps = count($c->blocking_gap_field_keys ?? []);

            return [
                'id' => $i->id,
                'legacy_ref' => $i->legacy_ref,
                'item_type' => $i->item_type,
                'title' => ['ar' => $i->title_ar, 'en' => $i->title_en],
                'date' => $this->dateLabel($i->getAttribute('content')),
                'source' => [
                    'name' => ['ar' => $i->rights_holder_ar ?? $i->publication_name_ar ?? $i->creator_name, 'en' => $i->rights_holder_en ?? $i->publication_name_en ?? $i->creator_name],
                    'rights_status' => $i->rights_status,
                ],
                'publication_status' => $i->publication_status,
                'incomplete' => $gaps > 0,
                'gap_count' => $gaps,
                'under_review' => $review->has($i->id),
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(), 'total' => $paginated->total(),
                'mine_count' => ArchiveItem::whereIn('id', $this->createdBy($userId))->count(),
                'incomplete_count' => ArchiveItem::whereIn('id', $this->incompleteIds())->count(),
            ],
        ]);
    }

    /** @return \Illuminate\Database\Query\Builder */
    private function createdBy(string $userId)
    {
        return DB::table('activity_log')->select('subject_id')
            ->where('subject_type', ArchiveItem::class)->where('event', 'created')
            ->where('causer_type', User::class)->where('causer_id', $userId);
    }

    /** @return \Illuminate\Database\Query\Builder */
    private function incompleteIds()
    {
        return DB::table('record_completeness')->select('citable_id')
            ->where('citable_type', ArchiveItem::class)->whereJsonLength('blocking_gap_field_keys', '>', 0);
    }

    /** @return \Illuminate\Database\Query\Builder */
    private function underReviewIds()
    {
        return DB::table('review_queue_items')->select('citable_id')
            ->where('citable_type', ArchiveItem::class)->where('status', 'pending');
    }

    private function dateLabel(mixed $date): ?string
    {
        if (! $date instanceof PartialDate) {
            return null;
        }

        return $date->display ?? ($date->yearFrom !== null ? (string) $date->yearFrom : null);
    }
}
