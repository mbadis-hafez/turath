<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Completeness\StoreContentPermissionRequestRequest;
use App\Models\ContentPermissionRequest;
use App\Support\Completeness\CitableTypeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** A Reviewer's request to edit or delete a specific published record, pending an Admin's decision. */
class ContentPermissionRequestStoreController
{
    public function __invoke(StoreContentPermissionRequestRequest $request, string $type, int $id): JsonResponse
    {
        $entry = CitableTypeResolver::forSegment($type);
        abort_if($entry === null, 404);

        $record = $entry['model']::query()->find($id);
        abort_if($record === null, 404);

        $requestType = $request->string('request_type')->value();
        $ability = $requestType === 'delete' ? 'requestDelete' : 'requestEdit';
        Gate::forUser($request->user())->authorize($ability, $record);

        $exists = ContentPermissionRequest::query()
            ->where('citable_type', $record::class)
            ->where('citable_id', $record->getKey())
            ->where('requested_by_user_id', $request->user()->id)
            ->where('request_type', $requestType)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['request_type' => ['A request of this type is already pending for this record.']]);
        }

        $permissionRequest = ContentPermissionRequest::create([
            'citable_type' => $record::class,
            'citable_id' => $record->getKey(),
            'request_type' => $requestType,
            'requested_by_user_id' => $request->user()->id,
            'reason' => $request->string('reason')->value(),
            'status' => 'pending',
        ]);

        return response()->json(['data' => ContentPermissionRequestIndexController::present($permissionRequest)], 201);
    }
}
