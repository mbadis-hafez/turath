<?php

namespace App\Support\Ocr\Matching;

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;
use App\Models\Holder;
use App\Models\Source;
use App\Models\User;
use App\Support\ArabicNormalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * A reviewer's own search for the record a name means, when the matcher's
 * candidates don't include it — the same tokenised search the registry pages
 * use. Finding a record here proves nothing either: the reviewer confirms it.
 *
 * A private holder is only found by its real name for someone who may see
 * that name (holders.manage, D25); for anyone else, searching it by name
 * would reveal what the display name hides.
 */
class EntitySearch
{
    public const LIMIT = 10;

    public function __construct(private readonly EntityMatchPresenter $presenter) {}

    /**
     * @return list<array{id: int|string, label: array{ar: string, en: string}, detail: ?string}>
     */
    public function search(string $type, string $query, ?User $viewer): array
    {
        $raw = array_values(array_filter(preg_split('/\s+/u', mb_substr(trim($query), 0, 100)) ?: [], fn (string $t) => $t !== ''));
        if (mb_strlen(implode('', $raw)) < 2) {
            return [];
        }
        // search_text is stored normalized; plain name columns are matched as typed or normalized.
        $tokens = array_map(fn (string $t) => [$t, ArabicNormalizer::normalize($t)], $raw);

        $ids = match ($type) {
            'artist' => $this->bySearchText(Artist::query(), $tokens),
            'artwork' => $this->bySearchText(Artwork::query(), $tokens),
            'event' => $this->bySearchText(Event::query(), $tokens),
            'holder' => $this->byColumns(
                Holder::query()->when(! ($viewer?->can('holders.manage') ?? false), fn (Builder $q) => $q->where('is_public_name', true)),
                ['name_ar', 'name_en'], $tokens,
            ),
            'source' => $this->byColumns(Source::query(), ['title_ar', 'title_en'], $tokens),
            default => [],
        };

        $described = $this->presenter->describe($type, $ids, $viewer);

        $out = [];
        foreach ($ids as $id) {
            if (isset($described[$id])) {
                $out[] = ['id' => $id, ...$described[$id]];
            }
        }

        return $out;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<array{0: string, 1: string}>  $tokens  [as typed, normalized]
     * @return list<int|string>
     */
    private function bySearchText(Builder $query, array $tokens): array
    {
        foreach ($tokens as [, $normalized]) {
            $query->where('search_text', 'like', $this->like($normalized));
        }

        return $query->orderBy('id')->limit(self::LIMIT)->pluck('id')->values()->all();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @param  list<array{0: string, 1: string}>  $tokens  [as typed, normalized]
     * @return list<int|string>
     */
    private function byColumns(Builder $query, array $columns, array $tokens): array
    {
        foreach ($tokens as [$typed, $normalized]) {
            $query->where(function (Builder $w) use ($columns, $typed, $normalized) {
                foreach ($columns as $column) {
                    $w->orWhere($column, 'like', $this->like($typed))->orWhere($column, 'like', $this->like($normalized));
                }
            });
        }

        return $query->orderBy('id')->limit(self::LIMIT)->pluck('id')->values()->all();
    }

    private function like(string $token): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $token).'%';
    }
}
