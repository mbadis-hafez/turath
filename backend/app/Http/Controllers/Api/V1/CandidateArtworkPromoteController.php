<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ArtworkResource;
use App\Models\ArchiveItemLink;
use App\Models\Artwork;
use App\Models\CandidateArtwork;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CandidateArtworkPromoteController
{
    public function __invoke(CandidateArtwork $candidate): JsonResponse
    {
        abort_unless($candidate->status === 'pending', 422);

        $artwork = DB::transaction(function () use ($candidate) {
            $hasTitle = $candidate->suggested_title_ar !== null || $candidate->suggested_title_en !== null;
            $artistId = $candidate->suggested_artist_id;

            $artwork = Artwork::create([
                'artist_id' => $artistId,
                'attribution_certainty' => $artistId !== null ? 'confirmed' : 'unattributed',
                'title_ar' => $candidate->suggested_title_ar,
                'title_en' => $candidate->suggested_title_en,
                'is_untitled' => ! $hasTitle,
                'category' => 'other',
                'publication_status' => 'draft',
            ]);

            // Provenance: keep the link back to where we first noticed it.
            if ($candidate->source_archive_item_id !== null) {
                ArchiveItemLink::firstOrCreate([
                    'archive_item_id' => $candidate->source_archive_item_id,
                    'linkable_type' => Artwork::class,
                    'linkable_id' => $artwork->id,
                    'role' => 'depicts',
                ]);
            }

            $candidate->update(['status' => 'promoted', 'promoted_artwork_id' => $artwork->id]);

            return $artwork;
        });

        $artwork->load(['artist', 'holder']);

        return response()->json(['data' => new ArtworkResource($artwork)], 201);
    }
}
