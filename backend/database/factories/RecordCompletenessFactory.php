<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\RecordCompleteness;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RecordCompleteness> */
class RecordCompletenessFactory extends Factory
{
    protected $model = RecordCompleteness::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'citable_type' => Artist::class,
            'citable_id' => Artist::factory(),
            'completeness_pct' => 100,
            'blocking_gap_field_keys' => [],
            'minor_gap_field_keys' => [],
            'open_conflict_count' => 0,
            'severity' => 'clear',
            'computed_at' => now(),
        ];
    }
}
