<?php

use App\Models\ArchiveItem;
use App\Models\Artist;
use App\Models\Artwork;
use App\Models\Event;

return [
    /*
    |--------------------------------------------------------------------------
    | Citable resource types
    |--------------------------------------------------------------------------
    |
    | Allow-list mapping of URL type segments (as used in
    | /api/v1/records/{type}/{id}/...) to model classes and the permission
    | required to manage citations/edits on that entity type. Unknown
    | segments return 404.
    |
    */

    'types' => [
        'artists' => ['model' => Artist::class, 'manage_permission' => 'artists.manage'],
        'artworks' => ['model' => Artwork::class, 'manage_permission' => 'artworks.manage'],
        'events' => ['model' => Event::class, 'manage_permission' => 'events.manage'],
        'archive-items' => ['model' => ArchiveItem::class, 'manage_permission' => 'archive.manage'],
    ],
];
