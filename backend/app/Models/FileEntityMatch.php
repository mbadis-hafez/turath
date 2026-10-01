<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Candidate records for one extracted name or title, and what a reviewer
 * decided — see the migration. Candidates are suggestions; only a reviewer's
 * confirmation says which record the document means.
 */
class FileEntityMatch extends Model
{
    use LogsChanges;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    /** The reviewer looked and none of the candidates is the one the document means. */
    public const STATUS_NO_MATCH = 'no_match';

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'candidates' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * The audit trail is for decisions (status, confirmed record, reviewer);
     * the candidate lists are recomputed by every match run.
     *
     * @return list<string>
     */
    protected function excludedFromActivityLog(): array
    {
        return ['candidates', 'source_text', 'matcher_version'];
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    /**
     * @return BelongsTo<FileExtractedField, $this>
     */
    public function extractedField(): BelongsTo
    {
        return $this->belongsTo(FileExtractedField::class, 'extracted_field_id');
    }

    protected static function booted(): void
    {
        // Decisions become evaluation/training examples (Dataset\ReviewExampleRecorder).
        static::observe(Observers\RecordsOcrReviewDecisions::class);
    }
}
