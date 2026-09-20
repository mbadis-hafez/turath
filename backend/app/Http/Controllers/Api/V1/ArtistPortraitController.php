<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Portraits live on the private disk and are streamed through the API, so a
 * portrait is only public when its rights are clear and the artist is
 * published (nothing bypasses the rights model, per F3 D20).
 */
class ArtistPortraitController
{
    public const CLEAR_RIGHTS = ['licensed', 'public_domain'];

    public function show(Request $request, int $artist): StreamedResponse
    {
        $model = Artist::withTrashed()->findOrFail($artist);
        $manage = $request->user()?->can('artists.manage') ?? false;

        abort_if($model->portrait_path === null || ! Storage::disk('local')->exists($model->portrait_path), 404);
        abort_unless($manage || (! $model->trashed() && $model->publication_status === 'published' && self::isPublic($model)), 404);

        return Storage::disk('local')->response($model->portrait_path);
    }

    public function store(Request $request, Artist $artist): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'rights_status' => ['nullable', Rule::in(['unknown', 'licensed', 'public_domain', 'all_rights_reserved'])],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ]);

        if ($artist->portrait_path !== null) {
            Storage::disk('local')->delete($artist->portrait_path);
        }

        $artist->portrait_path = $request->file('image')->store('artist-portraits');
        $artist->portrait_rights_status = $data['rights_status'] ?? 'unknown';
        $artist->save();

        return response()->json(['data' => self::present($artist)], 201);
    }

    public function update(Request $request, Artist $artist): JsonResponse
    {
        $data = $request->validate([
            'rights_status' => ['required', Rule::in(['unknown', 'licensed', 'public_domain', 'all_rights_reserved'])],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ]);
        $artist->update(['portrait_rights_status' => $data['rights_status']]);

        return response()->json(['data' => self::present($artist)]);
    }

    public function destroy(Artist $artist): JsonResponse
    {
        if ($artist->portrait_path !== null) {
            Storage::disk('local')->delete($artist->portrait_path);
        }
        $artist->update(['portrait_path' => null, 'portrait_rights_status' => 'unknown']);

        return response()->json(['data' => self::present($artist)]);
    }

    public static function isPublic(Artist $artist): bool
    {
        return $artist->portrait_path !== null && in_array($artist->portrait_rights_status, self::CLEAR_RIGHTS, true);
    }

    /**
     * @return array{has_portrait: bool, rights_status: string, url: string|null}
     */
    public static function present(Artist $artist): array
    {
        return [
            'has_portrait' => $artist->portrait_path !== null,
            'rights_status' => $artist->portrait_rights_status,
            'url' => $artist->portrait_path !== null ? "/api/v1/artists/{$artist->id}/portrait" : null,
        ];
    }
}
