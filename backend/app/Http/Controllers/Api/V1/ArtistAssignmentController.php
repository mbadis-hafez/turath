<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArtistAssignmentController
{
    public function __invoke(Request $request, Artist $artist): JsonResponse
    {
        $data = $request->validate([
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ]);

        $artist->update(['assigned_to_user_id' => $data['assigned_to_user_id'] ?? null]);
        $artist->load('assignedTo');

        return response()->json(['data' => self::present($artist)]);
    }

    /**
     * @return array{id: int, name: string, email: string}|null
     */
    public static function present(Artist $artist): ?array
    {
        return $artist->assignedTo ? [
            'id' => $artist->assignedTo->id,
            'name' => $artist->assignedTo->name,
            'email' => $artist->assignedTo->email,
        ] : null;
    }
}
