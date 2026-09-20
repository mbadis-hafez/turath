<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Artist;
use App\Models\ArtistSocialLink;
use App\Support\Curation\ChildSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArtistSocialLinksSyncController
{
    public function __invoke(Request $request, Artist $artist): JsonResponse
    {
        $data = $request->validate([
            'links' => ['present', 'array', 'max:30'],
            'links.*.id' => ['nullable', 'integer'],
            'links.*.platform' => ['required', Rule::in(ArtistSocialLink::PLATFORMS)],
            'links.*.url' => ['required', 'url', 'max:500'],
            'links.*.is_public' => ['sometimes', 'boolean'],
            'edit_summary' => ['nullable', 'string', 'max:255'],
        ]);

        ChildSync::sync($artist->socialLinks(), array_map(fn (array $l) => [
            'id' => $l['id'] ?? null, 'platform' => $l['platform'], 'url' => $l['url'], 'is_public' => $l['is_public'] ?? false,
        ], $data['links']));

        return response()->json(['data' => self::present($artist->refresh())]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function present(Artist $artist, bool $publicOnly = false): array
    {
        return $artist->socialLinks()->get()
            ->filter(fn (ArtistSocialLink $l) => ! $publicOnly || $l->is_public)
            ->map(fn (ArtistSocialLink $l) => ['id' => $l->id, 'platform' => $l->platform, 'url' => $l->url, 'is_public' => $l->is_public])
            ->values()->all();
    }
}
