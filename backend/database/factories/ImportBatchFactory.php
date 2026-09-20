<?php

namespace Database\Factories;

use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ImportBatch> */
class ImportBatchFactory extends Factory
{
    protected $model = ImportBatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_type' => 'artist',
            'column_map' => ['name_en' => 'name_en'],
            'original_filename' => 'import.csv',
            'disk_path' => 'imports/'.fake()->uuid().'.csv',
            'status' => 'uploaded',
            'uploaded_by_user_id' => User::factory(),
        ];
    }
}
