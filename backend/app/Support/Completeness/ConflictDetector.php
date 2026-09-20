<?php

namespace App\Support\Completeness;

use App\Enums\ConflictStatus;
use App\Models\FieldCitation;
use App\Models\SourceConflict;

/**
 * Runs whenever a field_citations row is created or deleted (§4). Auto-
 * creates an open conflict when citations disagree, and auto-resolves one
 * when they converge — but never deletes the conflict's history row, and
 * never touches an already-open conflict just because a third citation
 * arrived agreeing with one side (D50: only a human closes a real
 * disagreement).
 */
class ConflictDetector
{
    public function check(string $citableType, int $citableId, string $fieldKey): void
    {
        $citations = FieldCitation::query()
            ->where('citable_type', $citableType)
            ->where('citable_id', $citableId)
            ->where('field_key', $fieldKey)
            ->get();

        $distinctValues = $citations
            ->map(fn (FieldCitation $c) => json_encode($c->claimed_value))
            ->unique();

        $existingOpen = SourceConflict::query()
            ->where('citable_type', $citableType)
            ->where('citable_id', $citableId)
            ->where('field_key', $fieldKey)
            ->where('status', ConflictStatus::Open->value)
            ->first();

        if ($distinctValues->count() > 1) {
            if ($existingOpen === null) {
                SourceConflict::create([
                    'citable_type' => $citableType,
                    'citable_id' => $citableId,
                    'field_key' => $fieldKey,
                    'status' => ConflictStatus::Open->value,
                    'citation_ids' => $citations->pluck('id')->values()->all(),
                ]);
            }

            return;
        }

        if ($existingOpen !== null) {
            $existingOpen->status = ConflictStatus::Resolved->value;
            $existingOpen->resolution_note = 'أصبحت المصادر متفقة';
            $existingOpen->resolved_at = now();
            $existingOpen->save();
        }
    }
}
