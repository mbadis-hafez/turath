<?php

namespace App\Support\Proposals;

use App\Http\Controllers\Api\V1\ProposalController;
use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\ArtistEntry;
use App\Models\ArtistSocialLink;
use App\Models\Artwork;
use App\Models\ArtworkImage;
use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * D73 companion: the revision list stays replayable (revisions table only),
 * but child rows (entries, social links, images, participants) and encrypted
 * values (contacts) are audited exclusively in activity_log via LogsChanges.
 * This surfaces those audit rows inside the revision history as informational,
 * non-revertible entries so a page edit never looks like it vanished.
 */
class AuditTrail
{
    /**
     * Citable parent model => child audit subjects keyed by their FK column.
     *
     * @var array<class-string<Model>, array<class-string<Model>, string>>
     */
    private const CHILDREN = [
        Artist::class => [ArtistEntry::class => 'artist_id', ArtistSocialLink::class => 'artist_id'],
        Artwork::class => [ArtworkImage::class => 'artwork_id'],
        Event::class => [EventParticipant::class => 'event_id'],
        ArchiveItem::class => [],
    ];

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, array<string, mixed>>
     */
    public static function forRecord(string $modelClass, int $id): array
    {
        $activities = Activity::query()
            ->where(function ($query) use ($modelClass, $id) {
                // Parent-level custom logs that never become revisions, e.g.
                // "contacts changed" (contact values are encrypted, so only a
                // count is auditable — ArtistCurationUpdateController).
                $query->where(function ($q) use ($modelClass, $id) {
                    $q->where('subject_type', $modelClass)
                        ->where('subject_id', (string) $id)
                        ->whereNotIn('description', ['created', 'updated']);
                });

                foreach (self::CHILDREN[$modelClass] ?? [] as $childClass => $fk) {
                    $query->orWhere(function ($q) use ($childClass, $fk, $id) {
                        $q->where('subject_type', $childClass)
                            ->whereIn('subject_id', $childClass::query()->where($fk, $id)->select('id'));
                    });
                }
            })
            ->orderBy('created_at')
            ->get();

        return $activities
            // Import/seed noise has no causer; only editor-driven changes belong in history.
            ->reject(fn (Activity $a) => $a->event === 'created' && $a->causer_id === null)
            ->map(fn (Activity $a) => self::present($a, $modelClass))
            ->all();
    }

    /**
     * @param  class-string<Model>  $parentClass
     * @return array<string, mixed>
     */
    public static function present(Activity $activity, string $parentClass): array
    {
        $changes = $activity->attribute_changes?->toArray() ?? [];
        $attributes = is_array($changes['attributes'] ?? null) ? $changes['attributes'] : [];
        $old = is_array($changes['old'] ?? null) ? $changes['old'] : [];

        $diffs = [];
        foreach (array_keys(array_merge($old, $attributes)) as $field) {
            $diffs[$field] = [
                'old' => FieldValues::normalize($old[$field] ?? null),
                'new' => FieldValues::normalize($attributes[$field] ?? null),
            ];
        }

        $subjectClass = $activity->subject_type;

        return [
            'id' => 'audit-'.$activity->id,
            'revision_number' => null,
            'source' => 'audit',
            'event' => $activity->event,
            'description' => $activity->description,
            'subject_label' => $subjectClass !== null && $subjectClass !== $parentClass
                ? self::subjectLabel($activity) : null,
            'edit_summary' => $activity->properties['edit_summary'] ?? null,
            'contacts_changed' => $activity->properties['contacts_changed'] ?? null,
            'field_diffs' => $diffs,
            'field_labels' => is_string($subjectClass) && class_exists($subjectClass)
                ? ProposalController::labelsFor($subjectClass, array_keys($diffs)) : [],
            'applied_by' => $activity->causer !== null
                ? ['id' => $activity->causer->getKey(), 'name' => $activity->causer->getAttribute('name')] : null,
            'applied_at' => $activity->created_at?->toIso8601String(),
            'reverted_by_revision_id' => null,
        ];
    }

    private static function subjectLabel(Activity $activity): string
    {
        $class = $activity->subject_type;

        if (is_string($class) && class_exists($class)) {
            $subject = $class::query()->find($activity->subject_id);

            if ($subject !== null && method_exists($subject, 'activitySubjectLabel')) {
                $label = $subject->activitySubjectLabel();

                if (is_string($label) && $label !== '') {
                    return $label;
                }
            }
        }

        return class_basename((string) $class).' #'.$activity->subject_id;
    }
}
