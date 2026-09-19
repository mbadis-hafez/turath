<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Enums\VerifiedStatus;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Artist> */
class ArtistFactory extends Factory
{
    protected $model = Artist::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => fake()->name(),
            'name_en' => fake('en_US')->name(),
            'bio_ar' => fake()->paragraphs(2, true),
            'bio_en' => fake('en_US')->paragraphs(2, true),
            'living_status' => 'unknown',
            'verified_status' => VerifiedStatus::Unverified->value,
            'publication_status' => PublicationStatus::Draft->value,
        ];
    }

    public function published(): static
    {
        return $this->state(['publication_status' => PublicationStatus::Published->value]);
    }

    public function draft(): static
    {
        return $this->state(['publication_status' => PublicationStatus::Draft->value]);
    }

    public function verified(): static
    {
        return $this->state(['verified_status' => VerifiedStatus::Verified->value]);
    }

    public function arabicOnly(): static
    {
        return $this->state(['name_en' => null]);
    }

    public function englishOnly(): static
    {
        return $this->state(['name_ar' => null]);
    }
}
