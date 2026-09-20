<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\RecordCompleteness;
use App\Support\ArabicNormalizer;
use App\Support\Events\EventPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEventIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'event_type' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'in:draft,published,hidden'],
            'theme_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Event::query()->withCount('participants');
        foreach (['event_type', 'status' => 'publication_status'] as $param => $column) {
            $param = is_int($param) ? $column : $param;
            if (! empty($data[$param])) {
                $query->where($column, $data[$param]);
            }
        }
        if (! empty($data['theme_id'])) {
            $query->whereHas('themes', fn ($q) => $q->where('themes.id', $data['theme_id']));
        }
        if (! empty($data['q'])) {
            foreach (array_filter(preg_split('/\s+/u', ArabicNormalizer::normalize(mb_substr($data['q'], 0, 100))) ?: []) as $token) {
                $query->where('search_text', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $token).'%');
            }
        }

        $paginated = $query->orderByDesc('id')->paginate((int) ($data['per_page'] ?? 24));
        $completeness = RecordCompleteness::where('citable_type', Event::class)->whereIn('citable_id', $paginated->pluck('id'))->get()->keyBy('citable_id');

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (Event $e) => [
                ...EventPresenter::summary($e),
                'participant_count' => $e->participants_count,
                'completeness_pct' => $completeness->get($e->id)->completeness_pct ?? 0,
                'gap_count' => count($completeness->get($e->id)->blocking_gap_field_keys ?? []),
            ])->values(),
            'meta' => [
                'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(), 'total' => $paginated->total(),
            ],
        ]);
    }
}
