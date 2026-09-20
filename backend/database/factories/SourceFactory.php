<?php

namespace Database\Factories;

use App\Enums\SourceType;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Source> */
class SourceFactory extends Factory
{
    protected $model = Source::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_type' => SourceType::NewspaperArticle->value,
            'title_ar' => fake()->sentence(3),
            'title_en' => fake('en_US')->sentence(3),
            'publisher_or_outlet' => fake()->company(),
            'reference_note' => 'p. '.fake()->numberBetween(1, 40),
            'year' => fake()->numberBetween(1960, 2020),
            'added_by_user_id' => User::factory(),
        ];
    }
}
