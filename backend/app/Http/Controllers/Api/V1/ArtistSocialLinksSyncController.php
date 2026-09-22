<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Artist\SyncArtistSocialLinksRequest;
use App\Models\Artist;
use App\Models\ArtistSocialLink;
use App\Support\Curation\ArtistSections;
use Illuminate\Http\JsonResponse;

class ArtistSocialLinksSyncController
{
    public function __invoke(SyncArtistSocialLinksRequest $request, Artist $artist): JsonResponse
    {
        (new ArtistSections)->syncSocialLinks($artist, $request->input('links', []));

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
