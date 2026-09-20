<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ArchiveItem;
use App\Models\File;
use App\Support\ArchiveAccessResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** One original file per archive item, on the private disk and streamed through the API so access rules always apply. */
class ArchiveItemFileController
{
    public function download(Request $request, int $archiveItem, int $file): StreamedResponse
    {
        $item = ArchiveItem::withTrashed()->findOrFail($archiveItem);
        $record = File::where('archive_item_id', $item->id)->findOrFail($file);

        abort_if($item->trashed() && ! ($request->user()?->can('archive.manage')), 404);
        abort_unless(ArchiveAccessResolver::canViewFull($request->user(), $item), 404);
        abort_unless(Storage::disk($record->disk)->exists($record->path), 404);

        return Storage::disk($record->disk)->response($record->path, $record->original_filename);
    }

    public function store(Request $request, ArchiveItem $archiveItem): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,tif,tiff,pdf,doc,docx,mp3,wav,mp4,mov', 'max:102400'],
        ]);

        $upload = $request->file('file');
        $isImage = str_starts_with($upload->getMimeType() ?? '', 'image/');
        $size = $isImage ? (@getimagesize($upload->getRealPath()) ?: [null, null]) : [null, null];

        $this->removeOriginal($archiveItem);

        $archiveItem->files()->create([
            'role' => 'original',
            'disk' => 'local',
            'path' => $upload->store('archive-files'),
            'mime_type' => $upload->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $upload->getSize(),
            'sha256' => hash_file('sha256', $upload->getRealPath()),
            'width_px' => $size[0],
            'height_px' => $size[1],
            'original_filename' => $upload->getClientOriginalName(),
            'uploaded_by_user_id' => $request->user()?->id,
        ]);
        $archiveItem->touch();

        return response()->json(['data' => self::present($archiveItem->refresh())], 201);
    }

    public function destroy(ArchiveItem $archiveItem): JsonResponse
    {
        $this->removeOriginal($archiveItem);
        $archiveItem->touch();

        return response()->json(['data' => self::present($archiveItem->refresh())]);
    }

    private function removeOriginal(ArchiveItem $item): void
    {
        foreach ($item->files()->where('role', 'original')->get() as $old) {
            Storage::disk($old->disk)->delete($old->path);
            $old->forceDelete();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function present(ArchiveItem $item): ?array
    {
        $file = $item->files()->where('role', 'original')->latest('id')->first();

        return $file === null ? null : [
            'id' => $file->id,
            'name' => $file->original_filename,
            'mime_type' => $file->mime_type,
            'size_bytes' => $file->size_bytes,
            'width_px' => $file->width_px,
            'height_px' => $file->height_px,
            'is_image' => str_starts_with($file->mime_type, 'image/'),
            'url' => "/api/v1/archive-items/{$item->id}/files/{$file->id}/download",
        ];
    }
}
