<?php

namespace Tests\Unit;

use App\Services\AnswerContextBuilder;
use App\Services\GrammarResolver;
use Tests\TestCase;

class AnswerContextBuilderTest extends TestCase
{
    private function builder(): AnswerContextBuilder
    {
        return new AnswerContextBuilder(new GrammarResolver);
    }

    public function test_real_boolean_values_pass_through_unchanged(): void
    {
        $result = $this->builder()->build(['is_enduring' => true, 'attorneys_act_jointly' => false]);

        $this->assertTrue($result['flags']['is_enduring']);
        $this->assertFalse($result['flags']['attorneys_act_jointly']);
    }

    public function test_non_boolean_fields_are_filled_truthiness(): void
    {
        $result = $this->builder()->build([
            'alternate_executor_name' => 'Bob Smith',
            'blank_text' => '',
            'null_field' => null,
            'zero_number' => 0,
            'zero_string' => '0',
        ]);

        $this->assertTrue($result['flags']['alternate_executor_name']);
        $this->assertFalse($result['flags']['blank_text']);
        $this->assertFalse($result['flags']['null_field']);
        $this->assertTrue($result['flags']['zero_number'], '0 is a real answer, not blank.');
        $this->assertTrue($result['flags']['zero_string'], "'0' is a real answer, not blank.");
    }

    public function test_declared_field_missing_from_answers_backfills_to_a_false_flag_not_a_throw(): void
    {
        $result = $this->builder()->build([], ['attorneys_act_jointly']);

        $this->assertArrayHasKey('attorneys_act_jointly', $result['flags']);
        $this->assertFalse($result['flags']['attorneys_act_jointly']);
        $this->assertArrayHasKey('attorneys_act_jointly', $result['answers']);
        $this->assertNull($result['answers']['attorneys_act_jointly']);
    }

    public function test_gender_suffixed_field_gets_four_pronoun_siblings(): void
    {
        $result = $this->builder()->build(['executor_gender' => 'female']);

        $this->assertSame('she', $result['answers']['executor_pronoun_subject']);
        $this->assertSame('her', $result['answers']['executor_pronoun_object']);
        $this->assertSame('her', $result['answers']['executor_pronoun_possessive']);
        $this->assertSame('herself', $result['answers']['executor_pronoun_reflexive']);
        // Original key untouched.
        $this->assertSame('female', $result['answers']['executor_gender']);
    }

    public function test_blank_or_missing_gender_produces_no_pronoun_siblings(): void
    {
        $blank = $this->builder()->build(['executor_gender' => '']);
        $this->assertArrayNotHasKey('executor_pronoun_subject', $blank['answers']);

        $missing = $this->builder()->build([], ['executor_gender']);
        $this->assertArrayNotHasKey('executor_pronoun_subject', $missing['answers']);
    }

    public function test_non_string_gender_suffixed_field_is_skipped_not_coerced(): void
    {
        $result = $this->builder()->build(['confirmed_gender' => true]);

        $this->assertArrayNotHasKey('confirmed_pronoun_subject', $result['answers']);
    }

    public function test_existing_real_field_is_never_clobbered_by_a_synthetic_pronoun_key(): void
    {
        $result = $this->builder()->build([
            'executor_gender' => 'male',
            'executor_pronoun_subject' => 'a real declared value',
        ]);

        $this->assertSame('a real declared value', $result['answers']['executor_pronoun_subject']);
    }

    public function test_derived_answer_field_names_expands_gender_suffixed_names_only(): void
    {
        $derived = AnswerContextBuilder::derivedAnswerFieldNames(['executor_name', 'executor_gender']);

        $this->assertContains('executor_name', $derived);
        $this->assertContains('executor_gender', $derived);
        $this->assertContains('executor_pronoun_subject', $derived);
        $this->assertContains('executor_pronoun_object', $derived);
        $this->assertContains('executor_pronoun_possessive', $derived);
        $this->assertContains('executor_pronoun_reflexive', $derived);
        $this->assertCount(6, $derived);
    }
}
