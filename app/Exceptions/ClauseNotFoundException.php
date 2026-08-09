<?php

namespace App\Exceptions;

use App\Models\Precedent;
use Exception;

class ClauseNotFoundException extends Exception
{
    public static function forTag(Precedent $precedent, string $tag, array $availableTags): self
    {
        $available = $availableTags ? implode(', ', $availableTags) : '(none found)';

        return new self(
            "Clause \"{$tag}\" not found in precedent #{$precedent->id} (\"{$precedent->title}\"). Available tags: {$available}."
        );
    }
}
