<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ArtworkResource;
use App\Models\Artwork;
use App\Support\Curation\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ArtworkApproveController
{
    public function __invoke(Request $request, Artwork $artwork): JsonResponse
    {
        $errors = (new PipelineService)->approvalErrors($artwork);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $artwork->publication_status = 'published';
        $artwork->published_by_user_id = $request->user()->id;
        $artwork->published_at = now();
        $artwork->save();
        $artwork->load(['artist', 'holder']);

        return response()->json(['data' => new ArtworkResource($artwork)]);
    }
}
