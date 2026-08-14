<?php

namespace App\Services;

use App\Exceptions\ClauseTemplateException;

/** Renders the same {{namespace.field}} syntax used inside precedent clauses. */
class TemplateStringRenderer
{
    private const PLACEHOLDER_RE = '/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\.([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/';

    public function render(string $template, array $context): string
    {
        return preg_replace_callback(self::PLACEHOLDER_RE, function (array $matches) use ($context) {
            $value = $context[$matches[1]][$matches[2]] ?? null;

            if (! array_key_exists($matches[1], $context)
                || ! array_key_exists($matches[2], $context[$matches[1]])) {
                throw ClauseTemplateException::unresolvedPlaceholder("{$matches[1]}.{$matches[2]}");
            }

            return (string) $value;
        }, $template);
    }
}
