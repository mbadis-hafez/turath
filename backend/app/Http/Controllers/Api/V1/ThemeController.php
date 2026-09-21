<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;
use App\Models\Theme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemeController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Theme::orderBy('id')->get()->map(fn ($t) => ['id' => $t->id, 'label' => ['ar' => $t->label_ar, 'en' => $t->label_en]])->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label_ar' => ['required_without:label_en', 'nullable', 'string', 'max:255'],
            'label_en' => ['required_without:label_ar', 'nullable', 'string', 'max:255'],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ]);
        $theme = Theme::create(['label_ar' => $data['label_ar'] ?? null, 'label_en' => $data['label_en'] ?? null]);

        return response()->json(['data' => ['id' => $theme->id, 'label' => ['ar' => $theme->label_ar, 'en' => $theme->label_en]]], 201);
    }

    public function sync(Request $request, Artist $artist): JsonResponse
    {
        return $this->syncFor($request, $artist);
    }

    public function syncEvent(Request $request, Event $event): JsonResponse
    {
        return $this->syncFor($request, $event);
    }

    public function syncArchiveItem(Request $request, ArchiveItem $archiveItem): JsonResponse
    {
        return $this->syncFor($request, $archiveItem);
    }

    public function syncArtwork(Request $request, Artwork $artwork): JsonResponse
    {
        return $this->syncFor($request, $artwork);
    }

    /** D123: one taxonomy for artists, artworks and events. */
    private function syncFor(Request $request, Artist|Event|Artwork|ArchiveItem $record): JsonResponse
    {
        $data = $request->validate(['theme_ids' => ['present', 'array'], 'theme_ids.*' => ['integer', 'exists:themes,id'], 'edit_summary' => ['nullable', 'string', 'max:255']]);
        $changes = $record->themes()->sync($data['theme_ids']);

        if ($changes['attached'] !== [] || $changes['detached'] !== []) {
            activity($record->getTable())->performedOn($record)->causedBy($request->user())->event('updated')
                ->withProperties(['edit_summary' => $request->input('edit_summary'), 'themes_attached' => $changes['attached'], 'themes_detached' => $changes['detached']])
                ->log('themes changed');
        }

        return response()->json(['data' => ['theme_ids' => $record->themes()->pluck('themes.id')]]);
    }
}
