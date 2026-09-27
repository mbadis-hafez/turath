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
            // A factory-made artist represents one that already exists, not
            // one just created through the reported creation-review flow
            // (005) — reviewed by default so every test that doesn't care
            // about that feature keeps working unchanged. unreviewed() below
            // is for tests that specifically want the pending state.
            'creation_approved_at' => now(),
        ];
    }

    /** A record still awaiting its creation-review approval (005). */
    public function unreviewed(): static
    {
        return $this->state(['creation_approved_at' => null]);
    }

    /**
     * Satisfies all 11 artist profile-completeness requirements (living
     * artist, so no death year or death-year citation needed) plus the
     * administrative statuses. Override per test when a gap is wanted.
     */
    public function complete(): static
    {
        return $this->state([
            'legacy_code' => 'AR'.fake()->unique()->numberBetween(100, 999),
            'birth_place_ar' => 'الأحساء',
            'birth_year_from' => 1939,
            'living_status' => 'living',
            'nationality_ar' => 'سعودي',
            'portrait_path' => 'portraits/test.jpg',
            'portrait_rights_status' => 'licensed',
            'authorization_letter_status' => 'signed',
            'owner_pre_agreement_status' => 'yes',
        ]);
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
