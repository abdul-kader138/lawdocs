<?php

namespace Tests\Unit;

use App\Contracts\DeclaresPartyFlags;
use App\Support\PrecedentFlagResolver;
use Tests\TestCase;

class PrecedentFlagResolverTest extends TestCase
{
    private function generator(array $flags = [], array $groups = []): DeclaresPartyFlags
    {
        return new class($flags, $groups) implements DeclaresPartyFlags
        {
            public function __construct(private array $flags, private array $groups) {}

            public function availableFlags(): array
            {
                return $this->flags;
            }

            public function availableGroups(): array
            {
                return $this->groups;
            }
        };
    }

    public function test_any_type_questionnaire_field_becomes_a_valid_flag(): void
    {
        $fields = [
            ['name' => 'is_enduring', 'label' => 'Is Enduring', 'type' => 'boolean'],
            ['name' => 'alternate_executor_name', 'label' => 'Alternate Executor', 'type' => 'text'],
        ];

        $flags = PrecedentFlagResolver::availableFlags($fields, $this->generator());

        $this->assertArrayHasKey('is_enduring', $flags);
        $this->assertArrayHasKey('alternate_executor_name', $flags, 'Non-boolean fields must be valid flag targets too.');
    }

    public function test_generator_declared_flags_are_included(): void
    {
        $flags = PrecedentFlagResolver::availableFlags([], $this->generator([
            'beneficiary.per_stirpes' => 'REPEAT-scoped, not answer-derived.',
        ]));

        $this->assertArrayHasKey('beneficiary.per_stirpes', $flags);
    }

    public function test_generator_declared_flag_wins_on_key_collision(): void
    {
        $fields = [['name' => 'is_enduring', 'label' => 'Questionnaire label', 'type' => 'boolean']];
        $flags = PrecedentFlagResolver::availableFlags($fields, $this->generator([
            'is_enduring' => 'Generator-authored description.',
        ]));

        $this->assertSame('Generator-authored description.', $flags['is_enduring']);
    }

    public function test_configured_party_groups_are_available(): void
    {
        $groups = PrecedentFlagResolver::availableGroups(
            [['key' => 'attorneys', 'label' => 'Attorneys']],
            $this->generator()
        );

        $this->assertArrayHasKey('attorneys', $groups);
    }

    public function test_generator_declared_groups_are_included_alongside_configured_ones(): void
    {
        $groups = PrecedentFlagResolver::availableGroups(
            [['key' => 'attorneys', 'label' => 'Attorneys']],
            $this->generator([], ['beneficiaries' => 'Declared by the generator.'])
        );

        $this->assertArrayHasKey('attorneys', $groups);
        $this->assertArrayHasKey('beneficiaries', $groups);
    }
}
