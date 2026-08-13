<?php

namespace App\Services\Clause;

use App\Exceptions\ClauseTemplateException;

/**
 * Walks the ClauseElement|ClauseBlockNode tree ClauseMarkerParser now
 * produces and flattens it into the plain ClauseElement[] DocxBuilder
 * already knows how to render — resolving [[IF]] branches, expanding
 * [[REPEAT]] over structured party data, and substituting {{ns.field}}
 * placeholders along the way.
 *
 * $context shape:
 *   [
 *     'answers' => array<string, mixed>,               // flat questionnaire answers, always in scope as {{answers.x}}
 *     'flags'   => array<string, bool>,                 // generator-computed named flags for [[IF]]
 *     'items'   => array<string, array<int, array>>,    // PartyGroupAssembler::forRequest() output, keyed by group_key
 *     'scope'   => array<string, array>,                // current [[REPEAT]] alias => row, pushed/popped during expansion
 *   ]
 */
class ClauseTemplateExpander
{
    private const PLACEHOLDER_RE = '/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\.([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/';

    public function __construct(private readonly ConditionEvaluator $conditions = new ConditionEvaluator) {}

    /**
     * @param  array<int, ClauseElement|ClauseBlockNode>  $nodes
     * @return ClauseElement[]
     */
    public function expand(array $nodes, array $context): array
    {
        $context += ['answers' => [], 'flags' => [], 'items' => [], 'scope' => []];

        $result = [];
        foreach ($nodes as $node) {
            if ($node instanceof ClauseElement) {
                $result[] = $this->expandElement($node, $context);

                continue;
            }

            if ($node->kind === 'if') {
                $branch = $this->conditions->evaluate($node->condition, $this->flatFlags($context))
                    ? $node->then
                    : ($node->else ?? []);
                array_push($result, ...$this->expand($branch, $context));

                continue;
            }

            // 'repeat': zero matching items is valid (e.g. no guardians when
            // there are no minor children) — simply produces nothing.
            $items = $context['items'][$node->groupKey] ?? [];
            foreach ($items as $row) {
                $iterationContext = $context;
                $iterationContext['scope'] = [...$context['scope'], $node->alias => $row];
                array_push($result, ...$this->expand($node->children, $iterationContext));
            }
        }

        return $result;
    }

    private function expandElement(ClauseElement $element, array $context): ClauseElement
    {
        if ($element->kind === 'table') {
            return $this->expandTable($element, $context);
        }

        $hasPlaceholder = false;
        foreach ($element->runs as $run) {
            if (str_contains($run['text'], '{{')) {
                $hasPlaceholder = true;
                break;
            }
        }

        // No placeholder present: return the SAME object, not a copy — this
        // is both the backward-compat guarantee (a plain clause with no
        // control markers is byte-identical to before) and, for elements
        // reached via [[REPEAT]], what keeps a shared live Numbering object
        // instance across every iteration so DocxBuilder's
        // numberingNameFor() dedups them onto one sequential numId instead
        // of each iteration restarting at "1.".
        if (! $hasPlaceholder) {
            return $element;
        }

        $newRuns = array_map(
            fn (array $run) => ['text' => $this->substitute($run['text'], $context), 'fontStyle' => $run['fontStyle']],
            $element->runs
        );

        return $element->withRuns($newRuns);
    }

    /**
     * Whole-table short-circuit, not per-cell: a table's cell styles aren't
     * registry-backed (see ClauseElement::$tableRows docblock), so there's no
     * shared-identity hazard to protect at finer granularity — scanning every
     * cell once and, if nothing anywhere has a placeholder, returning the
     * same instance is both correct and simpler than per-cell short-circuiting.
     * Cell content is always plain ClauseElement leaves (no [[IF]]/[[REPEAT]]
     * inside a cell — enforced at parse time), so expandElement() recurses
     * directly with no ClauseBlockNode branch to consider.
     */
    private function expandTable(ClauseElement $table, array $context): ClauseElement
    {
        $hasPlaceholder = false;
        foreach ($table->tableRows ?? [] as $row) {
            foreach ($row['cells'] as $cell) {
                foreach ($cell['content'] as $leaf) {
                    foreach ($leaf->runs as $run) {
                        if (str_contains($run['text'], '{{')) {
                            $hasPlaceholder = true;
                            break 4;
                        }
                    }
                }
            }
        }

        if (! $hasPlaceholder) {
            return $table;
        }

        $newRows = array_map(
            fn (array $row) => [
                'cells' => array_map(
                    fn (array $cell) => [
                        ...$cell,
                        'content' => array_map(fn (ClauseElement $leaf) => $this->expandElement($leaf, $context), $cell['content']),
                    ],
                    $row['cells']
                ),
            ],
            $table->tableRows
        );

        return $table->withTableRows($newRows);
    }

    private function substitute(string $text, array $context): string
    {
        return preg_replace_callback(
            self::PLACEHOLDER_RE,
            function (array $m) use ($context) {
                [, $namespace, $field] = $m;

                $namespaceData = $namespace === 'answers'
                    ? $context['answers']
                    : ($context['scope'][$namespace] ?? null);

                if ($namespaceData === null || ! array_key_exists($field, $namespaceData)) {
                    throw ClauseTemplateException::unresolvedPlaceholder("{$namespace}.{$field}");
                }

                return (string) $namespaceData[$field];
            },
            $text
        );
    }

    /** Global flags plus every boolean-valued field on each row currently in scope, keyed "alias.field". */
    private function flatFlags(array $context): array
    {
        $flags = $context['flags'];

        foreach ($context['scope'] as $alias => $row) {
            foreach ($row as $field => $value) {
                if (is_bool($value)) {
                    $flags["{$alias}.{$field}"] = $value;
                }
            }
        }

        return $flags;
    }
}
