<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ArchiveItem;
use App\Models\Artist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Applies one action to many items; each item succeeds or fails on its own (publish gate, permissions). */
class ArchiveItemBulkController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['set_status', 'link_artist', 'delete'])],
            'status' => ['required_if:action,set_status', Rule::in(['draft', 'published', 'hidden'])],
            'artist_id' => ['required_if:action,link_artist', 'integer', 'exists:artists,id'],
        ]);
        $user = $request->user();

        if (($data['status'] ?? null) === 'published') {
            abort_unless($user?->can('archive.publish'), 403);
        }

        $succeeded = [];
        $failed = [];

        foreach (ArchiveItem::whereIn('id', $data['ids'])->get() as $item) {
            try {
                match ($data['action']) {
                    'set_status' => $this->setStatus($item, $data['status']),
                    'link_artist' => $this->linkArtist($item, (int) $data['artist_id']),
                    default => $item->delete(),
                };
                $succeeded[] = $item->id;
            } catch (ValidationException $e) {
                $failed[] = ['id' => $item->id, 'message' => collect($e->errors())->flatten()->first()];
            }
        }

        return response()->json(['data' => ['succeeded' => $succeeded, 'failed' => $failed]]);
    }

    private function setStatus(ArchiveItem $item, string $status): void
    {
        if ($status === 'published') {
            ArchiveItemPublishController::assertPublishable($item);
        }
        $item->publication_status = $status;
        $item->save();
    }

    private function linkArtist(ArchiveItem $item, int $artistId): void
    {
        $item->links()->firstOrCreate(['linkable_type' => Artist::class, 'linkable_id' => $artistId, 'role' => 'subject']);
    }
}
