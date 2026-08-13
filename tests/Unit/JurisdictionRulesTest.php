<?php

namespace Tests\Unit;

use App\Support\JurisdictionRules;
use Tests\TestCase;

class JurisdictionRulesTest extends TestCase
{
    public function test_minimum_age_for_a_will_in_nsw(): void
    {
        $this->assertSame(18, JurisdictionRules::minimumAge('NSW', 'will'));
    }

    public function test_minimum_age_for_power_of_attorney(): void
    {
        $this->assertSame(18, JurisdictionRules::minimumAge('NSW', 'power_of_attorney'));
    }

    public function test_unknown_category_returns_null_rather_than_a_default(): void
    {
        $this->assertNull(JurisdictionRules::minimumAge('NSW', 'enduring_guardianship'));
        $this->assertNull(JurisdictionRules::minimumAge('NSW', null));
    }

    public function test_unknown_jurisdiction_returns_null(): void
    {
        $this->assertNull(JurisdictionRules::minimumAge('XX', 'will'));
    }
}
