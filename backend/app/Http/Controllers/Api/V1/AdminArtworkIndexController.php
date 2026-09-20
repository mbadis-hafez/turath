<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artwork;
use App\Models\ArtworkPipelineStage;
use App\Models\CandidateArtwork;
use App\Models\RecordCompleteness;
use App\Support\ArabicNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminArtworkIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = Artwork::query()->with(['artist', 'holder']);

        if ($status = $request->input('status')) {
            $query->where('publication_status', $status);
        }
        if ($holder = $request->input('holder_id')) {
            $query->where('holder_id', $holder);
        }
        if ($artist = $request->input('artist_id')) {
            $query->where('artist_id', $artist);
        }
        if ($request->boolean('missing_dimensions')) {
            $query->whereNull('height_cm')->whereNull('width_cm');
        }
        if ($request->boolean('has_pipeline_gap')) {
            $query->where(fn (Builder $q) => $q
                ->whereDoesntHave('pipelineStages')
                ->orWhereHas('pipelineStages', fn (Builder $s) => $s->whereNotIn('status', ['done', 'not_applicable'])));
        }
        if ($q = $request->input('q')) {
            foreach (array_filter(preg_split('/\s+/u', ArabicNormalizer::normalize(mb_substr($q, 0, 100))) ?: []) as $token) {
                $query->where('search_text', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $token).'%');
            }
        }

        $paginated = $query->orderByDesc('id')->paginate(min((int) $request->input('per_page', 24), 100));

        $completeness = RecordCompleteness::where('citable_type', Artwork::class)
            ->whereIn('citable_id', $paginated->pluck('id'))->get()->keyBy('citable_id');

        $rows = $paginated->getCollection()->map(function (Artwork $a) use ($completeness) {
            $stages = $a->pipelineStages;
            $cleared = $stages->filter(fn (ArtworkPipelineStage $s) => $s->isCleared())->count();

            return [
                'id' => $a->id,
                'title' => ['ar' => $a->title_ar, 'en' => $a->title_en],
                'is_untitled' => $a->is_untitled,
                'artist' => $a->artist ? ['id' => $a->artist->id, 'name' => ['ar' => $a->artist->name_ar, 'en' => $a->artist->name_en]] : null,
                'holder_id' => $a->holder_id,
                'publication_status' => $a->publication_status,
                'missing_dimensions' => $a->height_cm === null && $a->width_cm === null,
                'completeness_pct' => $completeness->get($a->id)->completeness_pct ?? 0,
                'severity' => $completeness->get($a->id)->severity ?? 'blocking',
                'pipeline' => ['cleared' => $cleared, 'total' => count(ArtworkPipelineStage::KEYS)],
                'merged_into_id' => $a->merged_into_id,
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(), 'total' => $paginated->total(),
                'candidate_count' => CandidateArtwork::where('status', 'pending')->count(),
            ],
        ]);
    }
}
