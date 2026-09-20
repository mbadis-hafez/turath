<?php

namespace App\Support\Proposals;

use App\Enums\RevisionSource;
use App\Models\Revision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * D71: every applied change goes through the record's ordinary fill-and-save,
 * so LogsChanges, F10's completeness recompute and the search_text rebuild all
 * fire exactly as they do for a direct edit. There is no separate write path.
 */
class RecordUpdater
{
    /**
     * @param  array<string, mixed>  $values  column => new value
     * @return array<string, array{old: mixed, new: mixed}> what actually changed
     */
    public static function apply(Model $record, array $values): array
    {
        $applied = [];

        foreach ($values as $column => $new) {
            $old = $record->getAttribute($column);
            if (! FieldValues::differ($old, $new)) {
                continue;
            }
            $applied[$column] = ['old' => FieldValues::normalize($old), 'new' => FieldValues::normalize($new)];
            $record->setAttribute($column, FieldValues::normalize($new));
        }

        if ($applied !== []) {
            $record->save();
        }

        return $applied;
    }

    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $diffs
     */
    public static function recordRevision(
        Model $record,
        array $diffs,
        RevisionSource $source,
        ?int $userId,
        ?string $proposalId = null,
    ): Revision {
        $next = (int) DB::table('revisions')
            ->where('citable_type', $record::class)->where('citable_id', $record->getKey())
            ->max('revision_number') + 1;

        return Revision::create([
            'citable_type' => $record::class,
            'citable_id' => $record->getKey(),
            'revision_number' => $next,
            'source' => $source->value,
            'edit_proposal_id' => $proposalId,
            'field_diffs' => $diffs,
            'applied_by_user_id' => $userId,
            'applied_at' => now(),
        ]);
    }
}
