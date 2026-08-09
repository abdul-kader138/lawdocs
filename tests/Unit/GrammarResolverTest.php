<?php

namespace Tests\Unit;

use App\Services\GrammarResolver;
use Tests\TestCase;

class GrammarResolverTest extends TestCase
{
    public function test_male_pronouns_for_all_roles(): void
    {
        $r = new GrammarResolver();

        $this->assertSame('he', $r->pronoun('male', 'subject'));
        $this->assertSame('him', $r->pronoun('male', 'object'));
        $this->assertSame('his', $r->pronoun('male', 'possessive'));
        $this->assertSame('his', $r->pronoun('male', 'possessive_pronoun'));
        $this->assertSame('himself', $r->pronoun('male', 'reflexive'));
    }

    public function test_female_pronouns_for_all_roles(): void
    {
        $r = new GrammarResolver();

        $this->assertSame('she', $r->pronoun('female', 'subject'));
        $this->assertSame('her', $r->pronoun('female', 'object'));
        $this->assertSame('her', $r->pronoun('female', 'possessive'));
        $this->assertSame('hers', $r->pronoun('female', 'possessive_pronoun'));
        $this->assertSame('herself', $r->pronoun('female', 'reflexive'));
    }

    public function test_unrecognized_or_blank_value_falls_back_to_entity_pronouns(): void
    {
        $r = new GrammarResolver();

        $this->assertSame('it', $r->pronoun('company', 'subject'));
        $this->assertSame('its', $r->pronoun('', 'possessive'));
    }

    public function test_capitalize_option_produces_sentence_initial_casing(): void
    {
        $r = new GrammarResolver();

        $this->assertSame('He', $r->pronoun('male', 'subject', capitalize: true));
        $this->assertSame('he', $r->pronoun('male', 'subject', capitalize: false));
    }

    public function test_value_matching_is_case_and_whitespace_insensitive(): void
    {
        $r = new GrammarResolver();

        $this->assertSame('he', $r->pronoun(' MALE ', 'subject'));
        $this->assertSame('he', $r->pronoun('Male', 'subject'));
    }

    public function test_unknown_role_throws(): void
    {
        $r = new GrammarResolver();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown grammatical role');

        $r->pronoun('male', 'nonexistent_role');
    }

    public function test_unknown_category_throws(): void
    {
        $r = new GrammarResolver();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown grammar category');

        $r->resolve('nonexistent_category', 'male', 'subject');
    }
}
