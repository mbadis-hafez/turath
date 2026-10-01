<?php

namespace App\Models\Observers;

use App\Models\Artist;
use App\Models\EditProposal;
use App\Support\Ocr\ArtistContactProposalService;

class EditProposalObserver
{
    /** Document-sourced contact proposals follow the draft that carries them. */
    public function updated(EditProposal $proposal): void
    {
        if ($proposal->citable_type === Artist::class && $proposal->wasChanged(['status', 'payload'])) {
            app(ArtistContactProposalService::class)->follow($proposal);
        }
    }
}
