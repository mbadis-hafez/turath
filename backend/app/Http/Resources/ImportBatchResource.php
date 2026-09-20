<?php

namespace App\Http\Resources;

use App\Models\ImportBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ImportBatch $batch */
        $batch = $this->resource;

        return [
            'id' => $batch->id,
            'entity_type' => $batch->entity_type,
            'mapping_profile_id' => $batch->mapping_profile_id,
            'original_filename' => $batch->original_filename,
            'status' => $batch->status,
            'row_count' => $batch->row_count,
            'new_count' => $batch->new_count,
            'matched_count' => $batch->matched_count,
            'error_count' => $batch->error_count,
            'skipped_count' => $batch->skipped_count,
            'uploaded_by' => $batch->relationLoaded('uploadedBy') && $batch->uploadedBy
                ? ['id' => $batch->uploadedBy->id, 'name' => $batch->uploadedBy->name]
                : null,
            'validated_at' => $batch->validated_at?->toIso8601String(),
            'committed_at' => $batch->committed_at?->toIso8601String(),
            'created_at' => $batch->created_at?->toIso8601String(),
        ];
    }
}
