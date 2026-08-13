<?php

namespace App\Exceptions;

use Exception;

/**
 * Generation-time failure while EXPANDING an already-parsed clause tree
 * (evaluating an [[IF]] condition or substituting a {{...}} placeholder) —
 * distinct from ClauseMarkerException, which is a structural parse-time
 * problem with the marker syntax itself. Fails loud for the same reason
 * ClauseMarkerException does: a silently-wrong condition or an un-substituted
 * placeholder could produce an incorrect legal document.
 */
class ClauseTemplateException extends Exception
{
    public static function unknownFlag(string $name): self
    {
        return new self("Unknown flag or field \"{$name}\" referenced in an [[IF:...]] condition. It must be a boolean computed by the generator or a boolean field on the current [[REPEAT]] item.");
    }

    public static function malformedCondition(string $condition): self
    {
        return new self("Malformed [[IF:...]] condition: \"{$condition}\". Allowed: identifiers (optionally dotted, e.g. beneficiary.per_stirpes), AND, OR, NOT, and parentheses only.");
    }

    public static function unresolvedPlaceholder(string $token): self
    {
        return new self('Unresolved placeholder {{'.$token.'}} — "answers" is always in scope, but a repeat alias is only in scope inside its own [[REPEAT:...]] block.');
    }
}
