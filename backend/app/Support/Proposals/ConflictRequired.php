<?php

namespace App\Support\Proposals;

use RuntimeException;

/**
 * @param  array<int, array<string, mixed>>  $conflicts
 */
class ConflictRequired extends RuntimeException
{
    /**
     * @param  array<int, array<string, mixed>>  $conflicts
     */
    public function __construct(public readonly array $conflicts)
    {
        parent::__construct('The record changed since this proposal was written.');
    }
}
