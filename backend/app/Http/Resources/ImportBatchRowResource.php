<?php

namespace App\Http\Resources;

use App\Models\ImportBatchRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportBatchRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ImportBatchRow $row */
        $row = $this->resource;

        return [
            'id' => $row->id,
            'row_number' => $row->row_number,
            'match_status' => $row->match_status,
            'match_confidence' => $row->match_confidence,
            'matched_entity_id' => $row->matched_entity_id,
            'raw_data' => $row->raw_data,
            'mapped_data' => $row->mapped_data,
            'validation_errors' => $row->validation_errors ?? [],
            'resolution' => $row->resolution,
            'resolved_by_user_id' => $row->resolved_by_user_id,
            'resolved_at' => $row->resolved_at?->toIso8601String(),
            'commit_result' => $row->commit_result,
            'resulting_entity_id' => $row->resulting_entity_id,
        ];
    }
}
