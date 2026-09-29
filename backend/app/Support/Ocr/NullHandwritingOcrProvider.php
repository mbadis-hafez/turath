<?php

namespace App\Support\Ocr;

/**
 * The default HandwritingOcrProvider: no self-hosted handwriting model is
 * wired up yet (see docs decision on 2026-09-30 — self-hosted Kraken/Muharaf
 * was chosen over a cloud vision API, largely over Saudi data-residency
 * concerns; KrakenHandwritingOcrProvider is the structural placeholder for
 * that, not yet bound). Handwriting regions are cropped and sent to manual
 * review with no machine suggestion at all until that's wired up — which is
 * a fully supported, expected state, not a degraded one.
 */
class NullHandwritingOcrProvider implements HandwritingOcrProvider
{
    public function suggest(string $cropImagePath, string $language): ?array
    {
        return null;
    }
}
