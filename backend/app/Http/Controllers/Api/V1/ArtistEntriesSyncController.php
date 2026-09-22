<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artist\SyncArtistEntriesRequest;
use App\Models\Artist;
use App\Models\ArtistEntry;
use App\Support\Curation\ArtistSections;
use Illuminate\Http\JsonResponse;

class ArtistEntriesSyncController
{
    public function __invoke(SyncArtistEntriesRequest $request, Artist $artist): JsonResponse
    {
        (new ArtistSections)->syncEntries(
            $artist,
            $request->has('educations') ? $request->input('educations') : null,
            $request->has('activities') ? $request->input('activities') : null,
        );

        return response()->json(['data' => self::present($artist->refresh())]);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function present(Artist $artist): array
    {
        $out = ['educations' => [], 'activities' => []];

        foreach ($artist->entries()->get() as $e) {
            /** @var ArtistEntry $e */
            $out[$e->type === 'education' ? 'educations' : 'activities'][] = [
                'id' => $e->id,
                'type' => $e->type,
                'title' => ['ar' => $e->title_ar, 'en' => $e->title_en],
                'place' => ['ar' => $e->place_ar, 'en' => $e->place_en],
                'year_from' => $e->year_from,
                'year_to' => $e->year_to,
                'note' => ['ar' => $e->note_ar, 'en' => $e->note_en],
            ];
        }

        return $out;
    }
}
