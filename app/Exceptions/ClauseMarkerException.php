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
        return new self("Clause \"{$tag}\" contains a {$elementClass}, which is not supported inside clause markers yet.");
    }

    public static function controlMarkerInsideTableCell(string $tag, string $line): self
    {
        return new self("Clause \"{$tag}\": table cell contains \"{$line}\", but [[IF]]/[[REPEAT]] markers inside a table cell aren't supported yet.");
    }

    public static function nestedTableUnsupported(string $tag): self
    {
        return new self("Clause \"{$tag}\" contains a table nested inside another table, which is not supported.");
    }

    public static function unclosedIf(string $tag): self
    {
        return new self("Clause \"{$tag}\" has an [[IF:...]] that was never closed with [[/IF]].");
    }

    public static function unclosedRepeat(string $tag): self
    {
        return new self("Clause \"{$tag}\" has a [[REPEAT:...]] that was never closed with [[/REPEAT]].");
    }

    public static function strayElse(): self
    {
        return new self('Found [[ELSE]] with no matching [[IF:...]] open.');
    }

    public static function doubleElse(string $tag): self
    {
        return new self("Clause \"{$tag}\" has more than one [[ELSE]] inside the same [[IF:...]] block.");
    }

    public static function strayEndIf(): self
    {
        return new self('Found [[/IF]] with no matching [[IF:...]] open.');
    }

    public static function strayEndRepeat(): self
    {
        return new self('Found [[/REPEAT]] with no matching [[REPEAT:...]] open.');
    }

    public static function mismatchedClose(string $expectedKind, string $foundMarker): self
    {
        return new self("Found {$foundMarker} but the innermost open block is a(n) {$expectedKind} — close that first.");
    }

    public static function controlMarkerOutsideClause(string $markerName): self
    {
        return new self("Found [[{$markerName}...]] outside of any [[CLAUSE:...]] block — [[IF]]/[[REPEAT]] markers are only valid inside a clause.");
    }

    public static function malformedRepeatHeader(string $tag, string $line): self
    {
        return new self("Clause \"{$tag}\" has a malformed [[REPEAT:...]] marker: \"{$line}\". Expected [[REPEAT:group_key]] or [[REPEAT:group_key AS alias]].");
    }

    public static function malformedConditionSyntax(string $tag, string $line): self
    {
        return new self("Clause \"{$tag}\" has an [[IF:...]] marker with an empty condition: \"{$line}\".");
    }

    public function withPrecedentContext(Precedent $precedent): self
    {
        return new self("{$this->getMessage()} (in precedent #{$precedent->id}: \"{$precedent->title}\")", 0, $this);
    }
}
