<?php

namespace App\Support\Proposals;

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\ArtistContact;
use App\Models\ArtistEntry;
use App\Models\ArtistSocialLink;
use App\Models\Artwork;
use App\Models\ArtworkPipelineStage;
use App\Models\EditProposal;
use App\Models\Event;
use App\Models\EventParticipant;
use Illuminate\Database\Eloquent\Model;

/**
 * Computes the reviewer-facing diff of an editorial draft: every payload
 * section compared against the LIVE record at view time, so drift since
 * submission is visible in the review queue. Flat sections become labeled
 * field diffs; child sections become added/removed/changed row diffs.
 */
class ProposalDiffBuilder
{
    /**
     * @return array{sections: array<int, array<string, mixed>>}
     */
    public static function build(EditProposal $proposal): array
    {
        /** @var Model|null $record */
        $record = $proposal->citable_type::query()->find($proposal->citable_id);
        abort_if($record === null, 404);

        $payload = $proposal->payload ?? [];
        if ($payload === []) {
            return ['sections' => []];
        }

        // Sections map through the same validation as apply; a drifted record
        // surfaces as a validation error bag rather than a broken diff.
        $mapped = (new EditorialDraftService)->mapSections($record, $payload);

        $sections = match (true) {
            $record instanceof Artist => self::artist($record, $mapped),
            $record instanceof Artwork => self::artwork($record, $mapped),
            $record instanceof Event => self::event($record, $mapped),
            $record instanceof ArchiveItem => self::archiveItem($record, $mapped),
            default => [],
        };

        return ['sections' => array_values(array_filter($sections))];
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, array<string, mixed>>
     */
    private static function artist(Artist $artist, array $mapped): array
    {
        $sections = [];

        if (isset($mapped['fields'])) {
            $sections[] = self::section('fields', self::flatFields(Artist::class, $artist, $mapped['fields']));
        }

        if (isset($mapped['curation'])) {
            $curation = $mapped['curation'];
            $contacts = $curation['contacts'] ?? null;
            unset($curation['contacts']);
            $collections = [];
            if (is_array($contacts)) {
                $live = $artist->contacts()->get()->map(fn ($c) => [
                    'id' => $c->id, 'name' => $c->name, 'role_note' => $c->role_note,
                    'email' => $c->email, 'phone' => $c->phone, 'address' => $c->address,
                ])->all();
                $incoming = array_map(fn (array $c) => [
                    'id' => $c['id'] ?? null, 'name' => $c['name'] ?? null, 'role_note' => $c['role_note'] ?? null,
                    'email' => $c['email'] ?? null, 'phone' => $c['phone'] ?? null, 'address' => $c['address'] ?? null,
                ], array_filter($contacts, fn (array $c) => ($c['name'] ?? null) !== null || ($c['email'] ?? null) !== null || ($c['phone'] ?? null) !== null || ($c['address'] ?? null) !== null));
                $collection = self::collection('contacts', $live, $incoming, self::labels(ArtistContact::class), fn (array $r) => $r['name'] ?? $r['email'] ?? $r['phone'] ?? '#'.$r['id']);
                if ($collection !== null) {
                    $collections[] = $collection;
                }
            }
            $sections[] = self::section('curation', self::flatFields(Artist::class, $artist, $curation), $collections);
        }

        $groups = ['educations' => ['education'], 'activities' => ['award', 'exhibition', 'talk', 'symposium']];
        foreach ($groups as $group => $types) {
            if (! isset($mapped[$group])) {
                continue;
            }
            $live = $artist->entries()->whereIn('type', $types)->get()
                ->map(fn (ArtistEntry $e) => [
                    'id' => $e->id, 'type' => $e->type,
                    'title_ar' => $e->title_ar, 'title_en' => $e->title_en,
                    'place_ar' => $e->place_ar, 'place_en' => $e->place_en,
                    'year_from' => $e->year_from, 'year_to' => $e->year_to,
                    'note_ar' => $e->note_ar, 'note_en' => $e->note_en,
                ])->all();
            $incoming = array_map(fn (array $i) => [
                'id' => $i['id'] ?? null, 'type' => $i['type'] ?? $types[0],
                'title_ar' => $i['title']['ar'] ?? null, 'title_en' => $i['title']['en'] ?? null,
                'place_ar' => $i['place']['ar'] ?? null, 'place_en' => $i['place']['en'] ?? null,
                'year_from' => $i['year_from'] ?? null, 'year_to' => $i['year_to'] ?? null,
                'note_ar' => $i['note']['ar'] ?? null, 'note_en' => $i['note']['en'] ?? null,
            ], array_filter($mapped[$group], fn (array $i) => ($i['title']['ar'] ?? null) !== null || ($i['title']['en'] ?? null) !== null));
            $collection = self::collection($group, $live, $incoming, self::labels(ArtistEntry::class), fn (array $r) => $r['title_en'] ?? $r['title_ar'] ?? '#'.$r['id']);
            if ($collection !== null) {
                $sections[] = self::section($group, [], [$collection]);
            }
        }

        if (isset($mapped['social_links'])) {
            $live = $artist->socialLinks()->get()->map(fn ($l) => [
                'id' => $l->id, 'platform' => $l->platform, 'url' => $l->url, 'is_public' => $l->is_public,
            ])->all();
            $incoming = array_map(fn (array $l) => [
                'id' => $l['id'] ?? null, 'platform' => $l['platform'], 'url' => $l['url'], 'is_public' => $l['is_public'] ?? false,
            ], $mapped['social_links']);
            $collection = self::collection('social_links', $live, $incoming, self::labels(ArtistSocialLink::class), fn (array $r) => $r['platform'].' — '.$r['url']);
            if ($collection !== null) {
                $sections[] = self::section('social_links', [], [$collection]);
            }
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, array<string, mixed>>
     */
    private static function artwork(Artwork $artwork, array $mapped): array
    {
        $sections = [];

        if (isset($mapped['fields'])) {
            $sections[] = self::section('fields', self::flatFields(Artwork::class, $artwork, $mapped['fields']));
        }

        if (isset($mapped['pipeline'])) {
            $live = ArtworkPipelineStage::query()->where('artwork_id', $artwork->id)->get()
                ->map(fn (ArtworkPipelineStage $s) => [
                    'id' => $s->id, 'stage_key' => $s->stage_key, 'status' => $s->status, 'note' => $s->note, 'linked_file_id' => $s->linked_file_id,
                ])->all();
            $incoming = array_map(fn (array $s) => [
                'id' => null, 'stage_key' => $s['stage_key'], 'status' => $s['status'] ?? null,
                'note' => $s['note'] ?? null, 'linked_file_id' => $s['linked_file_id'] ?? null,
            ], $mapped['pipeline']);
            // Pipeline rows are keyed by stage_key, not id: the sync matches by stage_key.
            foreach ($live as $i => $row) {
                $live[$i]['id'] = $row['stage_key'];
            }
            $collection = self::collection('pipeline', $live, $incoming, self::labels(ArtworkPipelineStage::class), fn (array $r) => (string) $r['stage_key']);
            if ($collection !== null) {
                $sections[] = self::section('pipeline', [], [$collection]);
            }
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, array<string, mixed>>
     */
    private static function event(Event $event, array $mapped): array
    {
        $sections = [];

        if (isset($mapped['fields'])) {
            $sections[] = self::section('fields', self::flatFields(Event::class, $event, $mapped['fields']));
        }

        if (isset($mapped['participants'])) {
            $live = $event->participants()->get()
                ->map(fn (EventParticipant $p) => [
                    'id' => $p->id, 'participant_type' => $p->participant_type, 'participant_id' => $p->participant_id,
                    'role' => $p->role, 'note' => $p->note,
                ])->all();
            $incoming = array_map(fn (array $p) => [
                'id' => $p['id'] ?? null, 'participant_type' => $p['type'] === 'artist' ? Artist::class : Artwork::class,
                'participant_id' => $p['participant_id'], 'role' => $p['role'], 'note' => $p['note'] ?? null,
            ], $mapped['participants']);

            // Resolve display names once for live and incoming participants.
            $idsByType = [];
            foreach ([...$live, ...$incoming] as $r) {
                $idsByType[$r['participant_type']][] = $r['participant_id'];
            }
            $names = [];
            foreach ($idsByType as $class => $ids) {
                $class::query()->whereIn('id', array_unique($ids))->get()
                    ->each(function ($m) use (&$names, $class) {
                        $names["{$class}:{$m->id}"] = $m->name_en ?? $m->name_ar ?? $m->title_en ?? $m->title_ar ?? null;
                    });
            }
            $labelOf = function (array $r) use ($names) {
                $name = $names["{$r['participant_type']}:{$r['participant_id']}"] ?? null;

                return $name !== null && trim((string) $name) !== ''
                    ? (string) $name
                    : class_basename($r['participant_type']).' #'.$r['participant_id'];
            };

            $collection = self::collection('participants', $live, $incoming, self::labels(EventParticipant::class), $labelOf);
            if ($collection !== null) {
                $sections[] = self::section('participants', [], [$collection]);
            }
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, array<string, mixed>>
     */
    private static function archiveItem(ArchiveItem $item, array $mapped): array
    {
        return [self::section('fields', self::flatFields(ArchiveItem::class, $item, $mapped['fields'] ?? []))];
    }

    /**
     * @param  array<string, mixed>  $mapped  column => proposed value
     * @return array<int, array<string, mixed>>
     */
    private static function flatFields(string $modelClass, Model $record, array $mapped): array
    {
        $labels = self::labels($modelClass);
        $out = [];
        foreach ($mapped as $column => $proposed) {
            $current = $record->getAttribute($column);
            if (! FieldValues::differ($current, $proposed)) {
                continue;
            }
            $out[] = self::field($column, $current, $proposed, $labels);
        }

        return $out;
    }

    /**
     * Child rows matched by `id` (pipeline by `stage_key`, smuggled in as id):
     * unknown ids are additions, unseen live rows are removals, the rest are
     * changed-field rows; a pure reorder is a change too.
     *
     * @param  array<int, array<string, mixed>>  $live
     * @param  array<int, array<string, mixed>>  $incoming
     * @param  array<string, array{ar: string, en: string}>  $labels
     * @param  callable(array<string, mixed>): string  $labelOf
     * @return array<string, mixed>|null
     */
    private static function collection(string $key, array $live, array $incoming, array $labels, callable $labelOf): ?array
    {
        $liveById = [];
        foreach (array_values($live) as $position => $row) {
            $liveById[$row['id']] = ['row' => $row, 'position' => $position];
        }

        $added = $removed = $changed = [];
        $seen = [];

        foreach (array_values($incoming) as $position => $item) {
            $id = $item['id'] ?? null;
            if ($id === null || ! isset($liveById[$id])) {
                $fields = [];
                foreach ($item as $column => $value) {
                    if ($column === 'id') {
                        continue;
                    }
                    $fields[] = self::field($column, null, $value, $labels);
                }
                $added[] = ['label' => $labelOf($item), 'fields' => $fields];

                continue;
            }

            $seen[] = $id;
            $row = $liveById[$id]['row'];
            $fields = [];
            if ($liveById[$id]['position'] !== $position) {
                $fields[] = self::field('sort', $liveById[$id]['position'], $position, $labels);
            }
            unset($item['id']);
            foreach ($item as $column => $value) {
                if (FieldValues::differ($row[$column] ?? null, $value)) {
                    $fields[] = self::field($column, $row[$column] ?? null, $value, $labels);
                }
            }
            if ($fields !== []) {
                $changed[] = ['label' => $labelOf($row), 'fields' => $fields];
            }
        }

        foreach ($liveById as $id => $meta) {
            if (in_array($id, $seen, true)) {
                continue;
            }
            $fields = [];
            foreach ($meta['row'] as $column => $value) {
                if ($column === 'id') {
                    continue;
                }
                $fields[] = self::field($column, $value, null, $labels);
            }
            $removed[] = ['label' => $labelOf($meta['row']), 'fields' => $fields];
        }

        if ($added === [] && $removed === [] && $changed === []) {
            return null;
        }

        return ['key' => $key, 'added' => $added, 'removed' => $removed, 'changed' => $changed];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<int, array<string, mixed>>  $collections
     * @return array<string, mixed>
     */
    private static function section(string $key, array $fields, array $collections = []): array
    {
        return ['key' => $key, 'fields' => $fields, 'collections' => $collections];
    }

    /**
     * @param  array<string, array{ar: string, en: string}>  $labels
     * @return array<string, mixed>
     */
    private static function field(string $column, mixed $old, mixed $new, array $labels): array
    {
        return [
            'field' => $column,
            'label' => $labels[$column] ?? ['ar' => $column, 'en' => $column],
            'old' => FieldValues::normalize($old),
            'new' => FieldValues::normalize($new),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, array{ar: string, en: string}>
     */
    private static function labels(string $modelClass): array
    {
        return method_exists($modelClass, 'activityFieldLabels') ? $modelClass::activityFieldLabels() : [];
    }
}
