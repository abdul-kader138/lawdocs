<?php

namespace Tests\Unit;

use App\Rules\PartySharesSumTo100;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PartySharesSumTo100Test extends TestCase
{
    private function validate(array $rows): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make(
            ['beneficiaries' => $rows],
            ['beneficiaries' => [new PartySharesSumTo100('Beneficiaries', 'share')]],
        );
    }

    public function test_passes_when_shares_sum_to_exactly_100(): void
    {
        $validator = $this->validate([
            ['name' => 'A', 'share' => 50],
            ['name' => 'B', 'share' => 50],
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_fails_when_shares_sum_under_100(): void
    {
        $validator = $this->validate([
            ['name' => 'A', 'share' => 40],
            ['name' => 'B', 'share' => 50],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('must sum to 100', $validator->errors()->first('beneficiaries'));
    }

    public function test_fails_when_shares_sum_over_100(): void
    {
        $validator = $this->validate([
            ['name' => 'A', 'share' => 60],
            ['name' => 'B', 'share' => 60],
        ]);

        $this->assertTrue($validator->fails());
    }

    public function test_passes_on_empty_rows_leaving_emptiness_to_other_rules(): void
    {
        $validator = $this->validate([]);

        $this->assertFalse($validator->fails());
    }

    public function test_tolerates_negligible_floating_point_drift(): void
    {
        $validator = $this->validate([
            ['name' => 'A', 'share' => 33.34],
            ['name' => 'B', 'share' => 33.33],
            ['name' => 'C', 'share' => 33.33],
        ]);

        $this->assertFalse($validator->fails());
    }

    public function test_missing_share_key_treated_as_zero(): void
    {
        $validator = $this->validate([
            ['name' => 'A'],
            ['name' => 'B', 'share' => 100],
        ]);

        $this->assertFalse($validator->fails());
    }
}
