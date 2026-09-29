<?php

namespace Database\Seeders;

use App\Enums\AttributionCertainty;
use App\Enums\HolderType;
use App\Enums\PublicationStatus;
use App\Enums\VerifiedStatus;
use App\Models\Artist;
use App\Models\ArtistContact;
use App\Models\Artwork;
use App\Models\Holder;
use App\Support\DimensionParser;
use App\ValueObjects\PartialDate;
use Illuminate\Database\Seeder;

/**
 * Bulk-loads the "Inventory"-flagged rows of the source project's full
 * artwork spreadsheet (948 rows across the "AR001 - AR100" and
 * "AR101 - AR250 Master Sheet Artwork" tabs) — the F4 import ArtworkSeeder's
 * docblock earmarks but never does itself. Idempotent by legacy_ref/legacy_code,
 * like ArtistSeeder/ArtworkSeeder.
 *
 * Data lives in database/seeders/data/*.json rather than inline PHP: two
 * orders of magnitude bigger than the hand-curated fixtures in
 * ArtistSeeder/ArtworkSeeder, and produced by re-running the same
 * spreadsheet-to-fixture script rather than typed by hand.
 *
 * Privacy: owners get a real Holder row per distinct name (legacy_holders.json),
 * typed from the sheet's "Owner Type" column. is_public_name follows the same
 * rule HolderPayloadRequest/HolderImporter already use elsewhere: true only
 * for institutions, false for everyone else (private collectors, families,
 * "other") — so HolderDisplayResolver keeps those real names out of the
 * public API exactly like the hand-curated "Talal Kurdi" holder already does.
 * Owners that match one of the 8 holders already reviewed in HolderSeeder
 * link to that existing row instead of creating a near-duplicate.
 *
 * Key contact name/phone/email go on the *artist's* contact list
 * (artist_contacts — encrypted at rest, internal-only per D100), not the
 * holder: the sheet records a contact per artwork row, and in practice
 * that's who to reach about the artist's works in general. The same artist
 * can have several distinct contacts across their rows (different owners,
 * different eras of contact) — all of them are kept, deduped by
 * (name, phone, email), rather than guessing at "the" contact.
 *
 * Every artist/artwork/holder this seeder creates lands as draft/unverified
 * (or non-public, for holders): unlike ArtistSeeder's 18 hand-reviewed
 * artists, nothing here has had a per-row review, so D31 (imports never
 * auto-publish) applies throughout.
 *
 * Not part of DatabaseSeeder's default chain — ArtistSeederTest pins that
 * chain's artist count in every environment including testing, and this is
 * a one-time real-data load, not a fixture. Run explicitly:
 * `php artisan db:seed --class=LegacyArtworkInventorySeeder`.
 */
class LegacyArtworkInventorySeeder extends Seeder
{
    /**
     * holder_key (as written into legacy_artworks.json/legacy_holders.json)
     * => how to find an already-seeded Holder row. Keep in sync with
     * HolderSeeder::HOLDERS.
     *
     * @var array<string, array{code: string|null, name_en: string|null}>
     */
    private const CURATED_HOLDER_LOOKUP = [
        'moc' => ['code' => null, 'name_en' => 'MoC Collection'],
        'altaybat' => ['code' => 'MU001', 'name_en' => null],
        'barjeel' => ['code' => 'COL001', 'name_en' => null],
        'darat_safeya' => ['code' => 'MU002', 'name_en' => null],
        'mathaf' => ['code' => null, 'name_en' => 'Mathaf: Arab Museum of Modern Art'],
        'almansouria' => ['code' => null, 'name_en' => 'Almansouria Foundation for Culture and Creativity'],
        'talal_kurdi' => ['code' => 'COL003', 'name_en' => null],
    ];

    public function run(): void
    {
        $dataPath = database_path('seeders/data');

        $artists = $this->readJson("{$dataPath}/legacy_artists.json");
        $artworks = $this->readJson("{$dataPath}/legacy_artworks.json");
        $holders = $this->readJson("{$dataPath}/legacy_holders.json");
        $contacts = $this->readJson("{$dataPath}/legacy_artist_contacts.json");

        foreach ($artists as $data) {
            Artist::withTrashed()->firstOrCreate(
                ['legacy_code' => $data['code']],
                [
                    'name_ar' => $data['name_ar'],
                    'name_en' => $data['name_en'],
                    'publication_status' => PublicationStatus::Draft->value,
                    'verified_status' => VerifiedStatus::Unverified->value,
                    // Imported legacy inventory is pre-existing content, not a
                    // new editorial creation — it must not sit in creation review.
                    'creation_approved_at' => now(),
                ],
            );
        }

        foreach ($contacts as $data) {
            $artist = Artist::where('legacy_code', $data['artist_code'])->first();

            if ($artist === null || $data['name'] === null) {
                continue;
            }

            ArtistContact::firstOrCreate(
                ['artist_id' => $artist->id, 'name' => $data['name']],
                ['role_note' => $data['role_note'], 'email' => $data['email'], 'phone' => $data['phone']],
            );
        }

        $holderIds = $this->resolveCuratedHolderIds();

        foreach ($holders as $data) {
            $holderIds[$data['key']] ??= $this->findOrCreateHolder($data)->id;
        }

        foreach ($artworks as $data) {
            $this->importArtwork($data, $holderIds);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readJson(string $path): array
    {
        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function findOrCreateHolder(array $data): Holder
    {
        return Holder::withTrashed()->firstOrCreate(
            ['name_en' => $data['name_en']],
            [
                'type' => $data['type'],
                'name_ar' => $data['name_ar'],
                'city_en' => $data['city_en'],
                'city_ar' => $data['city_ar'],
                'is_public_name' => $data['type'] === HolderType::Institution->value,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $holderIds
     */
    private function importArtwork(array $data, array $holderIds): void
    {
        $artist = Artist::where('legacy_code', $data['artist_code'])->first();

        $dims = $data['dimensions_raw'] !== null ? DimensionParser::parse($data['dimensions_raw']) : null;
        $frameDims = $data['frame_dimensions_raw'] !== null ? DimensionParser::parse($data['frame_dimensions_raw']) : null;

        Artwork::withTrashed()->firstOrCreate(
            ['legacy_ref' => $data['legacy_ref']],
            [
                'artist_id' => $artist?->id,
                'attribution_certainty' => $artist !== null ? AttributionCertainty::Confirmed->value : AttributionCertainty::Unattributed->value,
                'title_ar' => $data['title_ar'],
                'title_en' => $data['title_en'],
                'is_untitled' => $data['is_untitled'],
                'category' => $data['category'],
                'medium_ar' => $data['medium_ar'],
                'medium_en' => $data['medium_en'],
                'notes_ar' => $data['notes_ar'],
                'notes_en' => $data['notes_en'],
                'edition_number' => $data['edition_number'],
                'height_cm' => $dims?->heightCm,
                'width_cm' => $dims?->widthCm,
                'depth_cm' => $dims?->depthCm,
                'dimensions_raw' => $data['dimensions_raw'],
                'frame_height_cm' => $frameDims?->heightCm,
                'frame_width_cm' => $frameDims?->widthCm,
                'frame_depth_cm' => $frameDims?->depthCm,
                'frame_dimensions_raw' => $data['frame_dimensions_raw'],
                'signed' => $data['signed'],
                'creation' => $data['year_raw'] !== null ? PartialDate::fromString($data['year_raw']) : null,
                'holder_id' => $data['holder_key'] !== null ? ($holderIds[$data['holder_key']] ?? null) : null,
                'holder_inventory_no' => $data['holder_inventory_no'],
                'condition_report_status' => $data['condition_report_status'],
                'publication_status' => PublicationStatus::Draft->value,
                'creation_approved_at' => now(),
            ],
        );
    }

    /** @return array<string, int> */
    private function resolveCuratedHolderIds(): array
    {
        $ids = [];

        foreach (self::CURATED_HOLDER_LOOKUP as $key => $lookup) {
            $holder = $lookup['code'] !== null
                ? Holder::where('legacy_code', $lookup['code'])->first()
                : Holder::where('name_en', $lookup['name_en'])->first();

            if ($holder !== null) {
                $ids[$key] = $holder->id;
            }
        }

        return $ids;
    }
}
