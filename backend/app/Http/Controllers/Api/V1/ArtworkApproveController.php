<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ArtworkResource;
use App\Models\Artwork;
use App\Support\Curation\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ArtworkApproveController
{
    public function __invoke(Artwork $artwork): JsonResponse
    {
        $errors = (new PipelineService)->approvalErrors($artwork);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $artwork->publication_status = 'published';
        $artwork->save();
        $artwork->load(['artist', 'holder']);

        return response()->json(['data' => new ArtworkResource($artwork)]);
    }
}
