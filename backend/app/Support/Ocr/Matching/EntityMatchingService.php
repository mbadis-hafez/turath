<?php

namespace App\Support\Ocr\Matching;

use App\Enums\ExtractedFieldStatus;
use App\Models\ArchiveItemLink;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;
use App\Models\File;
use App\Models\FileEntityMatch;
use App\Models\FileExtractedField;
use App\Models\Holder;
use App\Models\Source;
use App\Models\User;
use App\Support\ArabicDigits;
use App\Support\DimensionParser;
use App\Support\Ocr\Extraction\DocumentFieldSchema;
use App\Support\Ocr\Extraction\FieldDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Entity matching for a document's extracted fields:
 *
 *   extracted name or title → candidate records (EntityMatcher) → a
 *   reviewer confirms one, or says none is right
 *
 * Nothing here changes an Artist, Artwork, Event, Holder or Source.
 * Confirming a match records which record the document means, and verifies
 * the reading it was matched from. Contact fields and the artist they belong
 * to are left to the artist contact panel, which has its own confirmation.
 */
class EntityMatchingService
{
    public function __construct(private readonly EntityMatcher $matcher = new EntityMatcher) {}

    /**
     * The file's extracted fields that name a record, with the type to match them as.
     *
     * @return list<array{field: FileExtractedField, type: string, value: string}>
     */
    public function mentions(File $file): array
    {
        $documentType = $file->document_type;
        if ($documentType === null) {
            return [];
        }

        $mentions = [];
        foreach ($file->extractedFields()->where('document_type', $documentType->value)->orderBy('id')->get() as $field) {
            $definition = DocumentFieldSchema::field($documentType, $field->field_key);
            $value = $field->currentValue();
            if ($definition?->matchAs === null || $definition->route === FieldDefinition::ROUTE_ARTIST_CONTACT
                || $field->status === ExtractedFieldStatus::Rejected || $value === null || trim($value) === '') {
                continue;
            }
            $mentions[] = ['field' => $field, 'type' => $definition->matchAs, 'value' => $value];
        }

        return $mentions;
    }

    /**
     * Finds candidates for every mention. A match a reviewer already decided is
     * kept as it is; a pending one is recomputed; a pending one whose field is
     * gone, or no longer names a record, is removed.
     *
     * @return array{mentions: int, with_candidates: int, strong: int, decided_kept: int}
     */
    public function matchFile(File $file): array
    {
        $mentions = $this->mentions($file);
        $context = $this->context($file);
        $existing = FileEntityMatch::query()->where('file_id', $file->id)->get()->keyBy('extracted_field_id');
        $summary = ['mentions' => count($mentions), 'with_candidates' => 0, 'strong' => 0, 'decided_kept' => 0];

        DB::transaction(function () use ($file, $mentions, $context, $existing, &$summary) {
            $current = [];
            foreach ($mentions as ['field' => $field, 'type' => $type, 'value' => $value]) {
                $current[] = $field->id;
                /** @var FileEntityMatch|null $row */
                $row = $existing->get($field->id);
                if ($row !== null && $row->status !== FileEntityMatch::STATUS_PENDING) {
                    $summary['decided_kept']++;

                    continue;
                }

                $candidates = $this->matcher->match($type, $value, $context);
                $summary['with_candidates'] += $candidates === [] ? 0 : 1;
                $summary['strong'] += ($candidates[0]['strength'] ?? null) === 'high' ? 1 : 0;

                FileEntityMatch::query()->updateOrCreate(['extracted_field_id' => $field->id], [
                    'file_id' => $file->id,
                    'entity_type' => $type,
                    'source_text' => $value,
                    'candidates' => $candidates,
                    'status' => FileEntityMatch::STATUS_PENDING,
                    'matcher_version' => EntityMatcher::VERSION,
                ]);
            }

            FileEntityMatch::query()->where('file_id', $file->id)->where('status', FileEntityMatch::STATUS_PENDING)
                ->whereNotIn('extracted_field_id', $current === [] ? [0] : $current)->delete();
        });

        return $summary;
    }

    /**
     * A reviewer says which record the document means: one of the candidates,
     * or any other existing record of that type. A place is confirmed by one
     * of its candidate spellings.
     */
    public function confirm(FileEntityMatch $match, int|string|null $entityId, ?string $key, User $reviewer): FileEntityMatch
    {
        if ($match->entity_type === 'place') {
            $keys = array_column($match->candidates ?? [], 'key');
            if ($key === null || ! in_array($key, $keys, true)) {
                throw ValidationException::withMessages(['key' => ['Choose one of the suggested places.']]);
            }
            $attributes = ['confirmed_entity_id' => null, 'confirmed_key' => $key];
        } else {
            if ($entityId === null || ! $this->exists($match->entity_type, $entityId)) {
                throw ValidationException::withMessages(['entity_id' => ['That record does not exist.']]);
            }
            $attributes = ['confirmed_entity_id' => (string) $entityId, 'confirmed_key' => null];
        }

        return DB::transaction(function () use ($match, $attributes, $reviewer) {
            $match->update([...$attributes, 'status' => FileEntityMatch::STATUS_CONFIRMED, 'reviewed_by_user_id' => $reviewer->id, 'reviewed_at' => now()]);

            // Saying which record a reading names verifies the reading too — and keeps it through re-extraction.
            $field = $match->extractedField;
            if ($field !== null && in_array($field->status, [ExtractedFieldStatus::Pending, ExtractedFieldStatus::Edited], true)) {
                $field->update(['status' => ExtractedFieldStatus::Accepted, 'verified_value' => $field->currentValue(), 'reviewed_by_user_id' => $reviewer->id, 'reviewed_at' => now()]);
            }

            return $match->refresh();
        });
    }

    /** None of the candidates is the record the document means — perhaps none exists yet. */
    public function markNoMatch(FileEntityMatch $match, User $reviewer): FileEntityMatch
    {
        $match->update([
            'status' => FileEntityMatch::STATUS_NO_MATCH, 'confirmed_entity_id' => null, 'confirmed_key' => null,
            'reviewed_by_user_id' => $reviewer->id, 'reviewed_at' => now(),
        ]);

        return $match->refresh();
    }

    /** Undoes a decision; the next match run refreshes the candidates. */
    public function reset(FileEntityMatch $match): FileEntityMatch
    {
        $match->update(['status' => FileEntityMatch::STATUS_PENDING, 'confirmed_entity_id' => null, 'confirmed_key' => null, 'reviewed_by_user_id' => null, 'reviewed_at' => null]);

        return $match->refresh();
    }

    /**
     * What else the document says, for ranking: the artists it concerns (linked
     * to its archive item, or confirmed here), its years, its city, and an
     * artwork's measured dimensions.
     */
    public function context(File $file): MatchContext
    {
        $linked = ArchiveItemLink::query()->where('archive_item_id', $file->archive_item_id)->where('linkable_type', Artist::class)->pluck('linkable_id')->all();
        $confirmed = FileEntityMatch::query()->where('file_id', $file->id)->where('entity_type', 'artist')->where('status', FileEntityMatch::STATUS_CONFIRMED)->pluck('confirmed_entity_id')->all();

        $years = [];
        foreach ($file->extractedDates()->whereNotNull('normalized')->pluck('normalized') as $normalized) {
            $years[] = (int) substr((string) $normalized, 0, 4);
        }

        $value = fn (string $key) => $file->extractedFields()->where('field_key', $key)->where('document_type', $file->document_type?->value)->first()?->currentValue();
        $dimensions = $value('dimensions');

        return new MatchContext(
            archiveItem: $file->archiveItem()->firstOrFail(),
            artistIds: array_values(array_unique(array_map('intval', [...$linked, ...$confirmed]))),
            years: array_values(array_unique($years)),
            city: $value('city'),
            dimensions: $dimensions !== null ? DimensionParser::parse(ArabicDigits::toAscii($dimensions)) : null,
        );
    }

    private function exists(string $type, int|string $id): bool
    {
        $query = match ($type) {
            'artist' => Artist::query(),
            'artwork' => Artwork::query(),
            'event' => Event::query(),
            'holder' => Holder::query(),
            'source' => Source::query(),
            default => null,
        };

        return $query !== null && $query->whereKey($id)->exists();
    }
}
