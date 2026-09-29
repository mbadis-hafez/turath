<?php

namespace Database\Seeders;

use App\Models\Artist;
use App\Models\Event;
use App\Support\Events\ArtistActivityEventSync;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fifty fictional artists with every field the artist create page submits:
 * identity, bio, nationality, classification, dates and places, curation
 * (identification, owner, rights statuses, supervisor note, contacts),
 * education and activity lines (which mirror exhibition/talk/symposium lines
 * into the events registry) and social links.
 *
 * Re-runnable: existing DUMMY- artists are replaced. Not part of
 * DatabaseSeeder on purpose — run explicitly:
 *   php artisan db:seed --class=DummyArtistsSeeder
 */
class DummyArtistsSeeder extends Seeder
{
    private const COUNT = 50;

    private const CITIES = [
        ['ar' => 'الرياض', 'en' => 'Riyadh'],
        ['ar' => 'جدة', 'en' => 'Jeddah'],
        ['ar' => 'مكة المكرمة', 'en' => 'Mecca'],
        ['ar' => 'الدمام', 'en' => 'Dammam'],
        ['ar' => 'الخبر', 'en' => 'Khobar'],
        ['ar' => 'أبها', 'en' => 'Abha'],
        ['ar' => 'المدينة المنورة', 'en' => 'Medina'],
    ];

    private const NATIONALITIES = [
        ['ar' => 'سعودي', 'en' => 'Saudi'],
        ['ar' => 'سعودي', 'en' => 'Saudi'],
        ['ar' => 'سعودي', 'en' => 'Saudi'],
        ['ar' => 'مصري', 'en' => 'Egyptian'],
        ['ar' => 'عراقي', 'en' => 'Iraqi'],
        ['ar' => 'سوري', 'en' => 'Syrian'],
    ];

    private const CLASSIFICATIONS = [
        ['ar' => 'رائد', 'en' => 'Pioneer'],
        ['ar' => 'فنان حداثي', 'en' => 'Modernist'],
        ['ar' => 'فنان معاصر', 'en' => 'Contemporary artist'],
        ['ar' => 'خطاط', 'en' => 'Calligrapher'],
        ['ar' => 'نحات', 'en' => 'Sculptor'],
    ];

    private const SCHOOLS = [
        ['ar' => 'كلية التربية الفنية', 'en' => 'College of Fine Education'],
        ['ar' => 'معهد الفنون الجميلة', 'en' => 'Institute of Fine Arts'],
        ['ar' => 'أكاديمية الفنون', 'en' => 'Academy of Arts'],
        ['ar' => 'جامعة الملك عبدالعزيز', 'en' => 'King Abdulaziz University'],
    ];

    private const DEGREES = [
        ['ar' => 'بكالوريوس فنون جميلة', 'en' => 'BFA'],
        ['ar' => 'ماجستير فنون', 'en' => 'MFA'],
        ['ar' => 'دبلوم فنون تطبيقية', 'en' => 'Applied Arts Diploma'],
    ];

    private const VENUES = [
        ['ar' => 'قاعة الفن المعاصر', 'en' => 'Contemporary Art Hall'],
        ['ar' => 'مركز جدة للفنون', 'en' => 'Jeddah Arts Center'],
        ['ar' => 'متحف الرياض', 'en' => 'Riyadh Museum'],
        ['ar' => 'معرض المربع', 'en' => 'Al-Murabba Gallery'],
        ['ar' => 'بيت الفن', 'en' => 'Beit Al-Fann'],
    ];

    private const ACTIVITY_TITLES = [
        'exhibition' => [
            ['ar' => 'معرض فردي', 'en' => 'Solo Exhibition'],
            ['ar' => 'معرض جماعي', 'en' => 'Group Exhibition'],
            ['ar' => 'ألوان الصحراء', 'en' => 'Colors of the Desert'],
            ['ar' => 'وجوه المدينة', 'en' => 'Faces of the City'],
        ],
        'talk' => [
            ['ar' => 'محاضرة عن الفن الحديث', 'en' => 'Lecture on Modern Art'],
            ['ar' => 'حديث مع الفنان', 'en' => 'Artist Talk'],
        ],
        'symposium' => [
            ['ar' => 'ندوة الفن السعودي المعاصر', 'en' => 'Saudi Contemporary Art Symposium'],
            ['ar' => 'ملتقى الرواد', 'en' => 'Pioneers Forum'],
        ],
        'award' => [
            ['ar' => 'جائزة التميز الفني', 'en' => 'Excellence in Art Award'],
            ['ar' => 'جائزة رائد التشكيل', 'en' => 'Pioneer of Fine Arts Award'],
        ],
    ];

    private const SOCIALS = ['instagram', 'x', 'facebook'];

    private const OWNER_TYPES = ['artist', 'heir_or_estate', 'gallery', 'institution', 'other'];

    private const RIGHTS_STATUSES = ['not_started', 'pending', 'signed', 'not_applicable'];

    public function run(): void
    {
        // Drop the previous generation first (entries and participants cascade or
        // dangle with the artists; mirrored events do not), then purge the draft
        // events that are left pointing at artists which no longer exist.
        Artist::withTrashed()->where('legacy_code', 'like', 'DUMMY-%')->forceDelete();

        $artistIds = Artist::withTrashed()->pluck('id');
        Event::where('publication_status', 'draft')
            ->whereHas('participants', fn ($q) => $q->where('participant_type', Artist::class)
                ->whereNotIn('participant_id', $artistIds))
            ->forceDelete();

        DB::transaction(function () {
            for ($i = 1; $i <= self::COUNT; $i++) {
                $this->seedArtist($i);
            }
        });

        $this->command?->info(sprintf('Seeded %d dummy artists (legacy_code DUMMY-001…%s).', self::COUNT, str_pad((string) self::COUNT, 3, '0', STR_PAD_LEFT)));
    }

    private function seedArtist(int $index): void
    {
        $en = fake('en_US');
        $living = $en->randomElement(['living', 'deceased', 'deceased', 'unknown']);
        $birthYear = $en->numberBetween(1915, 1985);
        $deathYear = $living === 'deceased' ? min($birthYear + $en->numberBetween(45, 88), 2024) : null;
        $birthCity = $en->randomElement(self::CITIES);
        $deathCity = $deathYear !== null ? $en->randomElement(self::CITIES) : null;

        $artist = Artist::factory()->create([
            'name_ar' => fake('ar_SA')->name(),
            'name_en' => $en->name(),
            'bio_ar' => fake('ar_SA')->paragraphs(3, true),
            'bio_en' => $en->paragraphs(3, true),
            'living_status' => $living,
            'legacy_code' => 'DUMMY-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            'nationality_ar' => ($n = $en->randomElement(self::NATIONALITIES))['ar'],
            'nationality_en' => $n['en'],
            'classification_ar' => ($c = $en->randomElement(self::CLASSIFICATIONS))['ar'],
            'classification_en' => $c['en'],
            'birth_date_display' => (string) $birthYear,
            'birth_year_from' => $birthYear,
            'birth_year_to' => $birthYear,
            'birth_place_ar' => $birthCity['ar'],
            'birth_place_en' => $birthCity['en'],
            'death_date_display' => $deathYear !== null ? (string) $deathYear : null,
            'death_year_from' => $deathYear,
            'death_year_to' => $deathYear,
            'death_place_ar' => $deathCity['ar'] ?? null,
            'death_place_en' => $deathCity['en'] ?? null,
            'verified_status' => $en->randomElement(['unverified', 'unverified', 'unverified', 'verified', 'disputed']),
            'publication_status' => $en->randomElement(['draft', 'draft', 'draft', 'draft', 'published', 'published', 'hidden']),
            'creation_approved_at' => now(),
            'identified_through_note' => $en->boolean(80) ? 'Introduced by '.$en->company().' archive' : null,
            'identified_through_date' => $en->boolean(60) ? $en->dateTimeBetween('-3 years', 'now')->format('Y-m-d') : null,
            'owner_type' => $en->randomElement(self::OWNER_TYPES),
            'authorization_letter_status' => $en->randomElement(self::RIGHTS_STATUSES),
            'owner_pre_agreement_status' => $en->randomElement(self::RIGHTS_STATUSES),
            'ref_supervisor_note' => $en->boolean(40) ? $en->sentence() : null,
        ]);

        $this->seedContacts($artist);
        $this->seedEntries($artist, $birthYear);
        $this->seedSocialLinks($artist);
    }

    private function seedContacts(Artist $artist): void
    {
        $en = fake('en_US');
        foreach (range(1, $en->numberBetween(0, 2)) as $i) {
            $artist->contacts()->create([
                'name' => fake('ar_SA')->name(),
                'email' => $en->boolean(70) ? $en->safeEmail() : null,
                'phone' => $en->boolean(50) ? '+9665'.$en->numerify('#######') : null,
                'sort' => $i,
            ]);
        }
    }

    private function seedEntries(Artist $artist, int $birthYear): void
    {
        $faker = fake();

        foreach (range(1, $faker->numberBetween(1, 2)) as $i) {
            $from = $birthYear + $faker->numberBetween(17, 30);
            $school = $faker->randomElement(self::SCHOOLS);
            $degree = $faker->randomElement(self::DEGREES);
            $artist->entries()->create([
                'type' => 'education',
                'title_ar' => $degree['ar'],
                'title_en' => $degree['en'],
                'place_ar' => $school['ar'],
                'place_en' => $school['en'],
                'year_from' => $from,
                'year_to' => $from + $faker->numberBetween(2, 5),
                'sort' => $i,
            ]);
        }

        $sort = 0;
        foreach (['award', 'exhibition', 'talk', 'symposium'] as $type) {
            $min = $type === 'exhibition' ? 1 : 0;
            $max = ['award' => 2, 'exhibition' => 3, 'talk' => 1, 'symposium' => 1][$type];
            foreach (range(1, $faker->numberBetween($min, $max)) as $i) {
                $title = $faker->randomElement(self::ACTIVITY_TITLES[$type]);
                $venue = $type === 'exhibition' ? $faker->randomElement(self::VENUES) : null;
                $artist->entries()->create([
                    'type' => $type,
                    'title_ar' => $title['ar'],
                    'title_en' => $title['en'],
                    'place_ar' => $venue['ar'] ?? null,
                    'place_en' => $venue['en'] ?? null,
                    'year_from' => min($birthYear + $faker->numberBetween(20, 70), 2025),
                    'sort' => ++$sort,
                ]);
            }
        }

        // Mirror exhibition/talk/symposium lines into the events registry, as the
        // entries endpoint does for saves from the create page.
        ArtistActivityEventSync::reconcile($artist, new EloquentCollection);
    }

    private function seedSocialLinks(Artist $artist): void
    {
        $en = fake('en_US');
        foreach ($en->randomElements(self::SOCIALS, $en->numberBetween(0, 2)) as $i => $platform) {
            $artist->socialLinks()->create([
                'platform' => $platform,
                'url' => match ($platform) {
                    'instagram' => 'https://instagram.com/'.$en->userName(),
                    'x' => 'https://x.com/'.$en->userName(),
                    default => 'https://facebook.com/'.$en->userName(),
                },
                'is_public' => $en->boolean(60),
                'sort' => $i,
            ]);
        }
    }
}
