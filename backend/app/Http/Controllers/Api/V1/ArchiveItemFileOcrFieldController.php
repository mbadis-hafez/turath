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
use App\Support\Ocr\Extraction\ExtractedFieldRoute;
use App\Support\Ocr\Extraction\FieldDefinition;
use App\Support\Proposals\EditorialDraftService;
use App\Support\Proposals\SectionValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reviewing OCR/AI-extracted field suggestions. Per docs/privacy-rules.md:10,
 * "accepting" a field never writes it straight onto a public-facing value.
 * What accepting does depends on the field's route (Extraction\FieldDefinition):
 *
 * - record (the archive item's own fields): merged through the same paths a
 *   human edit would take — the reviewer's own editorial draft when they hold
 *   proposals.submit, or a direct live update otherwise;
 * - entity / evidence (a document type's other fields): verified here only;
 *   no record is touched until the record it belongs to is confirmed;
 * - artist_contact: refused — contact details go through the artist contact
 *   proposal, which needs a confirmed artist and an archivist's approval.
 *
 * An edit is stored as verified_value; extracted_value, what the machine
 * read, is never overwritten.
 */
class ArchiveItemFileOcrFieldController
{
    public function accept(Request $request, ArchiveItem $archiveItem, FileExtractedField $field): JsonResponse
    {
        $this->assertBelongsToItem($archiveItem, $field);
        $route = $this->reviewableRoute($field);
        $value = $field->currentValue();
        if ($value === null || trim($value) === '') {
            throw ValidationException::withMessages(['field' => ['There is no value to accept yet — transcribe it from the source first.']]);
        }

        if ($route === FieldDefinition::ROUTE_RECORD) {
            $this->apply($request->user(), $archiveItem, $field->field_key, $value);
        }
        $field->update(['status' => ExtractedFieldStatus::Accepted, 'verified_value' => $value, 'reviewed_by_user_id' => $request->user()->id, 'reviewed_at' => now()]);

        return response()->json(['data' => self::present($field)]);
    }

    public function reject(Request $request, ArchiveItem $archiveItem, FileExtractedField $field): JsonResponse
    {
        $this->assertBelongsToItem($archiveItem, $field);
        $field->update(['status' => ExtractedFieldStatus::Rejected, 'reviewed_by_user_id' => $request->user()->id, 'reviewed_at' => now()]);

        return response()->json(['data' => self::present($field)]);
    }

    /**
     * Edits the suggested value before it's accepted; does not itself apply it
     * to the record. The edit is the reviewer's value (verified_value); what
     * the machine read stays in extracted_value.
     */
    public function update(Request $request, ArchiveItem $archiveItem, FileExtractedField $field): JsonResponse
    {
        $this->assertBelongsToItem($archiveItem, $field);
        $this->reviewableRoute($field);
        $data = $request->validate(['extracted_value' => ['required', 'string', 'max:5000']]);
        $field->update(['verified_value' => $data['extracted_value'], 'status' => ExtractedFieldStatus::Edited]);

        return response()->json(['data' => self::present($field)]);
    }

    /**
     * A reviewer can't confirm the value from the source. Nothing is applied;
     * the field stays open to a later accept, edit or reject, and bulk accept
     * skips it. The note says why, for whoever looks next.
     */
    public function uncertain(Request $request, ArchiveItem $archiveItem, FileExtractedField $field): JsonResponse
    {
        $this->assertBelongsToItem($archiveItem, $field);
        $this->reviewableRoute($field);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $note = isset($data['note']) && trim($data['note']) !== '' ? trim($data['note']) : null;

        $field->update(['status' => ExtractedFieldStatus::Uncertain, 'review_note' => $note, 'reviewed_by_user_id' => $request->user()->id, 'reviewed_at' => now()]);

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
            // Only the archive item's own fields: everything else is verified one by one.
            $value = $field->currentValue();
            if (ExtractedFieldRoute::for($field)['route'] !== FieldDefinition::ROUTE_RECORD || $value === null || trim($value) === '') {
                continue;
            }
            $this->apply($request->user(), $archiveItem, $field->field_key, $value);
            $field->update(['status' => ExtractedFieldStatus::Accepted, 'verified_value' => $value, 'reviewed_by_user_id' => $request->user()->id, 'reviewed_at' => now()]);
            $accepted[] = $field->field_key;
        }

        return response()->json(['data' => ['accepted' => $accepted]]);
    }

    private function assertBelongsToItem(ArchiveItem $archiveItem, FileExtractedField $field): void
    {
        abort_unless($field->file->archive_item_id === $archiveItem->id, 404);
    }

    /** The field's route, when it can be reviewed from this list at all. */
    private function reviewableRoute(FileExtractedField $field): string
    {
        $route = ExtractedFieldRoute::for($field)['route'];
        if ($route === null) {
            throw ValidationException::withMessages(['field_key' => ["\"{$field->field_key}\" isn't a field this endpoint can apply."]]);
        }
        if ($route === FieldDefinition::ROUTE_ARTIST_CONTACT) {
            throw ValidationException::withMessages(['field_key' => ['Contact details, and the artist they belong to, are reviewed as a contact proposal, not here.']]);
        }

        return $route;
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

        $partial = ExtractedFieldPayloadMapper::preservingContent($partial, $archiveItem);
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
            $fieldsPartial = ExtractedFieldPayloadMapper::preservingContent($fieldsPartial, $archiveItem, $payload['fields'] ?? null);
            $payload['fields'] = array_merge($payload['fields'] ?? [], $fieldsPartial);

            (new EditorialDraftService)->upsert($archiveItem, $user, $payload, 'Accepted from automatic text extraction');
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(FileExtractedField $field): array
    {
        $route = ExtractedFieldRoute::for($field);

        return [
            'id' => $field->id,
            'field_key' => $field->field_key,
            'document_type' => $field->document_type?->value,
            'ordinal' => $field->ordinal,
            'label' => $route['label'],
            'route' => $route['route'],
            'target' => $route['target'],
            'extracted_value' => $field->extracted_value,
            'verified_value' => $field->verified_value,
            'confidence' => $field->confidence,
            'source_page' => $field->source_page,
            'region_id' => $field->region_id,
            'form_field_id' => $field->form_field_id,
            'has_crop' => $field->crop_path !== null,
            'original_ocr_text' => $field->original_ocr_text,
            'extraction_method' => $field->extraction_method->value,
            'rule' => $field->rule,
            'status' => $field->status->value,
            'reviewed_at' => $field->reviewed_at?->toIso8601String(),
            'review_note' => $field->review_note,
        ];
    }
}
