<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artist;
use App\Support\Curation\ArtistMerger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArtistMergeController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'survivor_id' => ['required', 'integer', 'different:duplicate_id', 'exists:artists,id'],
            'duplicate_id' => ['required', 'integer', 'exists:artists,id'],
            'field_resolution' => ['nullable', 'array'],
            'field_resolution.*' => [Rule::in(['survivor', 'duplicate'])],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ]);

        $merge = (new ArtistMerger)->merge(Artist::findOrFail($data['survivor_id']), Artist::findOrFail($data['duplicate_id']), $data['field_resolution'] ?? []);

        return response()->json(['data' => ['id' => $merge->id, 'survivor_artist_id' => $merge->survivor_artist_id, 'merged_artist_id' => $merge->merged_artist_id]], 201);
    }
}
