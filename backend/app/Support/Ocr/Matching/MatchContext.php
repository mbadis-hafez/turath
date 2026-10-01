<?php

namespace App\Support\Ocr\Matching;

use App\Models\ArchiveItem;
use App\ValueObjects\ParsedDimensions;

/**
 * What else the document says, used only to rank candidates that already
 * match on their own text — context never makes a candidate by itself.
 */
final class MatchContext
{
    /**
     * @param  list<int>  $artistIds  artists the document is known to concern: linked to its archive item, or confirmed by a reviewer
     * @param  list<int>  $years  years the document mentions
     */
    public function __construct(
        public readonly ArchiveItem $archiveItem,
        public readonly array $artistIds = [],
        public readonly array $years = [],
        public readonly ?string $city = null,
        public readonly ?ParsedDimensions $dimensions = null,
    ) {}
}
