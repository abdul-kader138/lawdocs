<?php

namespace App\Support;

/**
 * Centralized, declarative lookup for the handful of jurisdiction rules that
 * have data actually available BEFORE generation (i.e. only what a
 * questionnaire answer can tell us) — deliberately not a place for anything
 * requiring judgment a solicitor should make (witness eligibility, informal
 * will validity, etc. are NOT modeled here; see the plan's open risks).
 * Kept as one small table rather than scattered `if ($jurisdiction === ...)`
 * branches inside generators, per the "jurisdiction variance = template
 * variance, never code variance" principle — this table only ever
 * *warns*, never blocks or alters generated wording.
 */
class JurisdictionRules
{
    /**
     * Minimum age a principal/testator is ordinarily expected to be for
     * this document category in this jurisdiction (Succession Act 2006
     * (NSW) s5 and equivalent uniform succession legislation for wills;
     * Powers of Attorney Act 2003 (NSW) / Guardianship Act 1987 (NSW) for
     * the other two) — court exceptions exist in every jurisdiction, which
     * is exactly why this is surfaced as a soft warning, never a hard block.
     */
    private const MINIMUM_AGES = [
        'NSW' => ['will' => 18, 'power_of_attorney' => 18],
        'VIC' => ['will' => 18, 'power_of_attorney' => 18],
        'QLD' => ['will' => 18, 'power_of_attorney' => 18],
        'WA' => ['will' => 18, 'power_of_attorney' => 18],
        'SA' => ['will' => 18, 'power_of_attorney' => 18],
        'TAS' => ['will' => 18, 'power_of_attorney' => 18],
        'ACT' => ['will' => 18, 'power_of_attorney' => 18],
        'NT' => ['will' => 18, 'power_of_attorney' => 18],
    ];

    /**
     * Ordinary minimum number of witnesses expected at execution — Succession
     * Act 2006 (NSW) s6 (2 witnesses for a will); Powers of Attorney Act 2003
     * (NSW) / Guardianship Act 1987 (NSW) (1 prescribed/authorised witness
     * for an enduring instrument). This app has no visibility into WHO
     * actually witnesses a document (signing happens on paper, outside this
     * app — see DocumentRequestWitness, which only records names for the
     * firm's own file after the fact) — this number is authoring/checklist
     * guidance only, never enforced as a hard rule, and specific witness
     * eligibility (not a beneficiary, not the attorney, etc.) is a
     * solicitor's call, not this table's.
     */
    private const MINIMUM_WITNESSES = [
        'NSW' => ['will' => 2, 'power_of_attorney' => 1, 'enduring_guardianship' => 1],
        'VIC' => ['will' => 2, 'power_of_attorney' => 1, 'enduring_guardianship' => 1],
        'QLD' => ['will' => 2, 'power_of_attorney' => 1, 'enduring_guardianship' => 1],
        'WA' => ['will' => 2, 'power_of_attorney' => 1, 'enduring_guardianship' => 1],
        'SA' => ['will' => 2, 'power_of_attorney' => 1, 'enduring_guardianship' => 1],
        'TAS' => ['will' => 2, 'power_of_attorney' => 1, 'enduring_guardianship' => 1],
        'ACT' => ['will' => 2, 'power_of_attorney' => 1, 'enduring_guardianship' => 1],
        'NT' => ['will' => 2, 'power_of_attorney' => 1, 'enduring_guardianship' => 1],
    ];

    public static function minimumAge(string $jurisdiction, ?string $category): ?int
    {
        return self::MINIMUM_AGES[$jurisdiction][$category] ?? null;
    }

    public static function minimumWitnesses(string $jurisdiction, ?string $category): ?int
    {
        return self::MINIMUM_WITNESSES[$jurisdiction][$category] ?? null;
    }
}
