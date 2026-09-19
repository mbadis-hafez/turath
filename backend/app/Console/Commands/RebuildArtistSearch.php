<?php

namespace App\Console\Commands;

use App\Models\Artist;
use App\Support\ArtistSearchTextBuilder;
use Illuminate\Console\Command;

class RebuildArtistSearch extends Command
{
    protected $signature = 'artists:rebuild-search';

    protected $description = 'Rebuild the normalized search columns for all artists (used by bulk imports)';

    public function handle(): int
    {
        $count = 0;

        Artist::with('variants')->chunkById(200, function ($artists) use (&$count) {
            foreach ($artists as $artist) {
                ArtistSearchTextBuilder::rebuildQuietly($artist);
                $count++;
            }
        });

        $this->info("Rebuilt search columns for {$count} artists.");

        return self::SUCCESS;
    }
}
