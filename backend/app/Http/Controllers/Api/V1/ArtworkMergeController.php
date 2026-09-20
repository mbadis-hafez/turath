<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artwork;
use App\Support\Curation\ArtworkMerger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArtworkMergeController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'survivor_id' => ['required', 'integer', 'different:duplicate_id', 'exists:artworks,id'],
            'duplicate_id' => ['required', 'integer', 'exists:artworks,id'],
            'field_resolution' => ['nullable', 'array'],
            'field_resolution.*' => [Rule::in(['survivor', 'duplicate'])],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ]);

        $merge = (new ArtworkMerger)->merge(
            Artwork::findOrFail($data['survivor_id']),
            Artwork::findOrFail($data['duplicate_id']),
            $data['field_resolution'] ?? [],
        );

        return response()->json(['data' => [
            'id' => $merge->id,
            'survivor_artwork_id' => $merge->survivor_artwork_id,
            'merged_artwork_id' => $merge->merged_artwork_id,
        ]], 201);
    }
}
