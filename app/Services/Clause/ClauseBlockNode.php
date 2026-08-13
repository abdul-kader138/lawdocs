<?php

namespace App\Services\Clause;

/**
 * An [[IF]] or [[REPEAT]] control node captured from inside a clause marker,
 * sibling to ClauseElement in the tree ClauseMarkerParser now produces.
 * ClauseTemplateExpander is what walks these into a flat ClauseElement[]
 * DocxBuilder can render — this class itself has no evaluation logic.
 */
final class ClauseBlockNode
{
    private function __construct(
        public readonly string $kind, // 'if'|'repeat'
        public readonly ?string $condition = null,
        public readonly array $then = [],
        public readonly ?array $else = null,
        public readonly ?string $groupKey = null,
        public readonly ?string $alias = null,
        public readonly array $children = [],
    ) {}

    /** @param array<int, ClauseElement|ClauseBlockNode> $then */
    public static function ifNode(string $condition, array $then, ?array $else): self
    {
        return new self(kind: 'if', condition: $condition, then: $then, else: $else);
    }

    /** @param array<int, ClauseElement|ClauseBlockNode> $children */
    public static function repeatNode(string $groupKey, string $alias, array $children): self
    {
        return new self(kind: 'repeat', groupKey: $groupKey, alias: $alias, children: $children);
    }
}
