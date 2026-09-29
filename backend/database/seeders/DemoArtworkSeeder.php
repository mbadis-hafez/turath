<?php

namespace Database\Seeders;

use App\Models\Artist;
use App\Models\Artwork;
use App\Models\ArtworkImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo dataset for local development: 50 artists (with generated portrait
 * images) and 300 artworks (6 per artist, with 1–3 generated photographs
 * each). Idempotent via legacy_code / legacy_ref keys, local-only, same
 * spirit as DemoArtistSeeder. Run with:
 *
 *     php artisan db:seed --class=DemoArtworkSeeder
 */
class DemoArtworkSeeder extends Seeder
{
    private const ARTIST_COUNT = 50;

    private const ARTWORKS_PER_ARTIST = 6;

    /** @var array<int, string> */
    private const AR_FIRST = [
        'أحمد', 'محمد', 'خالد', 'عبدالله', 'سارة', 'نورة', 'فاطمة', 'ليلى',
        'يوسف', 'عمر', 'هند', 'ريم', 'سلطان', 'ماجد', 'وجدان', 'العنود',
        'بدر', 'تركي', 'منيرة', 'جمانة',
    ];

    /** @var array<int, string> */
    private const AR_LAST = [
        'الحربي', 'العتيبي', 'القحطاني', 'الدوسري', 'الشهري', 'الغامدي',
        'المطيري', 'الزهراني', 'العنزي', 'المالكي', 'السبيعي', 'الخالدي',
        'الرشيدي', 'العمري', 'البلوي', 'الجهني',
    ];

    /** @var array<int, string> */
    private const EN_FIRST = [
        'Ahmed', 'Mohammed', 'Khaled', 'Abdullah', 'Sara', 'Noura', 'Fatima',
        'Layla', 'Yousef', 'Omar', 'Hind', 'Reem', 'Sultan', 'Majed', 'Wijdan',
        'Alanoud', 'Badr', 'Turki', 'Munira', 'Jumana',
    ];

    /** @var array<int, string> */
    private const EN_LAST = [
        'Alharbi', 'Alotaibi', 'Alqahtani', 'Aldossari', 'Alshehri', 'Alghamdi',
        'Almutairi', 'Alzahrani', 'Alanazi', 'Almalki', 'Alsubaie', 'Alkhaldi',
        'Alrashidi', 'Alomari', 'Albalawi', 'Aljehani',
    ];

    /** @var array<int, array{en: string, ar: string}> */
    private const NATIONALITIES = [
        ['en' => 'Saudi', 'ar' => 'سعودية'],
        ['en' => 'Saudi', 'ar' => 'سعودية'],
        ['en' => 'Saudi', 'ar' => 'سعودية'],
        ['en' => 'Egyptian', 'ar' => 'مصرية'],
        ['en' => 'Iraqi', 'ar' => 'عراقية'],
        ['en' => 'Palestinian', 'ar' => 'فلسطينية'],
        ['en' => 'Syrian', 'ar' => 'سورية'],
        ['en' => 'Kuwaiti', 'ar' => 'كويتية'],
    ];

    /**
     * Artwork title pools per category. Pairs are index-aligned.
     *
     * @var array<string, array{en: array<int, string>, ar: array<int, string>}>
     */
    private const TITLES = [
        'painting' => [
            'en' => ['Desert Echo', 'Coastal Light', 'Palm Grove', 'Urban Rhythm', 'Silent Dunes', 'Market Day', 'Golden Hour', 'Old Jeddah', 'Caravan', 'Mirage'],
            'ar' => ['صدى الصحراء', 'ضوء الساحل', 'بساتين النخيل', 'إيقاع المدينة', 'كثبان صامتة', 'يوم السوق', 'الساعة الذهبية', 'جدة القديمة', 'القافلة', 'سراب'],
        ],
        'drawing' => [
            'en' => ['Study I', 'Study II', 'Sketchbook Page', 'Line Study', 'Figure Drawing', 'Gesture'],
            'ar' => ['دراسة ١', 'دراسة ٢', 'صفحة دفتر', 'دراسة خطية', 'رسم شخصي', 'إيماءة'],
        ],
        'printmaking' => [
            'en' => ['Etching No. 3', 'Lithograph Series A', 'Woodcut Variation', 'Screen Print 7', 'Edition Study'],
            'ar' => ['نقش رقم ٣', 'سلسلة ليثوغراف أ', 'نسخة خشبية', 'طباعة شاشة ٧', 'دراسة إصدار'],
        ],
        'sculpture' => [
            'en' => ['Standing Form', 'Torso', 'Spiral', 'Column Study', 'Earth Figure'],
            'ar' => ['شكل واقف', 'جذع', 'لولب', 'دراسة عمود', 'شكل ترابي'],
        ],
        'mixed_media' => [
            'en' => ['Layered Memory', 'Fragmented Calligraphy', 'Assemblage 2', 'Torn Letters'],
            'ar' => ['ذاكرة متراكبة', 'خط مبعثر', 'تجميعة ٢', 'رسائل ممزقة'],
        ],
        'paper_work' => [
            'en' => ['Watercolor Study', 'Ink Wash', 'Paper Collage', 'Miniature'],
            'ar' => ['دراسة مائية', 'غسيل حبر', 'كولاج ورقي', 'مصغرة'],
        ],
        'photography' => [
            'en' => ['Alleyway', 'Nomad Portrait', 'City Grid', 'Dust Storm'],
            'ar' => ['زقاق', 'صورة بدوي', 'شبكة المدينة', 'عاصفة غبار'],
        ],
        'installation' => [
            'en' => ['Threshold', 'Suspended Words', 'Room of Sands'],
            'ar' => ['عتبة', 'كلمات معلقة', 'غرفة الرمال'],
        ],
        'other' => [
            'en' => ['Untitled Experiment', 'Found Object', 'Box Construction'],
            'ar' => ['تجربة بلا عنوان', 'شكل وجدي', 'بناء صندوقي'],
        ],
    ];

    /** @var array<string, array{en: string, ar: string}> */
    private const MEDIUMS = [
        'painting' => ['en' => 'Oil on canvas', 'ar' => 'زيت على قماش'],
        'drawing' => ['en' => 'Graphite on paper', 'ar' => 'رصاص على ورق'],
        'printmaking' => ['en' => 'Lithograph on paper', 'ar' => 'ليثوغراف على ورق'],
        'sculpture' => ['en' => 'Bronze', 'ar' => 'برونز'],
        'mixed_media' => ['en' => 'Mixed media on canvas', 'ar' => 'خامات مختلفة على قماش'],
        'paper_work' => ['en' => 'Watercolor on paper', 'ar' => 'ألوان مائية على ورق'],
        'photography' => ['en' => 'Archival pigment print', 'ar' => 'طباعة أرشيفية'],
        'installation' => ['en' => 'Mixed materials', 'ar' => 'خامات متنوعة'],
        'other' => ['en' => 'Mixed materials', 'ar' => 'خامات متنوعة'],
    ];

    /** @var array<int, string> */
    private const CATEGORIES = [
        'painting', 'painting', 'painting', 'painting', 'painting', 'painting',
        'drawing', 'drawing', 'printmaking', 'sculpture', 'mixed_media',
        'paper_work', 'photography', 'installation', 'other',
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('DemoArtworkSeeder only runs in the local environment.');

            return;
        }

        $faker = fake('en_US');

        $artists = [];

        foreach (range(1, self::ARTIST_COUNT) as $i) {
            $number = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $deceased = $faker->boolean(35);
            $birthYear = $faker->numberBetween(1930, 1990);
            $deathYear = $deceased ? $faker->numberBetween($birthYear + 40, min($birthYear + 85, 2024)) : null;

            $nationality = $faker->randomElement(self::NATIONALITIES);
            $firstIndex = ($i - 1) % count(self::EN_FIRST);
            $lastIndex = intdiv($i - 1, count(self::EN_FIRST)) % count(self::EN_LAST);

            $state = match ($i % 5) {
                0 => 'draft',
                1 => ['published', 'verified'],
                2 => 'hidden',
                default => 'published',
            };

            $existing = Artist::where('legacy_code', "DEMO-{$number}")->first();
            $portraitPath = $existing?->portrait_path ?? $this->generateImage('demo-artists/portraits', "DEMO-{$number}", 600, 750);

            $artist = Artist::updateOrCreate(
                ['legacy_code' => "DEMO-{$number}"],
                [
                    'name_en' => self::EN_FIRST[$firstIndex].' '.self::EN_LAST[$lastIndex],
                    'name_ar' => self::AR_FIRST[$firstIndex].' '.self::AR_LAST[$lastIndex],
                    'bio_en' => $faker->paragraphs(3, true),
                    'bio_ar' => $faker->paragraphs(3, true),
                    'birth_year_from' => $birthYear,
                    'birth_year_to' => $birthYear,
                    'birth_certainty' => 'exact',
                    'birth_calendar' => 'gregorian',
                    'death_year_from' => $deathYear,
                    'death_year_to' => $deathYear,
                    'death_certainty' => $deathYear !== null ? 'exact' : 'unknown',
                    'death_calendar' => $deathYear !== null ? 'gregorian' : 'unknown',
                    'living_status' => $deceased ? 'deceased' : 'living',
                    'nationality_en' => $nationality['en'],
                    'nationality_ar' => $nationality['ar'],
                    'portrait_path' => $portraitPath,
                    'portrait_rights_status' => 'licensed',
                    'publication_status' => is_array($state) ? $state[0] : $state,
                    'verified_status' => is_array($state) ? $state[1] : 'unverified',
                    'creation_approved_at' => now(),
                ],
            );

            $artists[] = $artist;
        }

        $artworkCount = 0;

        foreach ($artists as $artistIndex => $artist) {
            foreach (range(1, self::ARTWORKS_PER_ARTIST) as $n) {
                $ref = sprintf('DEMO-ART-%03d-%02d', $artistIndex + 1, $n);
                $category = $faker->randomElement(self::CATEGORIES);
                $untitled = $faker->boolean(10);
                $titleIndex = $faker->numberBetween(0, count(self::TITLES[$category]['en']) - 1);
                $year = $faker->numberBetween(1965, 2024);
                $height = $faker->randomFloat(1, 20, 200);
                $width = $faker->randomFloat(1, 20, 200);
                $hasDepth = $category === 'sculpture' || $faker->boolean(20);
                $depth = $hasDepth ? $faker->randomFloat(1, 2, 60) : null;
                $dimensionsRaw = $depth !== null
                    ? sprintf('%.1fH x %.1fW x %.1fD cm', $height, $width, $depth)
                    : sprintf('%.1fH x %.1fW cm', $height, $width);

                $published = $faker->boolean(85);

                $artwork = Artwork::updateOrCreate(
                    ['legacy_ref' => $ref],
                    [
                        'artist_id' => $artist->id,
                        'attribution_certainty' => 'confirmed',
                        'title_en' => $untitled ? null : self::TITLES[$category]['en'][$titleIndex],
                        'title_ar' => $untitled ? null : self::TITLES[$category]['ar'][$titleIndex],
                        'is_untitled' => $untitled,
                        'category' => $category,
                        'medium_en' => self::MEDIUMS[$category]['en'],
                        'medium_ar' => self::MEDIUMS[$category]['ar'],
                        'height_cm' => $height,
                        'width_cm' => $width,
                        'depth_cm' => $depth,
                        'dimensions_raw' => $dimensionsRaw,
                        'signed' => $faker->boolean(70) ? 'signed' : ($faker->boolean(50) ? 'unsigned' : 'unknown'),
                        'creation_date_display' => (string) $year,
                        'creation_year_from' => $year,
                        'creation_year_to' => $year,
                        'creation_calendar' => 'gregorian',
                        'creation_certainty' => 'exact',
                        'publication_status' => $published ? 'published' : 'draft',
                        'published_at' => $published ? now() : null,
                        'creation_approved_at' => now(),
                    ],
                );

                $imageCount = $artwork->images()->count() > 0 ? 0 : $faker->numberBetween(1, 3);

                for ($img = 1; $img <= $imageCount; $img++) {
                    [$path, $widthPx, $heightPx, $bytes] = $this->generateImageWithMeta(
                        'demo-artworks/images',
                        $ref.'-'.$img,
                        $faker->numberBetween(800, 1200),
                        $faker->numberBetween(600, 1500),
                    );

                    ArtworkImage::create([
                        'artwork_id' => $artwork->id,
                        'path' => $path,
                        'original_filename' => $ref.'-'.$img.'.jpg',
                        'mime_type' => 'image/jpeg',
                        'size_bytes' => $bytes,
                        'sha256' => hash('sha256', Storage::disk('local')->get($path)),
                        'width_px' => $widthPx,
                        'height_px' => $heightPx,
                        'rights_status' => $faker->randomElement(['licensed', 'public_domain', 'licensed']),
                        'is_final' => $img === 1,
                    ]);
                }

                $artworkCount++;
            }
        }

        $this->command?->info(sprintf(
            'Seeded %d demo artists and %d demo artworks.',
            count($artists),
            $artworkCount,
        ));
    }

    /**
     * Generates a simple abstract placeholder image with GD and stores it on
     * the local disk. Returns the storage-relative path.
     */
    private function generateImage(string $dir, string $name, int $width, int $height): string
    {
        [$path] = $this->generateImageWithMeta($dir, $name, $width, $height);

        return $path;
    }

    /**
     * @return array{0: string, 1: int, 2: int, 3: int} path, width, height, size in bytes
     */
    private function generateImageWithMeta(string $dir, string $name, int $width, int $height): array
    {
        $image = imagecreatetruecolor($width, $height);

        $base = imagecolorallocate($image, random_int(120, 230), random_int(120, 230), random_int(120, 230));
        imagefill($image, 0, 0, $base);

        for ($i = 0; $i < 8; $i++) {
            $color = imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));

            if (random_int(0, 1) === 1) {
                imagefilledrectangle($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $color);
            } else {
                imagefilledellipse($image, random_int(0, $width), random_int(0, $height), random_int(20, (int) ($width / 2)), random_int(20, (int) ($height / 2)), $color);
            }
        }

        ob_start();
        imagejpeg($image, null, 80);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        $path = "{$dir}/{$name}.jpg";
        Storage::disk('local')->put($path, $contents);

        return [$path, $width, $height, strlen($contents)];
    }
}
