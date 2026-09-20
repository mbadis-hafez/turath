<?php

namespace Database\Factories;

use App\Enums\ArchiveLinkRole;
use App\Models\ArchiveItem;
use App\Models\ArchiveItemLink;
use App\Models\Artist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ArchiveItemLink> */
class ArchiveItemLinkFactory extends Factory
{
    protected $model = ArchiveItemLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'archive_item_id' => ArchiveItem::factory(),
            'linkable_type' => Artist::class,
            'linkable_id' => Artist::factory(),
            'role' => ArchiveLinkRole::Subject->value,
        ];
    }
}
