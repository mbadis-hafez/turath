<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\FieldCitation;
use App\Models\Source;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FieldCitation> */
class FieldCitationFactory extends Factory
{
    protected $model = FieldCitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'citable_type' => Artist::class,
            'citable_id' => Artist::factory(),
            'field_key' => 'death_year',
            'source_id' => Source::factory(),
            'claimed_value' => ['year' => 1939],
            'created_by_user_id' => User::factory(),
        ];
    }
}
