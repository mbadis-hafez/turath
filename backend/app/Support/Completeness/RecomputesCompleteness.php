<?php

namespace App\Support\Completeness;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared observer behavior for every citable entity (Artist, Artwork,
 * ArchiveItem): stamp the creator once (D52 — dashboard "my records"
 * scoping) and keep record_completeness in sync on every save (D53).
 * Recomputes inline rather than via a true queued job — same simplification
 * already made for F4's import validation (see docs/decisions.md).
 */
trait RecomputesCompleteness
{
    protected function assignCreator(Model $record): void
    {
        if ($record->getAttribute('created_by_user_id') === null) {
            $record->setAttribute('created_by_user_id', auth()->id());
        }
    }

    protected function recomputeCompleteness(Model $record): void
    {
        if (! CompletenessCalculator::supports($record::class)) {
            return;
        }

        (new CompletenessCalculator)->recompute($record);

        if ($record->getAttribute('created_by_user_id') !== null) {
            (new DashboardStatsCalculator)->recompute((int) $record->getAttribute('created_by_user_id'));
        }
    }
}
