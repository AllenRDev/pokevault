<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the upstream card database has no card for an id.
 *
 * A missing card is a client error rather than an upstream failure, so it is
 * translated into a 404 instead of bubbling up as a 500.
 */
class CardNotFoundException extends RuntimeException
{
    public static function forId(string $id): self
    {
        return new self("TCGdex has no card with the id [{$id}].");
    }
}
