<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\SourceConflict;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SourceConflict> */
class SourceConflictFactory extends Factory
{
    protected $model = SourceConflict::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'citable_type' => Artist::class,
            'citable_id' => Artist::factory(),
            'field_key' => 'death_year',
            'status' => 'open',
            'citation_ids' => [],
        ];
    }
}
