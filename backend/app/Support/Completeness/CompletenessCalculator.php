<?php

namespace App\Support\Completeness;

use App\Enums\CompletenessSeverity;
use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;
use App\Models\FieldCitation;
use App\Models\ReviewQueueItem;
use App\Models\SourceConflict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Produces a record_completeness row (D47, materialized per D53). Writes
 * via the query builder rather than Eloquent's save(), since the table's
 * true key is the (citable_type, citable_id) pair.
 */
class CompletenessCalculator
{
    /**
     * @var array<class-string<Model>, class-string<CompletenessRules>>
     */
    private const RULES = [
        Artist::class => ArtistCompletenessRules::class,
        Artwork::class => ArtworkCompletenessRules::class,
        ArchiveItem::class => ArchiveItemCompletenessRules::class,
        Event::class => EventCompletenessRules::class,
    ];

    public static function supports(string $modelClass): bool
    {
        return isset(self::RULES[$modelClass]);
    }

    public function rulesFor(string $modelClass): CompletenessRules
    {
        $class = self::RULES[$modelClass] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("No completeness rules registered for [{$modelClass}].");
        }

        return new $class;
    }

    public function recompute(Model $record): void
    {
        $modelClass = $record::class;
        $id = $record->getKey();
        $result = $this->evaluate($record);

        DB::table('record_completeness')->updateOrInsert(
            ['citable_type' => $modelClass, 'citable_id' => $id],
            [
                'completeness_pct' => $result['completeness_pct'],
                'blocking_gap_field_keys' => json_encode($result['blocking']),
                'minor_gap_field_keys' => json_encode($result['minor']),
                'open_conflict_count' => $result['open_conflict_count'],
                'severity' => $result['severity']->value,
                'computed_at' => now(),
            ],
        );
    }

    /**
     * Live evaluation against the record's current (possibly unsaved, but
     * already-existing) attributes — used both by recompute() (which
     * persists the result) and by publish-gate checks (D48), which need a
     * fresh answer at the moment of publishing rather than the possibly
     * stale materialized row.
     *
     * @return array{severity: CompletenessSeverity, blocking: array<int, string>, minor: array<int, string>, completeness_pct: int, open_conflict_count: int}
     */
    public function evaluate(Model $record): array
    {
        $modelClass = $record::class;
        $rules = $this->rulesFor($modelClass);
        $id = $record->getKey();

        $blocking = [];
        $minor = [];

        foreach ($rules->coreFields() as $fieldKey => $config) {
            if (! $this->isSatisfied($record, $rules, $fieldKey, $config)) {
                $blocking[] = $fieldKey;
            }
        }

        foreach ($rules->importantFields() as $fieldKey) {
            if (! $rules->isFieldPresent($record, $fieldKey)) {
                $minor[] = $fieldKey;
            }
        }

        $totalFields = count($rules->coreFields()) + count($rules->importantFields());
        $metFields = $totalFields - count($blocking) - count($minor);
        $pct = $totalFields > 0 ? (int) round(($metFields / $totalFields) * 100) : 100;

        $openConflictCount = $id !== null ? SourceConflict::query()
            ->where('citable_type', $modelClass)
            ->where('citable_id', $id)
            ->where('status', 'open')
            ->count() : 0;

        $pendingReview = $id !== null && ReviewQueueItem::query()
            ->where('citable_type', $modelClass)
            ->where('citable_id', $id)
            ->where('status', 'pending')
            ->exists();

        return [
            'severity' => $this->severity($blocking, $openConflictCount, $minor, $pendingReview),
            'blocking' => $blocking,
            'minor' => $minor,
            'completeness_pct' => $pct,
            'open_conflict_count' => $openConflictCount,
        ];
    }

    /**
     * @param  array{requires_citation: bool, citation_field_key?: string, any_citation?: bool}  $config
     */
    private function isSatisfied(Model $record, CompletenessRules $rules, string $fieldKey, array $config): bool
    {
        if (! $rules->isFieldPresent($record, $fieldKey)) {
            return false;
        }

        if (! $config['requires_citation'] || $rules->citationExempt($record, $fieldKey)) {
            return true;
        }

        $query = FieldCitation::query()
            ->where('citable_type', $record::class)
            ->where('citable_id', $record->getKey());

        if (! ($config['any_citation'] ?? false)) {
            $query->where('field_key', $config['citation_field_key'] ?? $fieldKey);
        }

        return $query->exists();
    }

    /**
     * @param  array<int, string>  $blocking
     * @param  array<int, string>  $minor
     */
    private function severity(array $blocking, int $openConflictCount, array $minor, bool $pendingReview): CompletenessSeverity
    {
        return match (true) {
            $blocking !== [] => CompletenessSeverity::Blocking,
            $openConflictCount > 0 => CompletenessSeverity::Conflict,
            $minor !== [] => CompletenessSeverity::Minor,
            $pendingReview => CompletenessSeverity::PendingReview,
            default => CompletenessSeverity::Clear,
        };
    }
}
