<?php

namespace Tests\Unit;

use App\Exceptions\ClauseTemplateException;
use App\Services\Clause\ClauseBlockNode;
use App\Services\Clause\ClauseElement;
use App\Services\Clause\ClauseTemplateExpander;
use Tests\TestCase;

class ClauseTemplateExpanderTest extends TestCase
{
    private function expander(): ClauseTemplateExpander
    {
        return new ClauseTemplateExpander;
    }

    private function textRun(string $text): ClauseElement
    {
        return ClauseElement::textRun([['text' => $text, 'fontStyle' => null]], null);
    }

    public function test_plain_clause_with_no_control_markers_is_unchanged_passthrough(): void
    {
        $leaf = $this->textRun('Just ordinary text.');

        $result = $this->expander()->expand([$leaf], []);

        $this->assertCount(1, $result);
        $this->assertSame($leaf, $result[0], 'A leaf with no {{...}} placeholder must be returned as the SAME object instance.');
    }

    public function test_if_true_branch_selected(): void
    {
        $node = ClauseBlockNode::ifNode('flag', [$this->textRun('Then.')], [$this->textRun('Else.')]);

        $result = $this->expander()->expand([$node], ['flags' => ['flag' => true]]);

        $this->assertCount(1, $result);
        $this->assertSame('Then.', $result[0]->runs[0]['text']);
    }

    public function test_if_false_branch_selects_else(): void
    {
        $node = ClauseBlockNode::ifNode('flag', [$this->textRun('Then.')], [$this->textRun('Else.')]);

        $result = $this->expander()->expand([$node], ['flags' => ['flag' => false]]);

        $this->assertCount(1, $result);
        $this->assertSame('Else.', $result[0]->runs[0]['text']);
    }

    public function test_if_false_with_no_else_produces_nothing(): void
    {
        $node = ClauseBlockNode::ifNode('flag', [$this->textRun('Then.')], null);

        $result = $this->expander()->expand([$node], ['flags' => ['flag' => false]]);

        $this->assertSame([], $result);
    }

    public function test_repeat_over_three_items_with_per_iteration_substitution(): void
    {
        $node = ClauseBlockNode::repeatNode(
            'beneficiaries',
            'beneficiary',
            [$this->textRun('Name: {{beneficiary.name}}.')]
        );

        $context = [
            'items' => [
                'beneficiaries' => [
                    ['name' => 'Alfred'],
                    ['name' => 'Bernadette'],
                    ['name' => 'Charlie'],
                ],
            ],
        ];

        $result = $this->expander()->expand([$node], $context);

        $this->assertCount(3, $result);
        $this->assertSame(['Name: Alfred.', 'Name: Bernadette.', 'Name: Charlie.'], array_map(fn ($el) => $el->runs[0]['text'], $result));
    }

    public function test_repeat_over_zero_items_produces_nothing(): void
    {
        $node = ClauseBlockNode::repeatNode('beneficiaries', 'beneficiary', [$this->textRun('x')]);

        $result = $this->expander()->expand([$node], ['items' => ['beneficiaries' => []]]);

        $this->assertSame([], $result);
    }

    public function test_repeat_over_missing_group_produces_nothing_rather_than_throwing(): void
    {
        $node = ClauseBlockNode::repeatNode('beneficiaries', 'beneficiary', [$this->textRun('x')]);

        $result = $this->expander()->expand([$node], ['items' => []]);

        $this->assertSame([], $result);
    }

    public function test_nested_repeat_and_if_per_stirpes_shape(): void
    {
        $ifNode = ClauseBlockNode::ifNode(
            'beneficiary.per_stirpes',
            [$this->textRun('per stirpes for {{beneficiary.name}}')],
            null
        );
        $repeatNode = ClauseBlockNode::repeatNode('beneficiaries', 'beneficiary', [
            $this->textRun('{{beneficiary.name}} gets a share.'),
            $ifNode,
        ]);

        $context = [
            'items' => [
                'beneficiaries' => [
                    ['name' => 'Alfred', 'per_stirpes' => true],
                    ['name' => 'Bernadette', 'per_stirpes' => false],
                ],
            ],
        ];

        $result = $this->expander()->expand([$repeatNode], $context);

        $this->assertCount(3, $result); // Alfred's row + per-stirpes line, Bernadette's row only
        $this->assertSame('Alfred gets a share.', $result[0]->runs[0]['text']);
        $this->assertSame('per stirpes for Alfred', $result[1]->runs[0]['text']);
        $this->assertSame('Bernadette gets a share.', $result[2]->runs[0]['text']);
    }

    public function test_repeated_leaf_with_no_placeholder_shares_one_object_instance_across_iterations(): void
    {
        // Simulates a REPEAT body containing a static list-item whose numbering
        // must stay shared (same live Numbering object) across every
        // iteration — proven at the object-identity level here, and at the
        // full docx-numId level in ClauseToDocxRoundTripTest.
        $staticLeaf = $this->textRun('Static heading text, no placeholders.');
        $node = ClauseBlockNode::repeatNode('beneficiaries', 'beneficiary', [$staticLeaf]);

        $result = $this->expander()->expand([$node], [
            'items' => ['beneficiaries' => [['name' => 'A'], ['name' => 'B'], ['name' => 'C']]],
        ]);

        $this->assertCount(3, $result);
        $this->assertSame($staticLeaf, $result[0]);
        $this->assertSame($staticLeaf, $result[1]);
        $this->assertSame($staticLeaf, $result[2]);
    }

    public function test_answers_namespace_is_always_in_scope(): void
    {
        $leaf = $this->textRun('Testator: {{answers.testator_name}}.');

        $result = $this->expander()->expand([$leaf], ['answers' => ['testator_name' => 'Ashley Dewell']]);

        $this->assertSame('Testator: Ashley Dewell.', $result[0]->runs[0]['text']);
    }

    public function test_placeholder_referencing_out_of_scope_alias_throws(): void
    {
        $leaf = $this->textRun('{{beneficiary.name}}');

        $this->expectException(ClauseTemplateException::class);
        $this->expectExceptionMessage('Unresolved placeholder');

        $this->expander()->expand([$leaf], ['answers' => []]);
    }

    public function test_placeholder_referencing_unknown_field_on_in_scope_alias_throws(): void
    {
        $node = ClauseBlockNode::repeatNode('beneficiaries', 'beneficiary', [
            $this->textRun('{{beneficiary.nonexistent_field}}'),
        ]);

        $this->expectException(ClauseTemplateException::class);

        $this->expander()->expand([$node], ['items' => ['beneficiaries' => [['name' => 'A']]]]);
    }

    public function test_condition_referencing_unknown_flag_throws(): void
    {
        $node = ClauseBlockNode::ifNode('nonexistent_flag', [$this->textRun('x')], null);

        $this->expectException(ClauseTemplateException::class);

        $this->expander()->expand([$node], []);
    }
}
