<?php

namespace App\Exceptions;

use RuntimeException;

class RegistrationRejected extends RuntimeException
{
    /** @param list<string> $reasons */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct(implode(' ', $reasons));
    }
}
