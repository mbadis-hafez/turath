<?php

namespace Database\Factories;

use App\Enums\FileRole;
use App\Models\ArchiveItem;
use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<File> */
class FileFactory extends Factory
{
    protected $model = File::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'archive_item_id' => ArchiveItem::factory(),
            'role' => FileRole::Original->value,
            'disk' => 'local',
            'path' => 'archive/'.fake()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(1000, 5_000_000),
            'sha256' => hash('sha256', fake()->uuid()),
        ];
    }

    public function thumbnail(): static
    {
        return $this->state(['role' => FileRole::Thumbnail->value]);
    }
}
