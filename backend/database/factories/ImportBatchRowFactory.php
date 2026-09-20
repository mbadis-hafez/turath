<?php

namespace Database\Factories;

use App\Models\ImportBatch;
use App\Models\ImportBatchRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ImportBatchRow> */
class ImportBatchRowFactory extends Factory
{
    protected $model = ImportBatchRow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_batch_id' => ImportBatch::factory(),
            'row_number' => fake()->unique()->numberBetween(1, 100000),
            'raw_data' => ['name_en' => fake('en_US')->name()],
            'mapped_data' => ['name_en' => fake('en_US')->name()],
            'match_status' => 'new',
            'resolution' => 'pending',
        ];
    }
}
