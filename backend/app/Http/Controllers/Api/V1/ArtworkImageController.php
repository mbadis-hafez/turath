<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artwork;
use App\Models\ArtworkImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Images live on the private disk and are streamed through the API, so an
 * image is only public when its rights are clear and the artwork is
 * published (nothing bypasses the rights model, per F3 D20).
 */
class ArtworkImageController
{
    public function show(Request $request, int $artwork, int $image): StreamedResponse
    {
        $model = Artwork::withTrashed()->findOrFail($artwork);
        $file = ArtworkImage::where('artwork_id', $model->id)->findOrFail($image);
        $manage = $request->user()?->can('artworks.manage') ?? false;

        abort_unless(Storage::disk('local')->exists($file->path), 404);
        abort_unless($manage || (! $model->trashed() && $model->publication_status === 'published' && $file->isClearForPublic()), 404);

        return Storage::disk('local')->response($file->path);
    }

    public function store(Request $request, Artwork $artwork): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'rights_status' => ['nullable', Rule::in(ArtworkImage::RIGHTS)],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ]);

        $upload = $request->file('image');
        $size = @getimagesize($upload->getRealPath()) ?: [null, null];

        $artwork->images()->create([
            'path' => $upload->store('artwork-images'),
            'original_filename' => $upload->getClientOriginalName(),
            'mime_type' => $upload->getMimeType() ?? 'image/jpeg',
            'size_bytes' => $upload->getSize(),
            'sha256' => hash_file('sha256', $upload->getRealPath()),
            'width_px' => $size[0],
            'height_px' => $size[1],
            'rights_status' => $data['rights_status'] ?? 'unknown',
            'uploaded_by_user_id' => $request->user()?->id,
        ]);

        return response()->json(['data' => self::present($artwork->refresh())], 201);
    }

    public function update(Request $request, Artwork $artwork, int $image): JsonResponse
    {
        $data = $request->validate([
            'rights_status' => ['sometimes', Rule::in(ArtworkImage::RIGHTS)],
            'is_final' => ['sometimes', 'boolean'],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ]);
        $file = $artwork->images()->findOrFail($image);

        DB::transaction(function () use ($artwork, $file, $data) {
            if (($data['is_final'] ?? false) === true) {
                $artwork->images()->where('id', '!=', $file->id)->where('is_final', true)->get()->each->update(['is_final' => false]);
            }
            $file->update(array_intersect_key($data, array_flip(['rights_status', 'is_final'])));
        });

        return response()->json(['data' => self::present($artwork->refresh())]);
    }

    public function destroy(Artwork $artwork, int $image): JsonResponse
    {
        $file = $artwork->images()->findOrFail($image);
        Storage::disk('local')->delete($file->path);
        $file->delete();

        return response()->json(['data' => self::present($artwork->refresh())]);
    }

    /** The final selected image, else the first upload. */
    public static function primary(Artwork $artwork): ?ArtworkImage
    {
        $images = $artwork->images;

        return $images->firstWhere('is_final', true) ?? $images->first();
    }

    public static function urlFor(ArtworkImage $image): string
    {
        return "/api/v1/artworks/{$image->artwork_id}/images/{$image->id}/file";
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function present(Artwork $artwork): array
    {
        return $artwork->images()->get()->map(fn (ArtworkImage $i) => [
            'id' => $i->id,
            'url' => self::urlFor($i),
            'filename' => $i->original_filename,
            'width_px' => $i->width_px,
            'height_px' => $i->height_px,
            'size_bytes' => $i->size_bytes,
            'rights_status' => $i->rights_status,
            'is_final' => $i->is_final,
        ])->values()->all();
    }
}
