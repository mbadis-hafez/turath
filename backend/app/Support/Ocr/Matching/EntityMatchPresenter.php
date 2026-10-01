<?php

namespace App\Support\Ocr\Matching;

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;
use App\Models\FileEntityMatch;
use App\Models\Holder;
use App\Models\Source;
use App\Models\User;
use App\Support\HolderDisplayResolver;
use Illuminate\Support\Collection;

/**
 * Matches for the review UI, with each candidate's label read from its
 * record now (so a renamed record shows its current name, and a private
 * holder shows only its display name to anyone without holders.manage —
 * D25). A candidate whose record has since been deleted is marked missing.
 */
class EntityMatchPresenter
{
    /**
     * @param  iterable<FileEntityMatch>  $matches
     * @return array<int, array<string, mixed>> keyed by extracted field id
     */
    public function present(iterable $matches, ?User $viewer): array
    {
        $matches = collect($matches);
        $records = $this->records($matches, $viewer);

        $out = [];
        foreach ($matches as $match) {
            $describe = fn (int|string|null $id, ?string $key) => $match->entity_type === 'place'
                ? ['label' => $key === null ? null : ['ar' => $key, 'en' => $key], 'detail' => null, 'missing' => false]
                : ($records[$match->entity_type][(string) $id] ?? ['label' => null, 'detail' => null, 'missing' => true]);

            $out[$match->extracted_field_id] = [
                'id' => $match->id,
                'entity_type' => $match->entity_type,
                'source_text' => $match->source_text,
                'status' => $match->status,
                // A score is text similarity, never proof: every match needs a person.
                'requires_review' => $match->status === FileEntityMatch::STATUS_PENDING,
                'candidates' => array_map(fn (array $c) => [...$c, ...$describe($c['id'], $c['key'])], $match->candidates ?? []),
                'confirmed' => $match->status === FileEntityMatch::STATUS_CONFIRMED
                    ? ['id' => $match->confirmed_entity_id, 'key' => $match->confirmed_key, ...$describe($match->confirmed_entity_id, $match->confirmed_key)]
                    : null,
                'reviewed_at' => $match->reviewed_at?->toIso8601String(),
            ];
        }

        return $out;
    }

    /**
     * Every record a candidate or confirmation points at, loaded once per type.
     *
     * @param  Collection<int, FileEntityMatch>  $matches
     * @return array<string, array<string, array{label: array{ar: string, en: string}, detail: ?string, missing: bool}>>
     */
    private function records(Collection $matches, ?User $viewer): array
    {
        $ids = [];
        foreach ($matches as $match) {
            foreach ([...array_column($match->candidates ?? [], 'id'), $match->confirmed_entity_id] as $id) {
                if ($id !== null) {
                    $ids[$match->entity_type][] = $id;
                }
            }
        }

        $records = [];
        foreach ($ids as $type => $typeIds) {
            $typeIds = array_values(array_unique($typeIds));
            foreach ($this->describe($type, $typeIds, $viewer) as $id => $record) {
                $records[$type][(string) $id] = [...$record, 'missing' => false];
            }
        }

        return $records;
    }

    /**
     * Each record's label and a short detail, as this viewer may see them.
     *
     * @param  list<int|string>  $ids
     * @return array<int|string, array{label: array{ar: string, en: string}, detail: ?string}>
     */
    public function describe(string $type, array $ids, ?User $viewer): array
    {
        $label = fn (?string $ar, ?string $en) => ['ar' => $ar ?? $en ?? '', 'en' => $en ?? $ar ?? ''];

        return match ($type) {
            'artist' => Artist::query()->whereKey($ids)->get()->mapWithKeys(fn (Artist $a) => [$a->id => [
                'label' => $label($a->name_ar, $a->name_en),
                'detail' => $this->years($a->birth?->yearFrom, $a->death?->yearFrom),
            ]])->all(),
            'artwork' => Artwork::query()->with('artist')->whereKey($ids)->get()->mapWithKeys(fn (Artwork $w) => [$w->id => [
                'label' => $label($w->title_ar, $w->title_en),
                'detail' => implode(' · ', array_filter([$w->artist->name_ar ?? $w->artist?->name_en, $w->creation?->yearFrom, $w->holder_inventory_no])),
            ]])->all(),
            'event' => Event::query()->whereKey($ids)->get()->mapWithKeys(fn (Event $e) => [$e->id => [
                'label' => $label($e->title_ar, $e->title_en),
                'detail' => implode(' · ', array_filter([$e->start?->yearFrom, $e->city])),
            ]])->all(),
            'holder' => Holder::query()->whereKey($ids)->get()->mapWithKeys(fn (Holder $h) => [$h->id => [
                'label' => $viewer?->can('holders.manage') ? $label($h->name_ar, $h->name_en) : HolderDisplayResolver::resolve($h),
                'detail' => $h->city_ar ?? $h->city_en,
            ]])->all(),
            'source' => Source::query()->whereKey($ids)->get()->mapWithKeys(fn (Source $s) => [$s->id => [
                'label' => $label($s->title_ar, $s->title_en),
                'detail' => implode(' · ', array_filter([$s->publisher_or_outlet, $s->year])),
            ]])->all(),
            default => [],
        };
    }

    private function years(?int $from, ?int $to): ?string
    {
        return $from === null && $to === null ? null : ($from ?? '?').'–'.($to ?? '');
    }
}
