<?php

use App\Models\ArchiveItem;
use App\Models\ArchiveItemLink;
use App\Models\Artist;
use App\Models\ArtistNameVariant;
use App\Models\Artwork;
use App\Models\File;
use App\Models\Holder;

return [
    /*
    |--------------------------------------------------------------------------
    | Activity subject resources
    |--------------------------------------------------------------------------
    |
    | Allow-list mapping of URL resource segments to model classes, used by
    | the per-entity activity history endpoint. Unknown segments return 404.
    |
    | Each entry is either a model class string, or an array with the model
    | class plus "children" — related subjects whose history is included with
    | the parent's (matched by a foreign key back to the parent).
    |
    */

    'resources' => [
        'artists' => [
            'model' => Artist::class,
            'children' => [
                ['class' => ArtistNameVariant::class, 'fk' => 'artist_id'],
            ],
        ],
        'artworks' => Artwork::class,
        'holders' => Holder::class,
        'archive-items' => [
            'model' => ArchiveItem::class,
            'children' => [
                ['class' => File::class, 'fk' => 'archive_item_id'],
                ['class' => ArchiveItemLink::class, 'fk' => 'archive_item_id'],
            ],
        ],
    ],
];
