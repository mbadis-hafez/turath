<?php

namespace App\Concerns;

use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait LogsChanges
{
    use LogsActivity;

    /**
     * Bilingual field labels for the activity feed, keyed by column name:
     * ['birth_year_from' => ['ar' => '...', 'en' => 'Birth year (from)']].
     * Models override this; the API falls back to the raw column name.
     *
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    /**
     * spatie v5 hook invoked on the subject right before the entry is saved.
     * Sets the log name to the model's table, strips timestamp noise from the
     * diff, and attaches the optional per-request edit summary.
     */
    public function beforeActivityLogged(Activity $activity, string $event): void
    {
        $activity->log_name = $this->getTable();

        $properties = $activity->properties?->toArray() ?? [];

        foreach (['attributes', 'old'] as $key) {
            if (isset($properties[$key]) && is_array($properties[$key])) {
                unset(
                    $properties[$key]['created_at'],
                    $properties[$key]['updated_at'],
                    $properties[$key]['deleted_at'],
                );
            }
        }

        $summary = request()->input('edit_summary');

        if (is_string($summary) && trim($summary) !== '' && mb_strlen($summary) <= 255) {
            $properties['edit_summary'] = $summary;
        }

        $activity->properties = $properties;
    }
}
