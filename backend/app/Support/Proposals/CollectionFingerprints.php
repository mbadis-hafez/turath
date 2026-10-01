<?php

namespace App\Support\Proposals;

use App\Models\Artist;
use App\Models\ArtistContact;
use Illuminate\Database\Eloquent\Model;

/**
 * A draft replaces a child collection wholesale (ChildSync deletes the rows
 * it doesn't list), so it is only safe to apply against the collection it was
 * written from: approving it after someone else changed that collection would
 * silently revert their change. The fingerprint taken when the draft was
 * written turns that into a conflict the reviewer has to confirm (D74).
 *
 * Only the artist's contacts are covered so far — the one collection holding
 * internal-only values, and the one document-sourced proposals write.
 * Keyed with the app key so a stored hash can't be brute-forced back into a
 * phone number.
 */
class CollectionFingerprints
{
    /**
     * Fingerprints of the collections a mapped draft payload replaces.
     *
     * @param  array<string, mixed>  $mapped  EditorialDraftService::mapSections output
     * @return array<string, string>|null
     */
    public static function forDraft(Model $record, array $mapped): ?array
    {
        $out = [];
        if ($record instanceof Artist && isset($mapped['curation']['contacts'])) {
            $out['contacts'] = self::contacts($record);
        }

        return $out === [] ? null : $out;
    }

    /** The current fingerprint of one collection, or null when the record has no such collection. */
    public static function current(Model $record, string $collection): ?string
    {
        return match (true) {
            $collection === 'contacts' && $record instanceof Artist => self::contacts($record),
            default => null,
        };
    }

    private static function contacts(Artist $artist): string
    {
        $rows = $artist->contacts()->orderBy('id')->get()->map(fn (ArtistContact $c) => [
            $c->id, $c->sort, $c->name, $c->role_note, $c->email, $c->phone, $c->address,
        ])->all();

        return hash_hmac('sha256', json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), (string) config('app.key'));
    }
}
