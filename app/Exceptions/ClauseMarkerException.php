<?php

namespace App\Exceptions;

use App\Models\Precedent;
use Exception;

/**
 * Malformed clause markup in a precedent (unclosed/duplicate/stray/nested
 * tags). Deliberately fails loud — a silently mis-parsed clause boundary
 * could splice the wrong text into a legal document.
 */
class ClauseMarkerException extends Exception
{
    public static function unclosed(string $tag): self
    {
        return new self("Clause [[CLAUSE:{$tag}]] was never closed with [[/CLAUSE]].");
    }

    public static function duplicateTag(string $tag): self
    {
        return new self("Clause tag \"{$tag}\" is opened more than once.");
    }

    public static function nestedOpen(string $outerTag, string $innerTag): self
    {
        return new self("Clause [[CLAUSE:{$innerTag}]] opens before [[CLAUSE:{$outerTag}]] was closed. Nested clause markers are not supported.");
    }

    public static function strayClose(): self
    {
        return new self('Found [[/CLAUSE]] with no matching [[CLAUSE:...]] open.');
    }

    public static function unsupportedElement(string $tag, string $elementClass): self
    {
        return new self("Clause \"{$tag}\" contains a {$elementClass}, which is not supported inside clause markers yet (e.g. tables).");
    }

    public function withPrecedentContext(Precedent $precedent): self
    {
        return new self("{$this->getMessage()} (in precedent #{$precedent->id}: \"{$precedent->title}\")", 0, $this);
    }
}
