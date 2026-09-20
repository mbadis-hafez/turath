<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artist;
use App\Models\ArtistEntry;
use App\Support\Curation\ChildSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArtistEntriesSyncController
{
    private const GROUPS = ['educations' => 'education', 'awards' => 'award', 'exhibitions' => 'exhibition'];

    public function __invoke(Request $request, Artist $artist): JsonResponse
    {
        $rules = ['edit_summary' => ['nullable', 'string', 'max:255']];
        foreach (array_keys(self::GROUPS) as $group) {
            $rules[$group] = ['sometimes', 'array', 'max:100'];
            $rules["{$group}.*.id"] = ['nullable', 'integer'];
            $rules["{$group}.*.title.ar"] = ['nullable', 'string', 'max:255'];
            $rules["{$group}.*.title.en"] = ['nullable', 'string', 'max:255'];
            $rules["{$group}.*.place.ar"] = ['nullable', 'string', 'max:255'];
            $rules["{$group}.*.place.en"] = ['nullable', 'string', 'max:255'];
            $rules["{$group}.*.year_from"] = ['nullable', 'integer', 'between:1000,2100'];
            $rules["{$group}.*.year_to"] = ['nullable', 'integer', 'between:1000,2100', "gte:{$group}.*.year_from"];
            $rules["{$group}.*.note.ar"] = ['nullable', 'string', 'max:2000'];
            $rules["{$group}.*.note.en"] = ['nullable', 'string', 'max:2000'];
        }
        $data = $request->validate($rules);

        foreach (self::GROUPS as $group => $type) {
            if (! array_key_exists($group, $data)) {
                continue;
            }

            $items = array_map(fn (array $i) => [
                'id' => $i['id'] ?? null,
                'title_ar' => $i['title']['ar'] ?? null, 'title_en' => $i['title']['en'] ?? null,
                'place_ar' => $i['place']['ar'] ?? null, 'place_en' => $i['place']['en'] ?? null,
                'year_from' => $i['year_from'] ?? null, 'year_to' => $i['year_to'] ?? null,
                'note_ar' => $i['note']['ar'] ?? null, 'note_en' => $i['note']['en'] ?? null,
            ], array_filter($data[$group], fn (array $i) => ($i['title']['ar'] ?? null) !== null || ($i['title']['en'] ?? null) !== null));

            ChildSync::sync($artist->entries()->where('type', $type), $items, ['type' => $type]);
        }

        return response()->json(['data' => self::present($artist->refresh())]);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function present(Artist $artist): array
    {
        $out = ['educations' => [], 'awards' => [], 'exhibitions' => []];
        $groups = array_flip(self::GROUPS);

        foreach ($artist->entries()->get() as $e) {
            /** @var ArtistEntry $e */
            $out[$groups[$e->type]][] = [
                'id' => $e->id,
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
