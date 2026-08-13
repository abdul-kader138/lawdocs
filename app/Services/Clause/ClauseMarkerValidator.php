<?php

namespace App\Services\Clause;

/**
 * Best-effort, save-time typo catcher — NOT the safety boundary itself
 * (ConditionEvaluator/ClauseTemplateExpander still fail loud at generation
 * time regardless). This walks a parsed clause tree and flags every
 * [[IF:...]] atom, [[REPEAT:group]] group key, and {{ns.field}} placeholder
 * that doesn't resolve against what the precedent/generator actually
 * declare, so an admin sees "unknown flag — did you mean X?" when saving
 * the precedent instead of a staff member hitting it on first real use.
 */
class ClauseMarkerValidator
{
    /**
     * Synthetic fields PartyGroupAssembler::normalize() MAY add to a row at
     * generation time — never declared in party_groups[].fields (those only
     * list admin-entered fields) but valid to reference in {{alias.field}}
     * regardless. Deliberately permissive rather than precisely conditional
     * on the group actually having a "gender"/"dob" field: this validator is
     * a best-effort typo-catcher, not the safety boundary (see class
     * docblock) — a group missing the source field still fails loud with a
     * clear "unresolved placeholder" error at real generation time.
     */
    private const SYNTHETIC_ROW_FIELDS = [
        'id', 'position', 'is_minor',
        'pronoun_subject', 'pronoun_object', 'pronoun_possessive', 'pronoun_reflexive',
        'substitute',
    ];

    /**
     * @param  array<string, array<int, ClauseElement|ClauseBlockNode>>  $tree  tag => nodes
     * @param  array<int, string>  $answerFieldNames  questionnaire_fields names
     * @param  array<string, array<int, string>>  $partyGroupFieldsByKey  party_groups key => field names
     * @param  array<string, string>  $availableFlags  generator's availableFlags()
     * @param  array<string, string>  $availableGroups  generator's availableGroups()
     * @return array<int, string> human-readable issues, empty if none found
     */
    public function validate(
        array $tree,
        array $answerFieldNames,
        array $partyGroupFieldsByKey,
        array $availableFlags,
        array $availableGroups,
    ): array {
        $issues = [];
        $validGroupKeys = array_values(array_unique([...array_keys($partyGroupFieldsByKey), ...array_keys($availableGroups)]));

        foreach ($tree as $tag => $nodes) {
            $this->walk($nodes, $tag, [], $answerFieldNames, $partyGroupFieldsByKey, $availableFlags, $validGroupKeys, $issues);
        }

        return $issues;
    }

    /**
     * @param  array<int, ClauseElement|ClauseBlockNode>  $nodes
     * @param  array<string, string>  $scope  alias => groupKey, for REPEATs currently open above this point
     * @param  array<int, string>  $issues
     */
    private function walk(array $nodes, string $tag, array $scope, array $answerFieldNames, array $partyGroupFieldsByKey, array $availableFlags, array $validGroupKeys, array &$issues): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof ClauseElement) {
                if ($node->kind === 'table') {
                    $this->checkTablePlaceholders($node, $tag, $scope, $answerFieldNames, $partyGroupFieldsByKey, $issues);
                } else {
                    $this->checkPlaceholders($node, $tag, $scope, $answerFieldNames, $partyGroupFieldsByKey, $issues);
                }

                continue;
            }

            if ($node->kind === 'if') {
                $this->checkCondition($node->condition, $tag, $availableFlags, $issues);
                $this->walk($node->then, $tag, $scope, $answerFieldNames, $partyGroupFieldsByKey, $availableFlags, $validGroupKeys, $issues);
                if ($node->else !== null) {
                    $this->walk($node->else, $tag, $scope, $answerFieldNames, $partyGroupFieldsByKey, $availableFlags, $validGroupKeys, $issues);
                }

                continue;
            }

            // 'repeat'
            if (! in_array($node->groupKey, $validGroupKeys, true)) {
                $issues[] = "Clause \"{$tag}\": [[REPEAT:{$node->groupKey}]] references an unknown party group. {$this->suggest($node->groupKey, $validGroupKeys)}";
            }

            $childScope = $scope;
            $childScope[$node->alias] = $node->groupKey;
            $this->walk($node->children, $tag, $childScope, $answerFieldNames, $partyGroupFieldsByKey, $availableFlags, $validGroupKeys, $issues);
        }
    }

    /** @param array<string, string> $availableFlags */
    private function checkCondition(string $condition, string $tag, array $availableFlags, array &$issues): void
    {
        foreach ($this->extractAtoms($condition) as $atom) {
            if (! array_key_exists($atom, $availableFlags)) {
                $issues[] = "Clause \"{$tag}\": [[IF:{$condition}]] references unknown flag \"{$atom}\". {$this->suggest($atom, array_keys($availableFlags))}";
            }
        }
    }

    /**
     * Same atom/flag cross-check as checkCondition(), for a condition string
     * that isn't inside a parsed clause tree at all — used for
     * Precedent::clause_sequence's per-entry "show only if" conditions,
     * which gate a whole section rather than text inside one.
     *
     * @param  array<int, array{label: string, condition: string}>  $conditions
     * @param  array<string, string>  $availableFlags
     * @return array<int, string>
     */
    public function validateConditions(array $conditions, array $availableFlags): array
    {
        $issues = [];

        foreach ($conditions as $entry) {
            foreach ($this->extractAtoms($entry['condition']) as $atom) {
                if (! array_key_exists($atom, $availableFlags)) {
                    $issues[] = "{$entry['label']}: condition \"{$entry['condition']}\" references unknown flag \"{$atom}\". {$this->suggest($atom, array_keys($availableFlags))}";
                }
            }
        }

        return $issues;
    }

    /**
     * @param  array<string, string>  $scope
     * @param  array<int, string>  $answerFieldNames
     * @param  array<string, array<int, string>>  $partyGroupFieldsByKey
     */
    private function checkPlaceholders(ClauseElement $element, string $tag, array $scope, array $answerFieldNames, array $partyGroupFieldsByKey, array &$issues): void
    {
        foreach ($element->runs as $run) {
            if (! preg_match_all('/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\.([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/', $run['text'] ?? '', $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $m) {
                [, $namespace, $field] = $m;

                if ($namespace === 'answers') {
                    if (! in_array($field, $answerFieldNames, true)) {
                        $issues[] = "Clause \"{$tag}\": {{answers.{$field}}} references an unknown questionnaire field. {$this->suggest($field, $answerFieldNames)}";
                    }

                    continue;
                }

                if (! array_key_exists($namespace, $scope)) {
                    $issues[] = "Clause \"{$tag}\": {{{$namespace}.{$field}}} — \"{$namespace}\" is not a [[REPEAT:...AS {$namespace}]] alias in scope at this point.";

                    continue;
                }

                if (in_array($field, self::SYNTHETIC_ROW_FIELDS, true)) {
                    continue;
                }

                $groupFields = $partyGroupFieldsByKey[$scope[$namespace]] ?? null;
                if ($groupFields !== null && ! in_array($field, $groupFields, true)) {
                    $issues[] = "Clause \"{$tag}\": {{{$namespace}.{$field}}} references a field not declared on party group \"{$scope[$namespace]}\". {$this->suggest($field, $groupFields)}";
                }
            }
        }
    }

    /**
     * Table cells can never contain [[IF]]/[[REPEAT]] nodes (enforced at
     * parse time — see ClauseMarkerParser::captureCellElements()), so unlike
     * walk() itself there's nothing to recurse into but flat ClauseElement
     * leaves; $scope is passed through unchanged so a {{alias.field}}
     * reference to an outer [[REPEAT:...AS alias]] that happens to wrap a
     * clause-level table still resolves correctly.
     *
     * @param  array<string, string>  $scope
     * @param  array<int, string>  $answerFieldNames
     * @param  array<string, array<int, string>>  $partyGroupFieldsByKey
     */
    private function checkTablePlaceholders(ClauseElement $table, string $tag, array $scope, array $answerFieldNames, array $partyGroupFieldsByKey, array &$issues): void
    {
        foreach ($table->tableRows ?? [] as $row) {
            foreach ($row['cells'] as $cell) {
                foreach ($cell['content'] as $leaf) {
                    $this->checkPlaceholders($leaf, $tag, $scope, $answerFieldNames, $partyGroupFieldsByKey, $issues);
                }
            }
        }
    }

    /** @return array<int, string> */
    private function extractAtoms(string $condition): array
    {
        preg_match_all('/(AND|OR|NOT|\(|\)|[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)/', $condition, $matches);

        return array_values(array_diff($matches[1] ?? [], ['AND', 'OR', 'NOT']));
    }

    /** @param array<int, string> $validOptions */
    private function suggest(string $needle, array $validOptions): string
    {
        if ($validOptions === []) {
            return 'None are declared.';
        }

        $closest = null;
        $bestDistance = null;
        foreach ($validOptions as $candidate) {
            $distance = levenshtein($needle, $candidate);
            if ($bestDistance === null || $distance < $bestDistance) {
                $bestDistance = $distance;
                $closest = $candidate;
            }
        }

        $hint = $bestDistance !== null && $bestDistance <= 3 ? " Did you mean \"{$closest}\"?" : '';

        return 'Valid options: '.implode(', ', $validOptions).".{$hint}";
    }
}
