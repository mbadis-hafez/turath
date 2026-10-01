<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OcrStage;
use App\Models\ArchiveItem;
use App\Models\FileEntityMatch;
use App\Support\Ocr\Matching\EntityMatchingService;
use App\Support\Ocr\Matching\EntityMatchPresenter;
use App\Support\Ocr\Matching\EntitySearch;
use App\Support\Ocr\Pipeline\OcrPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A reviewer deciding which record an extracted name or title means. A
 * candidate's score is never taken as proof: nothing is linked until a person
 * confirms it, and confirming only records the decision — no Artist, Artwork,
 * Event, Holder or Source is changed here.
 */
class ArchiveItemFileOcrMatchController
{
    public function __construct(
        private readonly EntityMatchingService $matching,
        private readonly EntityMatchPresenter $presenter,
    ) {}

    /** POST .../file/ocr/matches/{match}/confirm — {entity_id} for a record, {key} for a place. */
    public function confirm(Request $request, ArchiveItem $archiveItem, FileEntityMatch $match, OcrPipeline $pipeline): JsonResponse
    {
        $this->assertBelongsToItem($archiveItem, $match);
        $data = $request->validate([
            'entity_id' => ['nullable', 'required_without:key'],
            'key' => ['nullable', 'string', 'max:255', 'required_without:entity_id'],
        ]);
        $entityId = $data['entity_id'] ?? null;
        if ($entityId !== null && ! is_int($entityId) && ! is_string($entityId)) {
            abort(422, 'entity_id must be a record id.');
        }

        $match = $this->matching->confirm($match, $entityId, $data['key'] ?? null, $request->user());
        // A confirmed artist ranks that artist's artworks; the rest of the document's matches are refreshed.
        $pipeline->start($match->file, OcrStage::MatchEntities);

        return $this->respond($request, $match);
    }

    /** POST .../file/ocr/matches/{match}/no-match — none of the candidates is right. */
    public function noMatch(Request $request, ArchiveItem $archiveItem, FileEntityMatch $match): JsonResponse
    {
        $this->assertBelongsToItem($archiveItem, $match);

        return $this->respond($request, $this->matching->markNoMatch($match, $request->user()));
    }

    /** POST .../file/ocr/matches/{match}/reset — undo a decision. */
    public function reset(Request $request, ArchiveItem $archiveItem, FileEntityMatch $match, OcrPipeline $pipeline): JsonResponse
    {
        $this->assertBelongsToItem($archiveItem, $match);
        $match = $this->matching->reset($match);
        $pipeline->start($match->file, OcrStage::MatchEntities);

        return $this->respond($request, $match);
    }

    /**
     * GET .../file/ocr/matches/{match}/search?q= — the reviewer's own search for
     * the record, when it isn't among the candidates. Confirming one is still
     * the confirm endpoint. A place is a spelling, not a record: nothing to search.
     */
    public function search(Request $request, ArchiveItem $archiveItem, FileEntityMatch $match, EntitySearch $search): JsonResponse
    {
        $this->assertBelongsToItem($archiveItem, $match);
        $data = $request->validate(['q' => ['required', 'string', 'max:100']]);

        return response()->json(['data' => $search->search($match->entity_type, $data['q'], $request->user())]);
    }

    private function assertBelongsToItem(ArchiveItem $archiveItem, FileEntityMatch $match): void
    {
        abort_unless($match->file->archive_item_id === $archiveItem->id, 404);
    }

    private function respond(Request $request, FileEntityMatch $match): JsonResponse
    {
        return response()->json(['data' => $this->presenter->present([$match], $request->user())[$match->extracted_field_id]]);
    }
}
