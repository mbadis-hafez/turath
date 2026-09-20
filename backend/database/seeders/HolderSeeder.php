<?php

namespace Database\Seeders;

use App\Enums\HolderType;
use App\Models\Holder;
use Illuminate\Database\Seeder;

class HolderSeeder extends Seeder
{
    /**
     * Real holders from the source project. Institutions are public by
     * default; the one private collector and the family estate are flagged
     * is_public_name = false so their real name never reaches the public API.
     *
     * City names are translated (standard place names, not invented facts);
     * holder names/types/codes are exactly as recorded in the source project.
     *
     * @var array<int, array{code: string|null, type: string, name_ar: string|null, name_en: string, city_ar: string, city_en: string, is_public_name?: bool, is_estate?: bool}>
     */
    private const HOLDERS = [
        ['code' => null, 'type' => 'institution', 'name_ar' => 'مقتنيات وزارة الثقافة', 'name_en' => 'MoC Collection', 'city_ar' => 'الرياض', 'city_en' => 'Riyadh'],
        ['code' => 'MU001', 'type' => 'institution', 'name_ar' => null, 'name_en' => 'Altaybat Museum (Abduraouf Khalil)', 'city_ar' => 'جدة', 'city_en' => 'Jeddah'],
        ['code' => 'MU002', 'type' => 'institution', 'name_ar' => null, 'name_en' => 'Darat Safeya Binzagr', 'city_ar' => 'جدة', 'city_en' => 'Jeddah'],
        ['code' => 'COL001', 'type' => 'institution', 'name_ar' => null, 'name_en' => 'Barjeel Art Foundation', 'city_ar' => 'الشارقة', 'city_en' => 'Sharjah'],
        ['code' => null, 'type' => 'institution', 'name_ar' => null, 'name_en' => 'Mathaf: Arab Museum of Modern Art', 'city_ar' => 'الدوحة', 'city_en' => 'Doha'],
        ['code' => null, 'type' => 'institution', 'name_ar' => null, 'name_en' => 'Almansouria Foundation for Culture and Creativity', 'city_ar' => 'جدة', 'city_en' => 'Jeddah'],
        ['code' => 'COL003', 'type' => 'private_collector', 'name_ar' => null, 'name_en' => 'Talal Kurdi Collection', 'city_ar' => 'جدة', 'city_en' => 'Jeddah', 'is_public_name' => false],
        ['code' => null, 'type' => 'family', 'name_ar' => null, 'name_en' => 'Radwi Estate', 'city_ar' => 'جدة', 'city_en' => 'Jeddah', 'is_public_name' => false, 'is_estate' => true],
    ];

    public function run(): void
    {
        foreach (self::HOLDERS as $data) {
            $attributes = [
                'type' => $data['type'],
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'],
                'city_ar' => $data['city_ar'],
                'city_en' => $data['city_en'],
                'is_public_name' => $data['is_public_name'] ?? in_array($data['type'], [HolderType::Institution->value, HolderType::ArtistEstate->value], true),
                'is_estate' => $data['is_estate'] ?? null,
            ];

            if ($data['code'] !== null) {
                Holder::withTrashed()->firstOrCreate(['legacy_code' => $data['code']], $attributes);

                continue;
            }

            Holder::withTrashed()->firstOrCreate(['name_en' => $data['name_en']], $attributes);
        }
    }
}
