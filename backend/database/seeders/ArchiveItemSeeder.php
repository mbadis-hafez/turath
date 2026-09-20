<?php

namespace Database\Seeders;

use App\Enums\AccessLevel;
use App\Enums\PublicationStatus;
use App\Models\ArchiveItem;
use App\Models\Artist;
use Illuminate\Database\Seeder;

class ArchiveItemSeeder extends Seeder
{
    /**
     * A real, privacy-scrubbed subset from the source material (no owner
     * phone/email/address, no fabricated internal_notes). Per D22, every
     * seeded item defaults to access_level = institution_only and
     * publication_status = draft — nothing is pre-published, even for
     * review. No file uploads in the seeder itself: these exist as
     * metadata-only records with zero `files` rows.
     *
     * @var array<int, array{ref: string, type: string, title_en: string, artists: array<int, array{code: string, role: string}>}>
     */
    private const ITEMS = [
        [
            'ref' => 'AR036_Almaghlout_SV061224_ARCIMG002',
            'type' => 'image',
            'title_en' => 'Ahmad Almaghlouth at the Monte Carlo prize',
            'artists' => [['code' => 'AR036', 'role' => 'subject']],
        ],
        [
            'ref' => 'AR049_Dia_SV221224_ARTCL003',
            'type' => 'article',
            'title_en' => 'The Gate of Mecca sculpture',
            'artists' => [['code' => 'AR049', 'role' => 'subject']],
        ],
        [
            'ref' => 'AR049_Dia_SV221224_ARCVID001',
            'type' => 'video',
            'title_en' => '1st personal exhibition, 1969',
            'artists' => [['code' => 'AR049', 'role' => 'subject']],
        ],
        [
            'ref' => 'MU002_Daratsb_SV050125_ARTCL001',
            'type' => 'article',
            'title_en' => 'Two Saudi girls hold a painting exhibition, 1968',
            'artists' => [],
        ],
        [
            'ref' => 'MU002_Daratsb_SV020625_CAT002',
            'type' => 'catalogue',
            'title_en' => 'Past and Present Traditions, Aramco 1976',
            'artists' => [],
        ],
        [
            'ref' => 'AR013_Radwi_ARW009_CAT',
            'type' => 'catalogue',
            'title_en' => 'Echoing the Land exhibition catalogue',
            'artists' => [
                ['code' => 'AR013', 'role' => 'mentions'],
                ['code' => 'AR003', 'role' => 'mentions'],
            ],
        ],
    ];

    public function run(): void
    {
        // Safeya Binzagr has no legacy_code in F1's fixtures; matched by name.
        $safeya = Artist::where('name_en', 'Safeya Binzagr')->first();

        foreach (self::ITEMS as $data) {
            $item = ArchiveItem::withTrashed()->firstOrCreate(
                ['legacy_ref' => $data['ref']],
                [
                    'item_type' => $data['type'],
                    'title_en' => $data['title_en'],
                    'access_level' => AccessLevel::InstitutionOnly->value,
                    'publication_status' => PublicationStatus::Draft->value,
                ],
            );

            $artists = $data['artists'] !== []
                ? $data['artists']
                : (in_array($data['ref'], ['MU002_Daratsb_SV050125_ARTCL001', 'MU002_Daratsb_SV020625_CAT002'], true) && $safeya !== null
                    ? [['code' => null, 'role' => 'subject']]
                    : []);

            foreach ($artists as $artistRef) {
                $artist = $artistRef['code'] !== null
                    ? Artist::where('legacy_code', $artistRef['code'])->first()
                    : $safeya;

                if ($artist === null) {
                    continue;
                }

                $item->links()->firstOrCreate([
                    'linkable_type' => Artist::class,
                    'linkable_id' => $artist->id,
                    'role' => $artistRef['role'],
                ]);
            }
        }
    }
}
