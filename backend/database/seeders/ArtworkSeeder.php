<?php

namespace Database\Seeders;

use App\Enums\AttributionCertainty;
use App\Enums\PublicationStatus;
use App\Enums\SignedStatus;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Holder;
use App\Support\DimensionParser;
use Illuminate\Database\Seeder;

class ArtworkSeeder extends Seeder
{
    /**
     * A representative real subset (not the full ~185-row spreadsheet, which
     * is F4's job) drawn from the source project's artwork inventory for
     * three of the artists already seeded in F1. Only title, medium,
     * dimensions, category, creation year and holder type are seeded — no
     * owner contact information anywhere, matching the privacy rules
     * established for holders. Deliberately includes untitled works,
     * unparseable/low-confidence dimension strings, works with and without
     * a holder, and a family-estate holder, so the UI and filters have real
     * data to exercise.
     *
     * @var array<int, array<string, mixed>>
     */
    private const ARTWORKS = [
        // Abdulhalim Radwi (AR013)
        ['artist' => 'AR013', 'ref' => 'AR013_Radwi_ARW001', 'title_ar' => 'صورة زيتية مع سمك', 'title_en' => 'Still Life With Fish', 'category' => 'painting', 'medium_ar' => 'زيت ورمل على ماسونيت', 'medium_en' => 'Oil and sand on masonite', 'dim' => '33H x 46W cm', 'frame_dim' => '45H x 57.5W x 2D cm', 'year' => 1975, 'signed' => true, 'holder' => 'barjeel'],
        ['artist' => 'AR013', 'ref' => 'AR013_Radwi_ARW002', 'title_ar' => null, 'title_en' => null, 'category' => 'painting', 'medium_ar' => 'الوان مائية على ورق', 'medium_en' => 'Watercolor on paper', 'dim' => '36H x 49W x 0D cm', 'frame_dim' => '48.5H x 62.3W x 1.5D cm', 'year' => 1989, 'year_display' => '1409 hijri; 1989', 'calendar' => 'hijri', 'signed' => true, 'holder' => 'altaybat'],
        ['artist' => 'AR013', 'ref' => 'AR013_Radwi_ARW003', 'title_ar' => 'فلسطين', 'title_en' => 'Palestine', 'category' => 'mixed_media', 'medium_ar' => 'خامات مختلفة على قماش', 'medium_en' => 'Mixed media on canvas', 'dim' => '121 x 76 cm', 'year' => 1997, 'signed' => true, 'holder' => 'barjeel'],
        ['artist' => 'AR013', 'ref' => 'AR013_Radwi_ARW004', 'title_ar' => null, 'title_en' => null, 'category' => 'painting', 'medium_ar' => 'زيت على قماش', 'medium_en' => 'Oil on canvas', 'dim' => '80H x 48W x 1D cm', 'frame_dim' => '92H x 60W x 4D cm', 'year' => 1971, 'signed' => true, 'holder' => null],
        ['artist' => 'AR013', 'ref' => 'AR013_Radwi_ARW007', 'title_ar' => 'رقص شعبي', 'title_en' => 'Folk Dance', 'category' => 'painting', 'medium_ar' => 'زيت على قماش', 'medium_en' => 'Oil on canvas', 'dim' => 'Not available - tbc', 'year' => 1987, 'signed' => true, 'holder' => null],
        ['artist' => 'AR013', 'ref' => 'AR013_Radwi_ARW008', 'title_ar' => null, 'title_en' => null, 'category' => 'painting', 'medium_ar' => 'أكريليك على قماش', 'medium_en' => 'Acrylic on canvas', 'dim' => 'Not available - tbc', 'year' => 1983, 'signed' => true, 'holder' => null],
        ['artist' => 'AR013', 'ref' => 'AR013_Radwi_ARW015', 'title_ar' => 'دون عنوان ١١', 'title_en' => 'Untitled 11', 'category' => 'painting', 'medium_ar' => 'زيت على قماش', 'medium_en' => 'Oil on canvas', 'dim' => '99H x 200W cm', 'frame_dim' => '130H x 231W cm', 'year' => 1982, 'signed' => true, 'holder' => 'radwi_estate'],

        // Abdullah Alsheikh (AR003)
        ['artist' => 'AR003', 'ref' => 'AR003_Alsheikh_ARW001', 'title_ar' => 'تكوين شرقي', 'title_en' => 'Eastern Composition', 'category' => 'painting', 'medium_ar' => 'زيت على قماش', 'medium_en' => 'Oil on canvas', 'dim' => '61H x 91W x 2D cm', 'signed' => true, 'holder' => 'talal_kurdi'],
        ['artist' => 'AR003', 'ref' => 'AR003_Alsheikh_ARW003', 'title_ar' => 'عنترة', 'title_en' => 'Antarah', 'category' => 'painting', 'medium_ar' => 'زيت على خشب', 'medium_en' => 'Oil on wood', 'dim' => '61H x 91W cm', 'year' => 1981, 'signed' => true, 'holder' => null],
        ['artist' => 'AR003', 'ref' => 'AR003_Alsheikh_ARW004', 'title_ar' => null, 'title_en' => null, 'category' => 'painting', 'medium_ar' => 'زيت أو أكريليك على لوح ليفي', 'medium_en' => 'Oil or acrylic on fiberboard', 'dim' => 'tbc', 'year' => 1986, 'signed' => true, 'holder' => null],
        ['artist' => 'AR003', 'ref' => 'AR003_Alsheikh_ARW005', 'title_ar' => 'اسلاميات', 'title_en' => 'Islamic Fatures', 'category' => 'painting', 'medium_ar' => 'زيت على قماش', 'medium_en' => 'Oil on canvas', 'dim' => '92H X 123W cm', 'year' => 1988, 'signed' => true, 'holder' => 'moc'],
        ['artist' => 'AR003', 'ref' => 'AR003_Alsheikh_ARW007', 'title_ar' => 'الحمدلله', 'title_en' => 'Thanks God', 'category' => 'printmaking', 'medium_ar' => 'طباعة على الورق', 'medium_en' => 'Print on paper', 'dim' => '48H X 28W cm', 'year' => 1998, 'signed' => true, 'holder' => 'moc'],
        ['artist' => 'AR003', 'ref' => 'AR003_Alsheikh_ARW009', 'title_ar' => null, 'title_en' => null, 'category' => 'painting', 'medium_ar' => 'زيت على قماش', 'medium_en' => 'Oil on canvas', 'dim' => '91,5H x 122W cm', 'year' => 1993, 'signed' => true, 'holder' => 'almansouria'],

        // Abdullah Nawawi (AR004)
        ['artist' => 'AR004', 'ref' => 'AR004_Nawawi_ARW001', 'title_ar' => null, 'title_en' => null, 'category' => 'printmaking', 'medium_ar' => 'الطباعة بواسطة الحجر', 'medium_en' => 'Lithograph', 'dim' => '50H x 66W x 0D cm', 'year' => 1985, 'year_display' => '1405 hijri; 1984/1985', 'calendar' => 'hijri', 'edition_number' => '86/90', 'edition_size' => 90, 'signed' => true, 'holder' => 'talal_kurdi'],
        ['artist' => 'AR004', 'ref' => 'AR004_Nawawi_ARW002', 'title_ar' => 'القريه', 'title_en' => 'The Village', 'category' => 'painting', 'medium_ar' => 'ألوان زيتية', 'medium_en' => 'Oil', 'dim' => '74H x 94W cm', 'year' => 1989, 'signed' => true, 'holder' => 'moc'],
        ['artist' => 'AR004', 'ref' => 'AR004_Nawawi_ARW006', 'title_ar' => 'تكوين من الارقام العربية', 'title_en' => 'Composition of Arabic Numbers', 'category' => 'painting', 'medium_ar' => 'ألوان زيتية', 'medium_en' => 'Oil', 'dim' => '75H x 120W cm', 'year' => 1983, 'signed' => true, 'holder' => 'moc'],
        ['artist' => 'AR004', 'ref' => 'AR004_Nawawi_ARW0012', 'title_ar' => null, 'title_en' => null, 'category' => 'mixed_media', 'medium_ar' => 'أصباغ وجسو ورمل على قماش', 'medium_en' => 'Pigment, gesso, and sand on canvas', 'dim' => '121H x 91W cm', 'frame_dim' => '124H x 94W x 3D cm', 'year' => 1985, 'signed' => true, 'holder' => null],
        ['artist' => 'AR004', 'ref' => 'AR004_Nawawi_ARW013', 'title_ar' => null, 'title_en' => null, 'category' => 'painting', 'medium_ar' => 'زيت على قماش', 'medium_en' => 'Oil on canvas', 'dim' => '75H x 100W x 0D cm', 'frame_dim' => '97H x 122W x 4D cm', 'year' => 1981, 'signed' => true, 'holder' => 'altaybat'],
    ];

    /**
     * @var array<string, array{code: string|null, name_en: string|null}>
     */
    private const HOLDER_LOOKUP = [
        'barjeel' => ['code' => 'COL001', 'name_en' => null],
        'altaybat' => ['code' => 'MU001', 'name_en' => null],
        'talal_kurdi' => ['code' => 'COL003', 'name_en' => null],
        'moc' => ['code' => null, 'name_en' => 'MoC Collection'],
        'almansouria' => ['code' => null, 'name_en' => 'Almansouria Foundation for Culture and Creativity'],
        'radwi_estate' => ['code' => null, 'name_en' => 'Radwi Estate'],
    ];

    public function run(): void
    {
        foreach (self::ARTWORKS as $data) {
            $artist = Artist::where('legacy_code', $data['artist'])->first();

            if ($artist === null) {
                continue;
            }

            $isUntitled = $data['title_ar'] === null && $data['title_en'] === null;

            $parsed = DimensionParser::parse($data['dim']);
            $frameParsed = isset($data['frame_dim']) ? DimensionParser::parse($data['frame_dim']) : null;

            Artwork::withTrashed()->firstOrCreate(
                ['legacy_ref' => $data['ref']],
                [
                    'artist_id' => $artist->id,
                    'attribution_certainty' => AttributionCertainty::Confirmed->value,
                    'title_ar' => $data['title_ar'],
                    'title_en' => $data['title_en'],
                    'is_untitled' => $isUntitled,
                    'category' => $data['category'],
                    'medium_ar' => $data['medium_ar'],
                    'medium_en' => $data['medium_en'],
                    'edition_number' => $data['edition_number'] ?? null,
                    'edition_size' => $data['edition_size'] ?? null,
                    'height_cm' => $parsed->heightCm,
                    'width_cm' => $parsed->widthCm,
                    'depth_cm' => $parsed->depthCm,
                    'dimensions_raw' => $data['dim'],
                    'frame_height_cm' => $frameParsed?->heightCm,
                    'frame_width_cm' => $frameParsed?->widthCm,
                    'frame_depth_cm' => $frameParsed?->depthCm,
                    'frame_dimensions_raw' => $data['frame_dim'] ?? null,
                    'signed' => ($data['signed'] ?? null) === true ? SignedStatus::Signed->value : SignedStatus::Unknown->value,
                    'creation_date_display' => $data['year_display'] ?? (isset($data['year']) ? (string) $data['year'] : null),
                    'creation_year_from' => $data['year'] ?? null,
                    'creation_year_to' => $data['year'] ?? null,
                    'creation_calendar' => $data['calendar'] ?? 'gregorian',
                    'creation_certainty' => isset($data['year']) ? 'exact' : 'unknown',
                    'holder_id' => $this->resolveHolderId($data['holder'] ?? null),
                    'publication_status' => PublicationStatus::Published->value,
                ],
            );
        }
    }

    private function resolveHolderId(?string $key): ?int
    {
        if ($key === null || ! isset(self::HOLDER_LOOKUP[$key])) {
            return null;
        }

        $lookup = self::HOLDER_LOOKUP[$key];

        $holder = $lookup['code'] !== null
            ? Holder::where('legacy_code', $lookup['code'])->first()
            : Holder::where('name_en', $lookup['name_en'])->first();

        return $holder?->id;
    }
}
