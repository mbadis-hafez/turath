<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artwork\StoreArtworkImagesRequest;
use App\Models\Artwork;
use App\Models\ArtworkImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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

    /**
     * Accepts the legacy single `image` field or the new `images[]` batch
     * field (never both — StoreArtworkImagesRequest enforces that). Each
     * file is validated and checksummed independently, so one bad or
     * duplicate file never discards its valid siblings (FR-004); every file
     * gets a per-file outcome in `results` alongside the always-present
     * `data` list, whose shape is unchanged for existing callers.
     */
    public function store(StoreArtworkImagesRequest $request, Artwork $artwork): JsonResponse
    {
        $rights = $request->validated('rights_status') ?? 'unknown';
        $results = [];

        foreach ($request->uploadedFiles() as $upload) {
            $results[] = $this->attachOne($artwork, $upload, $rights, $request->user()?->id);
        }

        if (collect($results)->every(fn (array $r) => $r['status'] !== 'attached')) {
            return response()->json(['message' => 'No file in this batch could be attached.', 'results' => $results], 422);
        }

        return response()->json(['data' => self::present($artwork->refresh()), 'results' => $results], 201);
    }

    /**
     * Validates, checksums and (if new) stores one file. The duplicate check
     * queries the artwork's images fresh on every call, so it catches both a
     * file that matches one already on the artwork *and* a file that matches
     * one attached earlier in this same batch (each successful create()
     * above is immediately visible to the next iteration's query).
     *
     * @return array{filename: string|null, status: string, image_id: int|null, message: string|null}
     */
    private function attachOne(Artwork $artwork, UploadedFile $upload, string $rights, ?int $userId): array
    {
        $filename = $upload->getClientOriginalName();

        $validator = Validator::make(['image' => $upload], [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ]);
        if ($validator->fails()) {
            return ['filename' => $filename, 'status' => 'rejected', 'image_id' => null, 'message' => $validator->errors()->first('image')];
        }

        $checksum = hash_file('sha256', $upload->getRealPath());
        $matchedId = ArtworkImage::where('artwork_id', $artwork->id)->where('sha256', $checksum)->value('id');

        if ($matchedId !== null) {
            return ['filename' => $filename, 'status' => 'duplicate', 'image_id' => $matchedId, 'message' => 'Already attached to this artwork.'];
        }

        $size = @getimagesize($upload->getRealPath()) ?: [null, null];

        $image = $artwork->images()->create([
            'path' => $upload->store('artwork-images'),
            'original_filename' => $filename,
            'mime_type' => $upload->getMimeType() ?? 'image/jpeg',
            'size_bytes' => $upload->getSize(),
            'sha256' => $checksum,
            'width_px' => $size[0],
            'height_px' => $size[1],
            'rights_status' => $rights,
            'uploaded_by_user_id' => $userId,
        ]);

        return ['filename' => $filename, 'status' => 'attached', 'image_id' => $image->id, 'message' => null];
    }

    public function update(Request $request, Artwork $artwork, int $image): JsonResponse
    {
        $data = $request->validate([
            'rights_status' => ['sometimes', Rule::in(ArtworkImage::RIGHTS)],
            'is_final' => ['sometimes', 'boolean'],
            'view_role' => ['sometimes', 'nullable', Rule::in(ArtworkImage::VIEW_ROLES)],
            'is_public' => ['sometimes', 'boolean'],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ]);
        $file = $artwork->images()->findOrFail($image);

        DB::transaction(function () use ($artwork, $file, $data) {
            if (($data['is_final'] ?? false) === true) {
                $artwork->images()->where('id', '!=', $file->id)->where('is_final', true)->get()->each->update(['is_final' => false]);
            }
            $file->update(array_intersect_key($data, array_flip(['rights_status', 'is_final', 'view_role', 'is_public'])));
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
            'view_role' => $i->view_role,
            'is_public' => $i->is_public,
        ])->values()->all();
    }
}
