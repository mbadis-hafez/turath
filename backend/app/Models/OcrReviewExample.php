<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reviewer decision kept as evaluation/training data — see the migration
 * and Dataset\ReviewExampleRecorder. Text is redacted at write time; the
 * reviewer is kept for internal audit and never exported.
 */
class OcrReviewExample extends Model
{
    public const KIND_FIELD = 'field';

    public const KIND_DATE = 'date';

    /** A form field a person transcribed by hand (no machine suggestion behind it). */
    public const KIND_FORM_FIELD = 'form_field';

    /** A handwriting model's suggestion, and what the reviewer made of it. */
    public const KIND_HANDWRITING = 'handwriting';

    public const KIND_MATCH = 'match';

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted' => 'boolean',
            'confidence' => 'integer',
            'ai_meta' => 'array',
            'match' => 'array',
            'redacted' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * The decision that counts for each thing decided: the latest one, unless
     * it was undone.
     *
     * @return Builder<self>
     */
    public static function latestDecisions(): Builder
    {
        return self::query()
            ->whereIn('id', self::query()->selectRaw('max(id)')->groupBy('subject_type', 'subject_id'))
            ->where('reviewer_action', '!=', 'reset');
    }

    /**
     * @return BelongsTo<ArchiveItem, $this>
     */
    public function archiveItem(): BelongsTo
    {
        return $this->belongsTo(ArchiveItem::class);
    }
}
