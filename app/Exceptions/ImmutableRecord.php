<?php

namespace App\Exceptions;

use LogicException;

/**
 * Thrown when code tries to change or delete a record the ledger keeps
 * forever (LLD 07 §3). It signals a programming error, not a user mistake.
 */
final class ImmutableRecord extends LogicException
{
    public static function for(string $what): self
    {
        return new self("{$what} cannot be changed or deleted; reverse it instead.");
    }
}
