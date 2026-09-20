<?php

namespace App\Support\Proposals;

use RuntimeException;

class UnpublishConfirmationRequired extends RuntimeException
{
    /**
     * @param  array<int, string>  $blocking
     */
    public function __construct(public readonly array $blocking)
    {
        parent::__construct('This rollback would drop a published record below its publish requirements.');
    }
}
