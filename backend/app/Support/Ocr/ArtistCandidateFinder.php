<?php

namespace App\Support\Ocr;

use App\Enums\ImportMatchConfidence;
use App\Models\ArchiveItem;
use App\Models\ArchiveItemLink;
use App\Models\Artist;
use App\Support\ArabicNormalizer;
use App\Support\Ocr\Matching\NameTokens;

/**
 * Suggests which existing Artist an authorization letter belongs to, from the
 * name as written on the document (reviewer-transcribed — handwriting is never
 * OCR-trusted) and from artists the archive item is already linked to.
 *
 * Suggestions only: a reviewer must confirm one explicitly before anything
 * else happens. The strength is an ordinal tier, not a probability — there is
 * no calibrated model behind it, just normalized string comparison:
 *
 * - high: the name equals the artist's name or one of its recorded variants
 *   after normalization, or it shares name parts with an artist already
 *   linked to this archive item;
 * - medium: shares two or more name parts (or the only part given), or is
 *   linked to the archive item with no name to compare;
 * - low: shares a single name part out of several.
 */
class ArtistCandidateFinder
{
    private const QUERY_LIMIT = 200;

    /**
     * @return array<int, array{artist: Artist, strength: ImportMatchConfidence, basis: array<int, string>, shared_parts: int}>
     */
    public function find(?string $name, ArchiveItem $item, int $limit = 5): array
    {
        $linkedIds = ArchiveItemLink::query()
            ->where('archive_item_id', $item->id)
            ->where('linkable_type', Artist::class)
            ->pluck('linkable_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $name = $name !== null ? trim($name) : '';
        $tokens = $name !== '' ? $this->tokens(ArabicNormalizer::normalize($name)) : [];
        $compact = $name !== '' ? ArabicNormalizer::compact($name) : '';

        $query = Artist::query()->with('variants');
        $query->where(function ($q) use ($linkedIds, $tokens, $compact) {
            $q->whereIn('id', $linkedIds === [] ? [0] : $linkedIds);
            // Normalization already turned every punctuation character (LIKE's % and _ included) into a space.
            foreach ($tokens as $token) {
                $q->orWhere('search_text', 'like', '%'.$token.'%');
            }
            if ($compact !== '') {
                $q->orWhere('search_compact', 'like', '%'.$compact.'%');
            }
        });

        $candidates = [];
        foreach ($query->limit(self::QUERY_LIMIT)->get() as $artist) {
            $candidate = $this->score($artist, $tokens, $compact, in_array($artist->id, $linkedIds, true));
            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        $rank = [ImportMatchConfidence::High->value => 0, ImportMatchConfidence::Medium->value => 1, ImportMatchConfidence::Low->value => 2];
        usort($candidates, fn ($a, $b) => [$rank[$a['strength']->value], -$a['shared_parts'], $a['artist']->id]
            <=> [$rank[$b['strength']->value], -$b['shared_parts'], $b['artist']->id]);

        return array_slice($candidates, 0, $limit);
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array{artist: Artist, strength: ImportMatchConfidence, basis: array<int, string>, shared_parts: int}|null
     */
    private function score(Artist $artist, array $tokens, string $compact, bool $linked): ?array
    {
        $basis = [];

        $ownNames = array_filter([$artist->name_ar, $artist->name_en], fn ($n) => is_string($n) && $n !== '');
        $variantNames = $artist->variants->pluck('name')->filter(fn ($n) => is_string($n) && $n !== '')->all();
        if ($compact !== '') {
            if (in_array($compact, array_map(ArabicNormalizer::compact(...), $ownNames), true)) {
                $basis[] = 'exact_name';
            } elseif (in_array($compact, array_map(ArabicNormalizer::compact(...), $variantNames), true)) {
                $basis[] = 'exact_variant';
            }
        }

        $artistTokens = $this->tokens(ArabicNormalizer::normalize(implode(' ', [...$ownNames, ...$variantNames])));
        $shared = count(array_intersect($tokens, $artistTokens));
        if ($shared > 0 && $basis === []) {
            $basis[] = 'shared_name_parts';
        }

        if ($linked) {
            $basis[] = 'linked_to_archive_item';
        }

        if ($basis === []) {
            return null;
        }

        $nameAgrees = in_array('exact_name', $basis, true) || in_array('exact_variant', $basis, true);
        $strongParts = $shared >= 2 || ($shared === 1 && count($tokens) === 1);

        $strength = match (true) {
            $nameAgrees, $linked && $shared > 0 => ImportMatchConfidence::High,
            $strongParts, $linked => ImportMatchConfidence::Medium,
            default => ImportMatchConfidence::Low,
        };

        return ['artist' => $artist, 'strength' => $strength, 'basis' => $basis, 'shared_parts' => $shared];
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $normalized): array
    {
        return NameTokens::of($normalized);
    }
}
