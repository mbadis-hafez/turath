<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Which dataset split an archive item's examples belong to (Dataset\DatasetSplits). */
class OcrDatasetDocument extends Model
{
    public const SPLIT_TRAINING = 'training';

    public const SPLIT_EVALUATION = 'evaluation';

    /** Never exported, whatever its examples. */
    public const SPLIT_EXCLUDED = 'excluded';

    public const SPLITS = [self::SPLIT_TRAINING, self::SPLIT_EVALUATION, self::SPLIT_EXCLUDED];

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['locked_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<ArchiveItem, $this>
     */
    public function archiveItem(): BelongsTo
    {
        return $this->belongsTo(ArchiveItem::class);
    }
}
