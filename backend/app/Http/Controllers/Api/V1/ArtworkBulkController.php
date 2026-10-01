<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artwork;
use App\Support\Completeness\PublishGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Applies one action to many artworks; each item succeeds or fails on its own (publish gate). */
class ArtworkBulkController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['set_status', 'delete'])],
            'status' => ['required_if:action,set_status', Rule::in(['draft', 'published', 'hidden'])],
        ]);
        $user = $request->user();

        if (($data['status'] ?? null) === 'published') {
            abort_unless($user?->can('artworks.publish'), 403);
        }

        $succeeded = [];
        $failed = [];

        foreach (Artwork::whereIn('id', $data['ids'])->get() as $artwork) {
            try {
                match ($data['action']) {
                    'set_status' => $this->setStatus($artwork, $data['status']),
                    default => $artwork->delete(),
                };
                $succeeded[] = $artwork->id;
            } catch (ValidationException $e) {
                $failed[] = ['id' => $artwork->id, 'message' => collect($e->errors())->flatten()->first()];
            }
        }

        return response()->json(['data' => ['succeeded' => $succeeded, 'failed' => $failed]]);
    }

    private function setStatus(Artwork $artwork, string $status): void
    {
        if ($status === 'published') {
            PublishGate::assertPublishable($artwork);
        }
        $artwork->publication_status = $status;
        $artwork->save();
    }
}
