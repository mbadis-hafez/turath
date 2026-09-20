<?php

namespace App\Support\Curation;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ChildSync
{
    /**
     * Diff-aware replace of a child list: items carrying a known `id` are
     * updated (so unchanged rows stay unchanged in the audit log), others are
     * created, and existing rows missing from the list are deleted.
     *
     * @param  HasMany<*, *>  $relation
     * @param  array<int, array<string, mixed>>  $items  attribute arrays (already column-shaped), optional `id`
     * @param  array<string, mixed>  $constant  attributes forced on every row (e.g. type)
     * @return array{created: int, updated: int, deleted: int}
     */
    public static function sync(HasMany $relation, array $items, array $constant = []): array
    {
        $existing = $relation->get()->keyBy('id');
        $kept = [];
        $counts = ['created' => 0, 'updated' => 0, 'deleted' => 0];

        foreach (array_values($items) as $index => $item) {
            $id = $item['id'] ?? null;
            unset($item['id']);
            $attributes = [...$item, ...$constant, 'sort' => $index];

            if ($id !== null && $existing->has($id)) {
                $model = $existing->get($id);
                $model->fill($attributes);
                if ($model->isDirty()) {
                    $model->save();
                    $counts['updated']++;
                }
                $kept[] = $id;
            } else {
                $relation->create($attributes);
                $counts['created']++;
            }
        }

        foreach ($existing as $id => $model) {
            if (! in_array($id, $kept, true)) {
                $model->delete();
                $counts['deleted']++;
            }
        }

        return $counts;
    }
}
