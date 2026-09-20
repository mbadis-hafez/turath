<?php

namespace Database\Factories;

use App\Models\ImportMappingProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ImportMappingProfile> */
class ImportMappingProfileFactory extends Factory
{
    protected $model = ImportMappingProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_type' => 'artist',
            'name' => fake()->words(3, true),
            'column_map' => [
                'Artist name AR' => 'name_ar',
                'Artist name EN' => 'name_en',
            ],
            'created_by_user_id' => User::factory(),
        ];
    }
}
