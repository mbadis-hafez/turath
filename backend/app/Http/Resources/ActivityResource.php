<?php

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Activity $activity */
        $activity = $this->resource;

        return [
            'id' => $activity->id,
            'event' => $activity->event,
            'subject_type' => $activity->subject_type !== null ? class_basename($activity->subject_type) : null,
            'subject_id' => $activity->subject_id,
            'subject_label' => $this->subjectLabel($activity),
            'causer' => $activity->causer !== null
                ? ['id' => $activity->causer->getKey(), 'name' => $activity->causer->getAttribute('name')]
                : null,
            'edit_summary' => $activity->properties['edit_summary'] ?? null,
            'changes' => $this->changes($activity),
            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }

    private function subjectLabel(Activity $activity): string
    {
        $subject = $this->resolveSubject($activity);

        if ($subject !== null) {
            if (method_exists($subject, 'activitySubjectLabel')) {
                $label = $subject->activitySubjectLabel();

                if (is_string($label) && $label !== '') {
                    return $label;
                }
            }

            return class_basename($subject).' #'.$subject->getKey();
        }

        $type = $activity->subject_type !== null ? class_basename($activity->subject_type) : 'Unknown';

        return $type.' #'.$activity->subject_id.' (deleted)';
    }

    /**
     * Resolve the subject even when soft-deleted; null only when the row is
     * hard-missing or the class no longer exists.
     */
    private function resolveSubject(Activity $activity): ?Model
    {
        if ($activity->subject_type === null || ! class_exists($activity->subject_type)) {
            return null;
        }

        $class = $activity->subject_type;

        $query = $class::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
            $query->withTrashed();
        }

        return $query->find($activity->subject_id);
    }

    /**
     * @return array<int, array{field: string, label: array{ar: string, en: string}, old: mixed, new: mixed}>
     */
    private function changes(Activity $activity): array
    {
        $changes = $activity->attribute_changes?->toArray() ?? [];

        $attributes = $changes['attributes'] ?? [];
        $old = $changes['old'] ?? [];

        if (! is_array($attributes) || ! is_array($old)) {
            return [];
        }

        $labels = $this->fieldLabels($activity);

        $changes = [];

        foreach (array_keys(array_merge($attributes, $old)) as $field) {
            $changes[] = [
                'field' => $field,
                'label' => $labels[$field] ?? ['ar' => $field, 'en' => $field],
                'old' => $old[$field] ?? null,
                'new' => $attributes[$field] ?? null,
            ];
        }

        return $changes;
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    private function fieldLabels(Activity $activity): array
    {
        if ($activity->subject_type === null || ! class_exists($activity->subject_type)) {
            return [];
        }

        $class = $activity->subject_type;

        if (! method_exists($class, 'activityFieldLabels')) {
            return [];
        }

        $labels = $class::activityFieldLabels();

        return is_array($labels) ? $labels : [];
    }
}
