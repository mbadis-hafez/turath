<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ManagesArtistVariants;
use App\Http\Requests\Artist\StoreArtistVariantRequest;
use App\Models\Artist;
use Illuminate\Http\JsonResponse;

class ArtistVariantStoreController
{
    use ManagesArtistVariants;

    public function __invoke(StoreArtistVariantRequest $request, Artist $artist): JsonResponse
    {
        $name = $request->input('name');

        $this->ensureVariantNameUnique($artist, $name);

        $variant = $artist->variants()->create([
            'name' => $name,
            'language' => $request->input('language') ?? $this->detectVariantLanguage($name),
            'type' => $request->input('type'),
            'source_note' => $request->input('source_note'),
        ]);

        return response()->json(['data' => [
            'id' => $variant->id,
            'name' => $variant->name,
            'language' => $variant->language,
            'type' => $variant->type,
            'source_note' => $variant->source_note,
        ]], 201);
    }
}
