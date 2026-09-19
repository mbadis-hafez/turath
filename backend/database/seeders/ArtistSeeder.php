<?php

namespace Database\Seeders;

use App\Enums\PublicationStatus;
use App\Enums\VerifiedStatus;
use App\Models\Artist;
use Illuminate\Database\Seeder;

class ArtistSeeder extends Seeder
{
    /**
     * Legacy fixture artists. Idempotent by legacy_code; the one artist
     * without a code is matched by both names. Only names/codes/variants are
     * seeded — everything else stays NULL per spec.
     *
     * @var array<int, array{code: string|null, ar: string, en: string, variants: array<int, array{name: string, type: string}>}>
     */
    private const ARTISTS = [
        ['code' => 'AR001', 'ar' => 'عبده ياسين', 'en' => 'Abdoh Yaseen', 'variants' => []],
        ['code' => 'AR003', 'ar' => 'عبدالله الشيخ', 'en' => 'Abdullah Alsheikh', 'variants' => [
            ['name' => 'Abdullah Alshaikh', 'type' => 'transliteration'],
        ]],
        ['code' => 'AR004', 'ar' => 'عبدالله نواوي', 'en' => 'Abdullah Nawawi', 'variants' => [
            ['name' => 'Abdullah Al-Nawawi', 'type' => 'transliteration'],
        ]],
        ['code' => 'AR008', 'ar' => 'عبدالعزيز الحماد', 'en' => 'Abdulaziz Alhammad', 'variants' => []],
        ['code' => 'AR012', 'ar' => 'عبدالحميد البقشي', 'en' => 'Abdulhameed Albaqshi', 'variants' => []],
        ['code' => 'AR013', 'ar' => 'عبدالحليم رضوي', 'en' => 'Abdulhalim Radwi', 'variants' => [
            ['name' => 'عبد الحليم رضوي', 'type' => 'alias'],
        ]],
        ['code' => 'AR014', 'ar' => 'عبدالجبار اليحيا', 'en' => 'Abduljabbar Alyahya', 'variants' => [
            ['name' => 'Abduljabbar Alyahia', 'type' => 'typo'],
        ]],
        ['code' => 'AR016', 'ar' => 'عبدالله المرزوق', 'en' => 'Abdullah Almarzoug', 'variants' => []],
        ['code' => 'AR018', 'ar' => 'عبدالله الشلتي', 'en' => 'Abdullah Alshalty', 'variants' => [
            ['name' => 'Abdullah Alshalti', 'type' => 'transliteration'],
        ]],
        ['code' => 'AR026', 'ar' => 'عبدالرحمن السليمان', 'en' => 'Abdulrahman Alsoliman', 'variants' => [
            ['name' => 'Abdulrahman Al-Sulaiman', 'type' => 'transliteration'],
            ['name' => 'عبد الرحمن السليمان', 'type' => 'alias'],
        ]],
        ['code' => 'AR036', 'ar' => 'أحمد المغلوث', 'en' => 'Ahmad Almaghlout', 'variants' => [
            ['name' => 'Ahmad Almaghlouth', 'type' => 'transliteration'],
            ['name' => 'احمد المغلوث', 'type' => 'alias'],
        ]],
        ['code' => 'AR048', 'ar' => 'بكر شيخون', 'en' => 'Baker Sheikhoun', 'variants' => [
            ['name' => 'Bakr Shaikhon', 'type' => 'transliteration'],
            ['name' => 'Bakr Sheikhoun', 'type' => 'transliteration'],
        ]],
        ['code' => 'AR049', 'ar' => 'ضياء عزيز ضياء', 'en' => 'Dia Aziz Dia', 'variants' => [
            ['name' => 'Diaa Aziz Diaa', 'type' => 'transliteration'],
        ]],
        ['code' => 'AR060', 'ar' => 'فؤاد مغربل', 'en' => 'Fouad Mougharbel', 'variants' => [
            ['name' => 'فؤاذ مفربل', 'type' => 'typo'],
        ]],
        ['code' => 'AR073', 'ar' => 'إبراهيم بوقس', 'en' => 'Ibrahim Bugis', 'variants' => [
            ['name' => 'Ibrahim Bougis', 'type' => 'transliteration'],
            ['name' => 'إبراهيم بوغس', 'type' => 'alias'],
        ]],
        ['code' => 'AR089', 'ar' => 'محمد الصندل', 'en' => 'Mohammed Alsandal', 'variants' => [
            ['name' => 'Mohamed Al-Sandal', 'type' => 'transliteration'],
            ['name' => 'محمد الصتدل', 'type' => 'typo'],
        ]],
        ['code' => 'AR112', 'ar' => 'ناصر الموسى', 'en' => 'Nasser Al Mousa', 'variants' => []],
        ['code' => 'AR144', 'ar' => 'يوسف جاها', 'en' => 'Yousef Jaha', 'variants' => []],
        ['code' => null, 'ar' => 'صفية بن زقر', 'en' => 'Safeya Binzagr', 'variants' => [
            ['name' => 'Safia Binzagr', 'type' => 'transliteration'],
        ]],
    ];

    public function run(): void
    {
        foreach (self::ARTISTS as $data) {
            $artist = $this->findOrCreateArtist($data);

            foreach ($data['variants'] as $variant) {
                $artist->variants()->firstOrCreate(
                    ['name' => $variant['name']],
                    [
                        'language' => $this->detectLanguage($variant['name']),
                        'type' => $variant['type'],
                    ],
                );
            }
        }
    }

    /**
     * @param  array{code: string|null, ar: string, en: string, variants: array}  $data
     */
    private function findOrCreateArtist(array $data): Artist
    {
        $attributes = [
            'name_ar' => $data['ar'],
            'name_en' => $data['en'],
            'publication_status' => PublicationStatus::Published->value,
            'verified_status' => VerifiedStatus::Unverified->value,
        ];

        if ($data['code'] !== null) {
            return Artist::withTrashed()->firstOrCreate(['legacy_code' => $data['code']], $attributes);
        }

        return Artist::withTrashed()->firstOrCreate(
            ['name_ar' => $data['ar'], 'name_en' => $data['en']],
            $attributes,
        );
    }

    private function detectLanguage(string $name): string
    {
        return preg_match('/\p{Arabic}/u', $name) ? 'ar' : 'en';
    }
}
