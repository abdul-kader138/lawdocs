<?php

namespace App\Support;

/**
 * Whitelist, not a DB enum, so a new state is addable without a migration.
 * Jurisdiction variance is meant to live entirely in template content (see
 * Precedent::jurisdiction) — this class only exists to give admins a
 * consistent set of options and a canonical label.
 */
class AustralianJurisdictions
{
    public const OPTIONS = [
        'NSW' => 'New South Wales',
        'VIC' => 'Victoria',
        'QLD' => 'Queensland',
        'WA' => 'Western Australia',
        'SA' => 'South Australia',
        'TAS' => 'Tasmania',
        'ACT' => 'Australian Capital Territory',
        'NT' => 'Northern Territory',
    ];
}
