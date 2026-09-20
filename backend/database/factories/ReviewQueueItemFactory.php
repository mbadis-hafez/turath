<?php

namespace Database\Factories;

use App\Models\Artist;
use App\Models\ReviewQueueItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReviewQueueItem> */
class ReviewQueueItemFactory extends Factory
{
    protected $model = ReviewQueueItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'citable_type' => Artist::class,
            'citable_id' => Artist::factory(),
            'review_type' => 'archivist_review',
            'status' => 'pending',
            'submitted_at' => now(),
        ];
    }
}
