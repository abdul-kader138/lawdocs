<?php

namespace App\Support;

use App\Contracts\DeclaresPartyFlags;

/**
 * Universal "what [[IF:...]] flags / [[REPEAT:...]] groups are valid for
 * this precedent" computation — every declared questionnaire field (any
 * type, not just boolean) is flag-testable via AnswerContextBuilder's
 * runtime truthiness rule, and every configured party group is
 * REPEAT-available, merged with whatever extra flags/groups the assigned
 * generator itself declares (e.g. WillGenerator's REPEAT-scoped
 * "beneficiary.per_stirpes", which isn't derived from a top-level answer or
 * this precedent's own party_groups list at all).
 *
 * Shared by two consumers that must never drift apart — see
 * DeclaresPartyFlags's own docblock for why both exist:
 *   1. Precedent::validateMarkerReferences() — save-time, has a real
 *      Precedent model.
 *   2. PrecedentResource's live authoring-reference hint — operates on
 *      unsaved Filament form state, no Precedent instance yet.
 */
class PrecedentFlagResolver
{
    /**
     * @param  array<int, array<string, mixed>>  $questionnaireFields  raw Precedent::questionnaire_fields shape
     * @return array<string, string> flag key => human description
     */
    public static function availableFlags(array $questionnaireFields, DeclaresPartyFlags $generator): array
    {
        $fromAnswers = collect($questionnaireFields)
            ->filter(fn ($field) => filled($field['name'] ?? null))
            ->mapWithKeys(fn ($field) => [$field['name'] => $field['label'] ?? $field['name']])
            ->all();

        // Generator's explicit, hand-documented entries win on key
        // collision — unlikely in practice since generator-declared flags
        // are always dotted (e.g. "beneficiary.per_stirpes") and
        // questionnaire-derived ones are always bare.
        return [...$fromAnswers, ...$generator->availableFlags()];
    }

    /**
     * @param  array<int, array<string, mixed>>  $partyGroups  raw Precedent::party_groups shape
     * @return array<string, string> group key => human description
     */
    public static function availableGroups(array $partyGroups, DeclaresPartyFlags $generator): array
    {
        $fromPartyGroups = collect($partyGroups)
            ->filter(fn ($group) => filled($group['key'] ?? null))
            ->mapWithKeys(fn ($group) => [$group['key'] => $group['label'] ?? $group['key']])
            ->all();

        return [...$fromPartyGroups, ...$generator->availableGroups()];
    }
}
