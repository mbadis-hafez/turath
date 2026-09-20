<?php

namespace Database\Factories;

use App\Enums\ArtworkCategory;
use App\Enums\AttributionCertainty;
use App\Enums\PublicationStatus;
use App\Enums\SignedStatus;
use App\Models\Artist;
use App\Models\Artwork;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Artwork> */
class ArtworkFactory extends Factory
{
    protected $model = Artwork::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'artist_id' => Artist::factory(),
            'attribution_certainty' => AttributionCertainty::Confirmed->value,
            'title_ar' => fake()->words(3, true),
            'title_en' => fake('en_US')->words(3, true),
            'is_untitled' => false,
            'category' => ArtworkCategory::Painting->value,
            'medium_ar' => 'زيت على قماش',
            'medium_en' => 'Oil on canvas',
            'signed' => SignedStatus::Unknown->value,
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

    public function untitled(): static
    {
        return $this->state(['is_untitled' => true, 'title_ar' => null, 'title_en' => null]);
    }

    public function unattributed(): static
    {
        return $this->state(['artist_id' => null, 'attribution_certainty' => AttributionCertainty::Unattributed->value]);
    }
}
