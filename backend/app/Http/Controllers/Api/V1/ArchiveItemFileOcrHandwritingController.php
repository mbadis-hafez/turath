<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OcrStage;
use App\Models\ArchiveItem;
use App\Models\FileOcrRegion;
use App\Models\OcrHandwritingSuggestion;
use App\Support\Ocr\HandwritingOcrException;
use App\Support\Ocr\HandwritingSuggestionService;
use App\Support\Ocr\Pipeline\OcrPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Machine suggestions for handwriting crops: requested one region at a time by
 * a reviewer, and settled only by a reviewer's explicit decision. See
 * HandwritingSuggestionService for why nothing here writes a field on its own.
 */
class ArchiveItemFileOcrHandwritingController
{
    public function suggest(Request $request, ArchiveItem $archiveItem, FileOcrRegion $region, HandwritingSuggestionService $service): JsonResponse
    {
        abort_unless($region->file->archive_item_id === $archiveItem->id, 404);

        try {
            $suggestion = $service->suggestForRegion($region, $request->user());
        } catch (HandwritingOcrException $e) {
            return response()->json(['message' => 'The handwriting provider could not read this crop.', 'retryable' => $e->retryable], 503);
        }

        return response()->json(['data' => self::present($suggestion)]);
    }

    public function review(Request $request, ArchiveItem $archiveItem, OcrHandwritingSuggestion $suggestion, HandwritingSuggestionService $service, OcrPipeline $pipeline): JsonResponse
    {
        abort_unless($suggestion->file->archive_item_id === $archiveItem->id, 404);

        $data = $request->validate([
            'decision' => ['required', Rule::in([OcrHandwritingSuggestion::DECISION_ACCEPTED, OcrHandwritingSuggestion::DECISION_EDITED, OcrHandwritingSuggestion::DECISION_REJECTED])],
            'final_text' => ['nullable', 'string', 'max:2000'],
        ]);

        $reviewed = $service->review($suggestion, $data['decision'], $data['final_text'] ?? null, $request->user());
        if ($reviewed->final_text !== null) {
            // The accepted reading is now a form field's transcription; extraction picks it up.
            $pipeline->start($suggestion->file, OcrStage::Extract);
        }

        return response()->json(['data' => self::present($reviewed)]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(OcrHandwritingSuggestion $suggestion): array
    {
        return [
            'id' => $suggestion->id,
            'status' => $suggestion->status,
            'text' => $suggestion->text,
            'confidence' => $suggestion->confidence,
            'provider' => $suggestion->provider,
            'model' => $suggestion->model,
            'model_version' => $suggestion->model_version,
            'decision' => $suggestion->decision,
            'final_text' => $suggestion->final_text,
            'decided_by_user_id' => $suggestion->decided_by_user_id,
            'decided_at' => $suggestion->decided_at?->toIso8601String(),
        ];
    }
}
