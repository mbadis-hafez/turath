<?php

namespace App\Support\Ocr\Matching;

use App\Enums\ImportMatchConfidence;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;
use App\Models\Holder;
use App\Models\Source;
use App\Support\ArabicDigits;
use App\Support\Ocr\ArtistCandidateFinder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Candidate records for a name or title read off a document. Returns
 * candidates, never a decision: a reviewer confirms one (or none), and even
 * a perfect text match is only shown as a strong candidate — two artists can
 * share a name, and OCR text is itself unverified.
 *
 * Each candidate carries:
 * - score: how alike the texts are, 0..1 (1 = equal after normalization).
 *   Text similarity only — not a probability that they are the same thing.
 * - strength: high / medium / low, from the evidence in `basis`.
 * - basis: the reasons, e.g. exact_title, same_artist, same_year.
 *
 * The rules follow the importer's (docs/decisions.md D34, D38, D39): artists
 * by normalized name and recorded variants (ArtistCandidateFinder);
 * artworks by reference or title, ranked by artist and dimensions; holders by
 * exact name only — institution names are short, and fuzzy-matching them is
 * riskier than people's names — or their full name inside the text.
 * Context never makes a candidate on its own.
 */
class EntityMatcher
{
    /** Version of these rules; part of the match stage's fingerprint. */
    public const VERSION = 'match-v1';

    private const LIMIT = 5;

    private const QUERY_LIMIT = 200;

    /** @var Collection<int, Holder>|null */
    private ?Collection $holders = null;

    /** @var Collection<int, Source>|null */
    private ?Collection $sources = null;

    /** @var array<string, array{label: string, uses: int}>|null */
    private ?array $places = null;

    public function __construct(private readonly ArtistCandidateFinder $artists = new ArtistCandidateFinder) {}

    /**
     * @return list<array{id: int|string|null, key: ?string, score: float, strength: string, basis: list<string>}>
     */
    public function match(string $type, string $text, MatchContext $context): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        $candidates = match ($type) {
            'artist' => $this->artists($text, $context),
            'artwork' => $this->artworks($text, $context),
            'event' => $this->events($text, $context),
            'holder' => $this->holders($text),
            'source' => $this->sources($text),
            'place' => $this->places($text),
            default => [],
        };

        $rank = [ImportMatchConfidence::High->value => 0, ImportMatchConfidence::Medium->value => 1, ImportMatchConfidence::Low->value => 2];
        usort($candidates, fn ($a, $b) => [$rank[$a['strength']], -$a['score'], (string) ($a['id'] ?? $a['key'])] <=> [$rank[$b['strength']], -$b['score'], (string) ($b['id'] ?? $b['key'])]);

        return array_slice($candidates, 0, self::LIMIT);
    }

    /**
     * @return list<array{id: int|string|null, key: ?string, score: float, strength: string, basis: list<string>}>
     */
    private function artists(string $text, MatchContext $context): array
    {
        $out = [];
        foreach ($this->artists->find($text, $context->archiveItem, 10) as $found) {
            // An artist linked to the item but sharing nothing with this text is not a candidate for it.
            if (array_intersect($found['basis'], ['exact_name', 'exact_variant', 'shared_name_parts']) === []) {
                continue;
            }
            $artist = $found['artist'];
            $names = array_filter([$artist->name_ar, $artist->name_en, ...$artist->variants->pluck('name')->all()], fn ($n) => is_string($n) && $n !== '');
            $exact = in_array('exact_name', $found['basis'], true) || in_array('exact_variant', $found['basis'], true);

            $out[] = $this->candidate($artist->id, $exact ? 1.0 : $this->bestSimilarity($text, $names), $found['strength']->value, $found['basis']);
        }

        return $out;
    }

    /**
     * An artwork by its reference (inventory number, legacy ref) or its title,
     * ranked like the importer's dedupe: same artist, and dimensions within 0.5 cm.
     *
     * @return list<array{id: int|string|null, key: ?string, score: float, strength: string, basis: list<string>}>
     */
    private function artworks(string $text, MatchContext $context): array
    {
        $tokens = NameTokens::of($text);
        $query = Artwork::query()->where(function ($q) use ($tokens, $text) {
            $q->where('holder_inventory_no', $text)->orWhere('legacy_ref', $text);
            foreach ($tokens as $token) {
                $q->orWhere('search_text', 'like', '%'.$token.'%');
            }
        });

        $out = [];
        foreach ($query->limit(self::QUERY_LIMIT)->get() as $artwork) {
            $basis = [];
            $points = 0;
            $score = 0.0;

            $reference = collect([$artwork->holder_inventory_no, $artwork->legacy_ref])->first(fn ($r) => is_string($r) && NameTokens::same($r, $text));
            if ($reference !== null) {
                [$basis[], $points, $score] = ['exact_reference', 4, 1.0];
            } else {
                $score = $this->bestSimilarity($text, array_filter([$artwork->title_ar, $artwork->title_en], fn ($t) => is_string($t) && $t !== ''));
                $titleExact = collect([$artwork->title_ar, $artwork->title_en])->contains(fn ($t) => is_string($t) && NameTokens::same($t, $text));
                if ($titleExact || $score >= 1.0) {
                    [$basis[], $points, $score] = ['exact_title', 2, 1.0];
                } elseif ($score >= 0.6) {
                    [$basis[], $points] = ['similar_title', 1];
                } else {
                    continue;
                }
            }

            if ($artwork->artist_id !== null && in_array($artwork->artist_id, $context->artistIds, true)) {
                [$basis[], $points] = ['same_artist', $points + 1];
            }
            $dimensions = $context->dimensions;
            if ($dimensions?->heightCm !== null && $artwork->height_cm !== null && abs($dimensions->heightCm - (float) $artwork->height_cm) < 0.5) {
                [$basis[], $points] = ['same_height', $points + 1];
            }
            if ($dimensions?->widthCm !== null && $artwork->width_cm !== null && abs($dimensions->widthCm - (float) $artwork->width_cm) < 0.5) {
                [$basis[], $points] = ['same_width', $points + 1];
            }

            $out[] = $this->candidate($artwork->id, $score, $this->tier($points, high: 4, medium: 2), $basis);
        }

        return $out;
    }

    /**
     * An exhibition (or other event) by title, ranked by year and city.
     *
     * @return list<array{id: int|string|null, key: ?string, score: float, strength: string, basis: list<string>}>
     */
    private function events(string $text, MatchContext $context): array
    {
        $tokens = NameTokens::of($text);
        if ($tokens === []) {
            return [];
        }
        $query = Event::query()->where(function ($q) use ($tokens) {
            foreach ($tokens as $token) {
                $q->orWhere('search_text', 'like', '%'.$token.'%');
            }
        });
        $years = [...$context->years, ...$this->yearsIn($text)];

        $out = [];
        foreach ($query->limit(self::QUERY_LIMIT)->get() as $event) {
            [$score, $basis, $points] = $this->titleEvidence($text, [$event->title_ar, $event->title_en], 0.5);
            if ($points === 0) {
                continue;
            }
            $eventYears = array_filter([$event->start?->yearFrom, $event->start?->yearTo, $event->end?->yearFrom, $event->end?->yearTo]);
            if (array_intersect($eventYears, $years) !== []) {
                [$basis[], $points] = ['same_year', $points + 1];
            }
            if (is_string($event->city) && $event->city !== '' && (($context->city !== null && NameTokens::same($event->city, $context->city)) || NameTokens::within($event->city, $text))) {
                [$basis[], $points] = ['same_city', $points + 1];
            }

            $out[] = $this->candidate($event->id, $score, $this->tier($points, high: 3, medium: 2), $basis);
        }

        return $out;
    }

    /**
     * Exact name or code only, or the holder's whole name inside the text —
     * never a partial resemblance (D34).
     *
     * @return list<array{id: int|string|null, key: ?string, score: float, strength: string, basis: list<string>}>
     */
    private function holders(string $text): array
    {
        $this->holders ??= Holder::query()->get();

        $out = [];
        foreach ($this->holders as $holder) {
            $names = array_filter([$holder->name_ar, $holder->name_en], fn ($n) => is_string($n) && $n !== '');
            if ((is_string($holder->legacy_code) && NameTokens::same($holder->legacy_code, $text))
                || collect($names)->contains(fn ($n) => NameTokens::same($n, $text))) {
                $out[] = $this->candidate($holder->id, 1.0, ImportMatchConfidence::High->value, ['exact_name']);
            } elseif (collect($names)->contains(fn ($n) => count(NameTokens::of($n)) >= 2 && NameTokens::within($n, $text))) {
                $out[] = $this->candidate($holder->id, $this->bestSimilarity($text, $names), ImportMatchConfidence::Medium->value, ['name_within_text']);
            }
        }

        return $out;
    }

    /**
     * @return list<array{id: int|string|null, key: ?string, score: float, strength: string, basis: list<string>}>
     */
    private function sources(string $text): array
    {
        $this->sources ??= Source::query()->limit(2000)->get();
        $years = $this->yearsIn($text);

        $out = [];
        foreach ($this->sources as $source) {
            [$score, $basis, $points] = $this->titleEvidence($text, [$source->title_ar, $source->title_en], 0.6);
            if ($points === 0) {
                continue;
            }
            if ($source->year !== null && in_array((int) $source->year, $years, true)) {
                [$basis[], $points] = ['same_year', $points + 1];
            }
            if (is_string($source->publisher_or_outlet) && $source->publisher_or_outlet !== '' && NameTokens::within($source->publisher_or_outlet, $text)) {
                [$basis[], $points] = ['same_publisher', $points + 1];
            }

            $out[] = $this->candidate((string) $source->id, $score, $this->tier($points, high: 3, medium: 2), $basis);
        }

        return $out;
    }

    /**
     * Places already written somewhere in the records — artists' birth and
     * death places, event cities, holders' cities. There is no place table,
     * so a candidate is a spelling in use, not a record.
     *
     * @return list<array{id: int|string|null, key: ?string, score: float, strength: string, basis: list<string>}>
     */
    private function places(string $text): array
    {
        $this->places ??= $this->knownPlaces();
        $tokens = NameTokens::of($text);

        $out = [];
        foreach ($this->places as $place) {
            if ($tokens !== [] && NameTokens::of($place['label']) === $tokens) {
                $out[] = $this->candidate(null, 1.0, ImportMatchConfidence::High->value, ['exact_place'], $place['label']);
            } elseif (NameTokens::within($place['label'], $text)) {
                $out[] = $this->candidate(null, NameTokens::similarity($place['label'], $text), ImportMatchConfidence::Medium->value, ['place_within_text'], $place['label']);
            }
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, uses: int}>
     */
    private function knownPlaces(): array
    {
        $values = [
            ...Artist::query()->pluck('birth_place_ar')->all(), ...Artist::query()->pluck('birth_place_en')->all(),
            ...Artist::query()->pluck('death_place_ar')->all(), ...Artist::query()->pluck('death_place_en')->all(),
            ...Event::query()->pluck('city')->all(),
            ...Holder::query()->pluck('city_ar')->all(), ...Holder::query()->pluck('city_en')->all(),
        ];

        $places = [];
        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            $key = implode(' ', NameTokens::of($value));
            if ($key === '') {
                continue;
            }
            $places[$key] ??= ['label' => trim($value), 'uses' => 0];
            $places[$key]['uses']++;
        }

        return $places;
    }

    /**
     * @param  array<int, string|null>  $titles
     * @return array{0: float, 1: list<string>, 2: int} score, basis, points
     */
    private function titleEvidence(string $text, array $titles, float $similarAt): array
    {
        $titles = array_filter($titles, fn ($t) => is_string($t) && $t !== '');
        $score = $this->bestSimilarity($text, $titles);
        if ($score >= 1.0 || collect($titles)->contains(fn ($t) => NameTokens::same($t, $text))) {
            return [1.0, ['exact_title'], 2];
        }

        return $score >= $similarAt ? [$score, ['similar_title'], 1] : [$score, [], 0];
    }

    /**
     * @param  array<int|string, string>  $names
     */
    private function bestSimilarity(string $text, array $names): float
    {
        $best = 0.0;
        foreach ($names as $name) {
            $best = max($best, NameTokens::similarity($text, $name));
        }

        return $best;
    }

    private function tier(int $points, int $high, int $medium): string
    {
        return match (true) {
            $points >= $high => ImportMatchConfidence::High->value,
            $points >= $medium => ImportMatchConfidence::Medium->value,
            default => ImportMatchConfidence::Low->value,
        };
    }

    /**
     * @return list<int>
     */
    private function yearsIn(string $text): array
    {
        preg_match_all('/(?<!\d)(1[3-9]\d{2}|20\d{2})(?!\d)/u', ArabicDigits::toAscii($text), $m);

        return array_map('intval', $m[1]);
    }

    /**
     * @param  list<string>  $basis
     * @return array{id: int|string|null, key: ?string, score: float, strength: string, basis: list<string>}
     */
    private function candidate(int|string|null $id, float $score, string $strength, array $basis, ?string $key = null): array
    {
        return ['id' => $id, 'key' => $key, 'score' => round($score, 2), 'strength' => $strength, 'basis' => array_values(array_unique($basis))];
    }
}
