<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artist;
use App\Models\RecordCompleteness;
use App\Support\ArabicNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminArtistIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = Artist::query()->with('themes')->withCount('archiveItemLinks');

        if ($request->boolean('unverified')) {
            $query->where('verified_status', 'unverified');
        }
        if ($owner = $request->input('owner_type')) {
            $query->where('owner_type', $owner);
        }
        if ($city = $request->input('city')) {
            $query->where(fn ($q) => $q->where('birth_place_ar', $city)->orWhere('birth_place_en', $city));
        }
        if ($theme = $request->input('theme_id')) {
            $query->whereHas('themes', fn ($q) => $q->where('themes.id', $theme));
        }
        // No "priority material" flag exists yet: approximated as having any linked archive item.
        if ($request->boolean('has_priority_materials')) {
            $query->whereHas('archiveItemLinks');
        }
        if ($q = $request->input('q')) {
            $normalized = ArabicNormalizer::normalize(mb_substr($q, 0, 100));
            foreach (array_filter(preg_split('/\s+/u', $normalized) ?: []) as $token) {
                $query->where('search_text', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $token).'%');
            }
        }

        $paginated = $query->orderBy('id')->paginate(min((int) $request->input('per_page', 24), 100));
        $completeness = RecordCompleteness::where('citable_type', Artist::class)->whereIn('citable_id', $paginated->pluck('id'))->get()->keyBy('citable_id');

        $rows = [];
        foreach ($paginated->items() as $a) {
            $c = $completeness->get($a->id);
            $rows[] = [
                'id' => $a->id,
                'slug' => $a->slug,
                'legacy_code' => $a->legacy_code,
                'name' => ['ar' => $a->name_ar, 'en' => $a->name_en],
                'verified_status' => $a->verified_status,
                'city' => ['ar' => $a->birth_place_ar, 'en' => $a->birth_place_en],
                'owner_type' => $a->owner_type,
                'linked_material_count' => (int) $a->getAttribute('archive_item_links_count'),
                'gap_count' => $c === null ? 0 : count($c->blocking_gap_field_keys) + count($c->minor_gap_field_keys),
                'severity' => $c === null ? 'blocking' : $c->severity,
                'themes' => $a->themes->map(fn ($t) => ['id' => $t->id, 'label' => ['ar' => $t->label_ar, 'en' => $t->label_en]])->values(),
            ];
        }

        return response()->json(['data' => $rows, 'meta' => [
            'current_page' => $paginated->currentPage(), 'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(), 'total' => $paginated->total(),
        ]]);
    }
}
