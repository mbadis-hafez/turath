<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ManagesArtistVariants;
use App\Http\Requests\Artist\UpdateArtistVariantRequest;
use App\Models\Artist;
use App\Models\ArtistNameVariant;
use Illuminate\Http\JsonResponse;

class ArtistVariantUpdateController
{
    use ManagesArtistVariants;

    public function __invoke(UpdateArtistVariantRequest $request, Artist $artist, ArtistNameVariant $variant): JsonResponse
    {
        abort_unless($variant->artist_id === $artist->id, 404);

        $name = $request->input('name', $variant->name);

        $this->ensureVariantNameUnique($artist, $name, $variant->id);

        $variant->fill([
            'name' => $name,
            'language' => $request->input('language', $variant->language),
            'type' => $request->input('type', $variant->type),
            'source_note' => $request->input('source_note', $variant->source_note),
        ]);
        $variant->save();

        return response()->json(['data' => [
            'id' => $variant->id,
            'name' => $variant->name,
            'language' => $variant->language,
            'type' => $variant->type,
            'source_note' => $variant->source_note,
        ]]);
    }
}
