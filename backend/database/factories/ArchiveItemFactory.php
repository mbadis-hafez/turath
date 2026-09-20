<?php

namespace Database\Factories;

use App\Enums\AccessLevel;
use App\Enums\ArchiveItemType;
use App\Enums\PublicationStatus;
use App\Enums\RightsStatus;
use App\Models\ArchiveItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ArchiveItem> */
class ArchiveItemFactory extends Factory
{
    protected $model = ArchiveItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_type' => ArchiveItemType::Article->value,
            'title_ar' => fake()->sentence(3),
            'title_en' => fake('en_US')->sentence(3),
            'description_ar' => fake()->paragraph(),
            'description_en' => fake('en_US')->paragraph(),
            'access_level' => AccessLevel::InstitutionOnly->value,
            'rights_status' => RightsStatus::Unknown->value,
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

    public function publicAccess(): static
    {
        return $this->state([
            'access_level' => AccessLevel::Public->value,
            'rights_status' => RightsStatus::PublicDomain->value,
        ]);
    }

    public function embargoed(): static
    {
        return $this->state([
            'access_level' => AccessLevel::Embargoed->value,
            'embargo_until' => now()->addYear()->toDateString(),
        ]);
    }
}
