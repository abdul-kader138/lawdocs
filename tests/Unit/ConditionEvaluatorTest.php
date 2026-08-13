<?php

namespace Tests\Unit;

use App\Exceptions\ClauseTemplateException;
use App\Services\Clause\ConditionEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    private function evaluator(): ConditionEvaluator
    {
        return new ConditionEvaluator;
    }

    public function test_bare_true_and_false_flag(): void
    {
        $e = $this->evaluator();

        $this->assertTrue($e->evaluate('flag', ['flag' => true]));
        $this->assertFalse($e->evaluate('flag', ['flag' => false]));
    }

    public function test_dotted_identifier_for_repeat_scope_fields(): void
    {
        $e = $this->evaluator();

        $this->assertTrue($e->evaluate('beneficiary.per_stirpes', ['beneficiary.per_stirpes' => true]));
    }

    #[DataProvider('truthTableProvider')]
    public function test_and_or_not_truth_table(string $condition, array $flags, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluator()->evaluate($condition, $flags));
    }

    public static function truthTableProvider(): array
    {
        return [
            'AND both true' => ['a AND b', ['a' => true, 'b' => true], true],
            'AND one false' => ['a AND b', ['a' => true, 'b' => false], false],
            'OR one true' => ['a OR b', ['a' => false, 'b' => true], true],
            'OR both false' => ['a OR b', ['a' => false, 'b' => false], false],
            'NOT true' => ['NOT a', ['a' => true], false],
            'NOT false' => ['NOT a', ['a' => false], true],
            'double NOT' => ['NOT NOT a', ['a' => true], true],
            'parens override precedence' => ['a AND (b OR c)', ['a' => true, 'b' => false, 'c' => true], true],
            'AND binds tighter than OR' => ['a OR b AND c', ['a' => false, 'b' => true, 'c' => false], false],
            'NOT binds tighter than AND' => ['NOT a AND b', ['a' => false, 'b' => true], true],
            'nested parens' => ['((a))', ['a' => true], true],
            'not with parens' => ['NOT (a OR b)', ['a' => false, 'b' => false], true],
        ];
    }

    public function test_unknown_flag_throws(): void
    {
        $this->expectException(ClauseTemplateException::class);
        $this->expectExceptionMessage('Unknown flag');

        $this->evaluator()->evaluate('nonexistent_flag', []);
    }

    public function test_empty_condition_throws(): void
    {
        $this->expectException(ClauseTemplateException::class);

        $this->evaluator()->evaluate('', ['a' => true]);
    }

    public function test_whitespace_only_condition_throws(): void
    {
        $this->expectException(ClauseTemplateException::class);

        $this->evaluator()->evaluate('   ', ['a' => true]);
    }

    public function test_unbalanced_opening_paren_throws(): void
    {
        $this->expectException(ClauseTemplateException::class);

        $this->evaluator()->evaluate('(a AND b', ['a' => true, 'b' => true]);
    }

    public function test_unbalanced_closing_paren_throws(): void
    {
        $this->expectException(ClauseTemplateException::class);

        $this->evaluator()->evaluate('a AND b)', ['a' => true, 'b' => true]);
    }

    public function test_disallowed_characters_throw_instead_of_being_silently_ignored(): void
    {
        $this->expectException(ClauseTemplateException::class);

        $this->evaluator()->evaluate('a == 1', ['a' => true]);
    }

    public function test_arithmetic_is_rejected(): void
    {
        $this->expectException(ClauseTemplateException::class);

        $this->evaluator()->evaluate('1 + 1', []);
    }

    public function test_php_lookalike_injection_attempt_throws(): void
    {
        $this->expectException(ClauseTemplateException::class);

        $this->evaluator()->evaluate('true; system("ls")', ['true' => true]);
    }

    public function test_dangling_and_throws(): void
    {
        $this->expectException(ClauseTemplateException::class);

        $this->evaluator()->evaluate('a AND', ['a' => true]);
    }

    public function test_two_atoms_with_no_operator_between_throws(): void
    {
        $this->expectException(ClauseTemplateException::class);

        $this->evaluator()->evaluate('a b', ['a' => true, 'b' => true]);
    }

    public function test_empty_parens_throws(): void
    {
        $this->expectException(ClauseTemplateException::class);

        $this->evaluator()->evaluate('()', []);
    }
}
