<?php

namespace App\Services;

/**
 * Turns a DocumentRequest's raw answers into the enriched answers + flags
 * every generator feeds into ClauseTemplateExpander/ClauseSequenceRenderer —
 * the single place that makes plain questionnaire fields usable as
 * [[IF:...]] conditions and, for any "*_gender"-suffixed field, auto-adds
 * {{answers.<base>_pronoun_subject/object/possessive/reflexive}} the same
 * way PartyGroupAssembler::normalize() already does for party rows. This is
 * what lets a document needing conditional appointee/fallback wording (e.g.
 * "if no alternate is named, fall back to X") be expressed entirely as
 * marker content instead of a generator-authored PHP method.
 */
class AnswerContextBuilder
{
    private const PRONOUN_ROLES = ['subject', 'object', 'possessive', 'reflexive'];

    public function __construct(private readonly GrammarResolver $grammar) {}

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<int, string>  $declaredFieldNames  every questionnaire field name declared on the
     *                                                  precedent — a field a CSV import left entirely
     *                                                  absent (not null, just missing) is backfilled to
     *                                                  null here so it still resolves to a real, non-
     *                                                  throwing flag value at generation time instead of
     *                                                  ConditionEvaluator's unknownFlag.
     * @return array{answers: array<string, mixed>, flags: array<string, bool>}
     */
    public function build(array $answers, array $declaredFieldNames = []): array
    {
        foreach ($declaredFieldNames as $name) {
            if (! array_key_exists($name, $answers)) {
                $answers[$name] = null;
            }
        }

        // Real booleans pass through unchanged (zero behavior change for
        // existing hand-picked flags); every other field becomes
        // [[IF:...]]-testable as "was this filled in".
        $flags = collect($answers)
            ->map(fn ($value) => is_bool($value) ? $value : filled($value))
            ->all();

        $enrichedAnswers = $answers;

        foreach ($answers as $key => $value) {
            if (! str_ends_with($key, '_gender') || ! is_string($value) || ! filled($value)) {
                continue;
            }

            $base = substr($key, 0, -strlen('_gender'));

            foreach (self::PRONOUN_ROLES as $role) {
                $pronounKey = "{$base}_pronoun_{$role}";

                // Never clobber a real questionnaire field that happens to
                // collide with a synthetic name.
                if (! array_key_exists($pronounKey, $enrichedAnswers)) {
                    $enrichedAnswers[$pronounKey] = $this->grammar->pronoun($value, $role);
                }
            }
        }

        return ['answers' => $enrichedAnswers, 'flags' => $flags];
    }

    /**
     * Pure, value-free expansion of declared answer field NAMES to include
     * the pronoun-sibling names any "*_gender" field produces at runtime —
     * used by save-time {{answers.field}} placeholder validation, which only
     * has field declarations, never real answer values.
     *
     * @param  array<int, string>  $fieldNames
     * @return array<int, string>
     */
    public static function derivedAnswerFieldNames(array $fieldNames): array
    {
        $derived = $fieldNames;

        foreach ($fieldNames as $name) {
            if (! str_ends_with($name, '_gender')) {
                continue;
            }

            $base = substr($name, 0, -strlen('_gender'));

            foreach (self::PRONOUN_ROLES as $role) {
                $derived[] = "{$base}_pronoun_{$role}";
            }
        }

        return array_values(array_unique($derived));
    }
}
