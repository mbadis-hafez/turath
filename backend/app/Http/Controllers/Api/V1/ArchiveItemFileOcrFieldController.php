<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExtractedFieldStatus;
use App\Enums\ProposalStatus;
use App\Http\Requests\ArchiveItem\UpdateArchiveItemRequest;
use App\Models\ArchiveItem;
use App\Models\EditProposal;
use App\Models\FileExtractedField;
use App\Models\User;
use App\Support\Ocr\ExtractedFieldPayloadMapper;
use App\Support\Proposals\EditorialDraftService;
use App\Support\Proposals\SectionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reviewing OCR/AI-extracted field suggestions. Per docs/privacy-rules.md:10,
 * "accepting" a field never writes it straight onto a public-facing value —
 * it goes through the same paths a human edit would: the reviewer's own
 * editorial draft when they hold proposals.submit, or a direct live update
 * otherwise (mirroring ArchiveItemUpdateController).
 */
class ArchiveItemFileOcrFieldController
{
    public function accept(Request $request, ArchiveItem $archiveItem, FileExtractedField $field): JsonResponse
    {
        $this->assertBelongsToItem($archiveItem, $field);
        $this->apply($request->user(), $archiveItem, $field->field_key, $field->extracted_value);
        $field->update(['status' => ExtractedFieldStatus::Accepted, 'reviewed_by_user_id' => $request->user()->id, 'reviewed_at' => now()]);

        return response()->json(['data' => self::present($field)]);
    }

    public function reject(Request $request, ArchiveItem $archiveItem, FileExtractedField $field): JsonResponse
    {
        $this->assertBelongsToItem($archiveItem, $field);
        $field->update(['status' => ExtractedFieldStatus::Rejected, 'reviewed_by_user_id' => $request->user()->id, 'reviewed_at' => now()]);

        return response()->json(['data' => self::present($field)]);
    }

    /** Edits the suggested value before it's accepted; does not itself apply it to the record. */
    public function update(Request $request, ArchiveItem $archiveItem, FileExtractedField $field): JsonResponse
    {
        $this->assertBelongsToItem($archiveItem, $field);
        $data = $request->validate(['extracted_value' => ['required', 'string', 'max:5000']]);
        $field->update(['extracted_value' => $data['extracted_value'], 'status' => ExtractedFieldStatus::Edited]);

        return response()->json(['data' => self::present($field)]);
    }

    /** Accepts every pending field at or above a confidence threshold (default 85), matching the review UI's bulk action. */
    public function acceptHighConfidence(Request $request, ArchiveItem $archiveItem): JsonResponse
    {
        $data = $request->validate(['threshold' => ['sometimes', 'integer', 'min:0', 'max:100']]);
        $threshold = $data['threshold'] ?? 85;

        $file = $archiveItem->files()->where('role', 'original')->latest('id')->first();
        abort_if($file === null, 404);

        $accepted = [];
        foreach ($file->extractedFields()->whereIn('status', [ExtractedFieldStatus::Pending->value, ExtractedFieldStatus::Edited->value])->where('confidence', '>=', $threshold)->get() as $field) {
            $this->apply($request->user(), $archiveItem, $field->field_key, $field->extracted_value);
            $field->update(['status' => ExtractedFieldStatus::Accepted, 'reviewed_by_user_id' => $request->user()->id, 'reviewed_at' => now()]);
            $accepted[] = $field->field_key;
        }

        return response()->json(['data' => ['accepted' => $accepted]]);
    }

    private function assertBelongsToItem(ArchiveItem $archiveItem, FileExtractedField $field): void
    {
        abort_unless($field->file->archive_item_id === $archiveItem->id, 404);
    }

    private function apply(User $user, ArchiveItem $archiveItem, string $fieldKey, ?string $value): void
    {
        $partial = ExtractedFieldPayloadMapper::toPayload($fieldKey, $value);
        if ($partial === null) {
            throw ValidationException::withMessages(['field_key' => ["\"{$fieldKey}\" isn't a field this endpoint can apply."]]);
        }

        if ($user->can('proposals.submit')) {
            $this->applyToDraft($user, $archiveItem, $partial);

            return;
        }

        $mapped = SectionValidator::mapped(UpdateArchiveItemRequest::class, 'archiveItem', $archiveItem->id, $partial);
        $archiveItem->fill($mapped);
        $archiveItem->save();
    }

    /**
     * @param  array<string, mixed>  $fieldsPartial
     */
    private function applyToDraft(User $user, ArchiveItem $archiveItem, array $fieldsPartial): void
    {
        DB::transaction(function () use ($user, $archiveItem, $fieldsPartial) {
            /** @var EditProposal|null $open */
            $open = EditProposal::query()
                ->where('citable_type', ArchiveItem::class)
                ->where('citable_id', $archiveItem->id)
                ->where('proposed_by_user_id', $user->id)
                ->whereIn('status', [ProposalStatus::Draft->value, ProposalStatus::Pending->value, ProposalStatus::ChangesRequested->value])
                ->lockForUpdate()
                ->latest('created_at')
                ->first();

            $payload = $open->payload ?? [];
            $payload['fields'] = array_merge($payload['fields'] ?? [], $fieldsPartial);

            (new EditorialDraftService)->upsert($archiveItem, $user, $payload, 'Accepted from automatic text extraction');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(FileExtractedField $field): array
    {
        return [
            'id' => $field->id,
            'field_key' => $field->field_key,
            'extracted_value' => $field->extracted_value,
            'confidence' => $field->confidence,
            'source_page' => $field->source_page,
            'status' => $field->status->value,
        ];
    }
}
