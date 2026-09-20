<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserDashboardStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserDashboardStat> */
class UserDashboardStatFactory extends Factory
{
    protected $model = UserDashboardStat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'total_records' => 0,
            'avg_completeness_pct' => 100,
            'blocking_record_count' => 0,
            'conflict_count' => 0,
            'missing_field_count' => 0,
            'by_entity_type' => [],
            'computed_at' => now(),
        ];
    }
}
