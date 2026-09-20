<?php

namespace Database\Factories;

use App\Enums\HolderType;
use App\Models\Holder;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Holder> */
class HolderFactory extends Factory
{
    protected $model = Holder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => HolderType::Institution->value,
            'name_ar' => fake()->company(),
            'name_en' => fake('en_US')->company(),
            'city_ar' => 'جدة',
            'city_en' => 'Jeddah',
            'is_public_name' => true,
        ];
    }

    public function privateCollector(): static
    {
        return $this->state([
            'type' => HolderType::PrivateCollector->value,
            'is_public_name' => false,
        ]);
    }

    public function familyEstate(): static
    {
        return $this->state([
            'type' => HolderType::Family->value,
            'is_estate' => true,
            'is_public_name' => false,
        ]);
    }
}
