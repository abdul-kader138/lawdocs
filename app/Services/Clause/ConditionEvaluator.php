<?php

namespace App\Services\Clause;

use App\Exceptions\ClauseTemplateException;

/**
 * Evaluates an [[IF:condition]] string against a flat dict of named boolean
 * flags. Deliberately NOT eval() and deliberately NOT a general expression
 * language — this is the safety boundary an admin's typed [[IF:...]] text
 * can never cross: no arithmetic, no string/date comparison, no function
 * calls. An atom is either a bare identifier (a flag the generator computed,
 * e.g. has_alternate_executor) or a dotted one scoped to the innermost
 * enclosing [[REPEAT:... AS alias]] (e.g. beneficiary.per_stirpes),
 * combined only with AND / OR / NOT / parentheses.
 *
 *   condition := orExpr
 *   orExpr     := andExpr ("OR" andExpr)*
 *   andExpr    := notExpr ("AND" notExpr)*
 *   notExpr    := "NOT" notExpr | atom
 *   atom       := IDENT ("." IDENT)? | "(" orExpr ")"
 *
 * Anything outside that grammar (unbalanced parens, disallowed characters,
 * an empty condition, an unrecognized flag) throws ClauseTemplateException
 * rather than silently evaluating to something — a mistyped flag name must
 * never be mistaken for "false" in a legal document.
 */
class ConditionEvaluator
{
    private const KEYWORDS = ['AND', 'OR', 'NOT'];

    public function evaluate(string $condition, array $flags): bool
    {
        $tokens = $this->tokenize($condition);
        $pos = 0;

        $result = $this->parseOr($tokens, $pos, $flags, $condition);

        if ($pos !== count($tokens)) {
            throw ClauseTemplateException::malformedCondition($condition);
        }

        return $result;
    }

    /** @return array<int, string> */
    private function tokenize(string $condition): array
    {
        $tokens = [];
        $remaining = $condition;

        while (true) {
            $remaining = ltrim($remaining);
            if ($remaining === '') {
                break;
            }

            if (! preg_match('/^(AND\b|OR\b|NOT\b|\(|\)|[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)/', $remaining, $m)) {
                throw ClauseTemplateException::malformedCondition($condition);
            }

            $tokens[] = $m[1];
            $remaining = substr($remaining, strlen($m[0]));
        }

        if ($tokens === []) {
            throw ClauseTemplateException::malformedCondition($condition);
        }

        return $tokens;
    }

    /** @param array<int, string> $tokens */
    private function parseOr(array $tokens, int &$pos, array $flags, string $original): bool
    {
        $result = $this->parseAnd($tokens, $pos, $flags, $original);

        while (($tokens[$pos] ?? null) === 'OR') {
            $pos++;
            $result = $this->parseAnd($tokens, $pos, $flags, $original) || $result;
        }

        return $result;
    }

    /** @param array<int, string> $tokens */
    private function parseAnd(array $tokens, int &$pos, array $flags, string $original): bool
    {
        $result = $this->parseNot($tokens, $pos, $flags, $original);

        while (($tokens[$pos] ?? null) === 'AND') {
            $pos++;
            $result = $this->parseNot($tokens, $pos, $flags, $original) && $result;
        }

        return $result;
    }

    /** @param array<int, string> $tokens */
    private function parseNot(array $tokens, int &$pos, array $flags, string $original): bool
    {
        if (($tokens[$pos] ?? null) === 'NOT') {
            $pos++;

            return ! $this->parseNot($tokens, $pos, $flags, $original);
        }

        return $this->parseAtom($tokens, $pos, $flags, $original);
    }

    /** @param array<int, string> $tokens */
    private function parseAtom(array $tokens, int &$pos, array $flags, string $original): bool
    {
        $token = $tokens[$pos] ?? null;

        if ($token === '(') {
            $pos++;
            $result = $this->parseOr($tokens, $pos, $flags, $original);

            if (($tokens[$pos] ?? null) !== ')') {
                throw ClauseTemplateException::malformedCondition($original);
            }
            $pos++;

            return $result;
        }

        if ($token === null || in_array($token, [...self::KEYWORDS, ')'], true)) {
            throw ClauseTemplateException::malformedCondition($original);
        }

        $pos++;

        if (! array_key_exists($token, $flags)) {
            throw ClauseTemplateException::unknownFlag($token);
        }

        return (bool) $flags[$token];
    }
}
