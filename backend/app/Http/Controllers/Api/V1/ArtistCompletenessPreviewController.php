<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artist\ArtistCompletenessPreviewRequest;
use App\Models\Artist;
use App\Support\Completeness\ArtistCompletenessPresenter;
use Illuminate\Http\JsonResponse;

/**
 * Lets the artist create page show live completeness without hardcoded
 * rules: the payload is mapped onto an unsaved Artist, evaluated by the
 * same calculator + rules class as persisted records, and returned in
 * the same enriched shape as the records completeness endpoint.
 */
class ArtistCompletenessPreviewController
{
    public function __invoke(ArtistCompletenessPreviewRequest $request): JsonResponse
    {
        $data = $request->validated();

        $artist = new Artist;
        $artist->forceFill([
            'legacy_code' => $data['legacy_code'] ?? null,
            'name_ar' => $data['name']['ar'] ?? null,
            'name_en' => $data['name']['en'] ?? null,
            'bio_ar' => $data['bio']['ar'] ?? null,
            'bio_en' => $data['bio']['en'] ?? null,
            'living_status' => $data['living_status'] ?? null,
            'birth_year_from' => $data['birth']['year_from'] ?? null,
            'death_year_from' => $data['death']['year_from'] ?? null,
            'birth_place_ar' => $data['birth_place']['ar'] ?? null,
            'birth_place_en' => $data['birth_place']['en'] ?? null,
            'nationality_ar' => $data['nationality']['ar'] ?? null,
            'nationality_en' => $data['nationality']['en'] ?? null,
            'portrait_path' => ($data['portrait_uploaded'] ?? false) ? 'preview' : null,
            'portrait_rights_status' => $data['portrait_rights_status'] ?? 'unknown',
        ]);

        return response()->json(['data' => (new ArtistCompletenessPresenter)->present($artist)]);
    }
}
