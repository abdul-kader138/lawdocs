<?php

namespace Tests\Unit;

use App\Exceptions\ClauseMarkerException;
use App\Services\Clause\ClauseBlockNode;
use App\Services\Clause\ClauseElement;
use App\Services\Clause\ClauseMarkerParser;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style;
use Tests\TestCase;

class ClauseMarkerParserTest extends TestCase
{
    private function buildDocx(callable $build): string
    {
        $phpWord = new PhpWord;
        $build($phpWord->addSection());
        $path = tempnam(sys_get_temp_dir(), 'clause_parser_test_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    public function test_extracts_a_single_clause_with_preserved_bold_formatting(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('This is bold.', ['bold' => true]);
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($path);

            $this->assertArrayHasKey('test_clause', $clauses);
            $this->assertCount(1, $clauses['test_clause']);

            $element = $clauses['test_clause'][0];
            $this->assertSame('text_run', $element->kind);
            $this->assertSame('This is bold.', $element->runs[0]['text']);
            $this->assertInstanceOf(Style\Font::class, $element->runs[0]['fontStyle']);
            $this->assertTrue($element->runs[0]['fontStyle']->isBold());
        } finally {
            @unlink($path);
        }
    }

    public function test_extracts_multiple_clauses_keyed_by_tag_without_bleed(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:first]]');
            $section->addText('First clause content.');
            $section->addText('[[/CLAUSE]]');
            $section->addText('This is narrative text between clauses, not captured.');
            $section->addText('[[CLAUSE:second]]');
            $section->addText('Second clause content.');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($path);

            $this->assertSame(['first', 'second'], array_keys($clauses));
            $this->assertSame('First clause content.', $clauses['first'][0]->runs[0]['text']);
            $this->assertSame('Second clause content.', $clauses['second'][0]->runs[0]['text']);
        } finally {
            @unlink($path);
        }
    }

    public function test_marker_must_be_the_paragraphs_entire_text(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('See [[CLAUSE:foo]] below for details.');
            $section->addText('[[CLAUSE:real]]');
            $section->addText('Real content.');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($path);

            $this->assertArrayNotHasKey('foo', $clauses);
            $this->assertArrayHasKey('real', $clauses);
        } finally {
            @unlink($path);
        }
    }

    public function test_unclosed_clause_tag_throws(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:oops]]');
            $section->addText('Never closed.');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('never closed');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_duplicate_clause_tag_throws(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:dupe]]');
            $section->addText('First.');
            $section->addText('[[/CLAUSE]]');
            $section->addText('[[CLAUSE:dupe]]');
            $section->addText('Second.');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('more than once');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_stray_closing_marker_throws(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('no matching');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_nested_clause_markers_throws(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:outer]]');
            $section->addText('[[CLAUSE:inner]]');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('Nested clause markers');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_empty_clause_body_is_valid(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:empty]]');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($path);

            $this->assertSame([], $clauses['empty']);
        } finally {
            @unlink($path);
        }
    }

    public function test_table_inside_a_clause_is_captured_as_a_table_element(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:has_table]]');
            $table = $section->addTable();
            $table->addRow();
            $table->addCell(2000)->addText('cell');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($path);

            $this->assertCount(1, $clauses['has_table']);
            $this->assertSame('table', $clauses['has_table'][0]->kind);
        } finally {
            @unlink($path);
        }
    }

    // ── IF / REPEAT grammar ─────────────────────────────────────────────

    public function test_if_else_endif_produces_a_block_node_with_both_branches(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('[[IF:has_alternate]]');
            $section->addText('Then text.');
            $section->addText('[[ELSE]]');
            $section->addText('Else text.');
            $section->addText('[[/IF]]');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($path);
            $this->assertCount(1, $clauses['test_clause']);

            $node = $clauses['test_clause'][0];
            $this->assertInstanceOf(ClauseBlockNode::class, $node);
            $this->assertSame('if', $node->kind);
            $this->assertSame('has_alternate', $node->condition);
            $this->assertCount(1, $node->then);
            $this->assertSame('Then text.', $node->then[0]->runs[0]['text']);
            $this->assertCount(1, $node->else);
            $this->assertSame('Else text.', $node->else[0]->runs[0]['text']);
        } finally {
            @unlink($path);
        }
    }

    public function test_if_without_else_has_null_else_branch(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('[[IF:flag]]');
            $section->addText('Then text.');
            $section->addText('[[/IF]]');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($path);
            $node = $clauses['test_clause'][0];

            $this->assertNull($node->else);
        } finally {
            @unlink($path);
        }
    }

    public function test_repeat_produces_a_block_node_with_group_key_and_default_alias(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('[[REPEAT:beneficiaries]]');
            $section->addText('A row.');
            $section->addText('[[/REPEAT]]');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($path);
            $node = $clauses['test_clause'][0];

            $this->assertInstanceOf(ClauseBlockNode::class, $node);
            $this->assertSame('repeat', $node->kind);
            $this->assertSame('beneficiaries', $node->groupKey);
            $this->assertSame('beneficiaries', $node->alias);
            $this->assertCount(1, $node->children);
        } finally {
            @unlink($path);
        }
    }

    public function test_repeat_as_alias_is_captured(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('[[REPEAT:beneficiaries AS beneficiary]]');
            $section->addText('A row.');
            $section->addText('[[/REPEAT]]');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($path);
            $node = $clauses['test_clause'][0];

            $this->assertSame('beneficiaries', $node->groupKey);
            $this->assertSame('beneficiary', $node->alias);
        } finally {
            @unlink($path);
        }
    }

    public function test_repeat_nested_inside_if_and_if_nested_inside_repeat(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('[[IF:outer_flag]]');
            $section->addText('[[REPEAT:beneficiaries AS beneficiary]]');
            $section->addText('[[IF:beneficiary.per_stirpes]]');
            $section->addText('Per stirpes text.');
            $section->addText('[[/IF]]');
            $section->addText('[[/REPEAT]]');
            $section->addText('[[/IF]]');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($path);
            $outerIf = $clauses['test_clause'][0];

            $this->assertSame('if', $outerIf->kind);
            $repeat = $outerIf->then[0];
            $this->assertSame('repeat', $repeat->kind);
            $innerIf = $repeat->children[0];
            $this->assertSame('if', $innerIf->kind);
            $this->assertSame('beneficiary.per_stirpes', $innerIf->condition);
            $this->assertSame('Per stirpes text.', $innerIf->then[0]->runs[0]['text']);
        } finally {
            @unlink($path);
        }
    }

    public function test_leaf_elements_and_control_nodes_can_be_siblings(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('Before.');
            $section->addText('[[IF:flag]]');
            $section->addText('Conditional.');
            $section->addText('[[/IF]]');
            $section->addText('After.');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($path);
            $nodes = $clauses['test_clause'];

            $this->assertCount(3, $nodes);
            $this->assertInstanceOf(ClauseElement::class, $nodes[0]);
            $this->assertSame('Before.', $nodes[0]->runs[0]['text']);
            $this->assertInstanceOf(ClauseBlockNode::class, $nodes[1]);
            $this->assertInstanceOf(ClauseElement::class, $nodes[2]);
            $this->assertSame('After.', $nodes[2]->runs[0]['text']);
        } finally {
            @unlink($path);
        }
    }

    public function test_unclosed_if_throws(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('[[IF:flag]]');
            $section->addText('Never closed.');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('never closed with [[/IF]]');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_unclosed_repeat_throws(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('[[REPEAT:beneficiaries]]');
            $section->addText('Never closed.');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('never closed with [[/REPEAT]]');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_stray_else_outside_any_if_throws(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('[[ELSE]]');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('no matching [[IF:...]]');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_double_else_in_the_same_if_throws(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('[[IF:flag]]');
            $section->addText('A.');
            $section->addText('[[ELSE]]');
            $section->addText('B.');
            $section->addText('[[ELSE]]');
            $section->addText('C.');
            $section->addText('[[/IF]]');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('more than one [[ELSE]]');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_stray_endif_throws(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('[[/IF]]');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('no matching [[IF:...]]');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_stray_endrepeat_throws(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('[[/REPEAT]]');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('no matching [[REPEAT:...]]');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_mismatched_close_if_ends_repeat_throws(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('[[REPEAT:beneficiaries]]');
            $section->addText('Row.');
            $section->addText('[[/IF]]');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('no matching [[IF:...]]');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_if_or_repeat_outside_any_clause_throws(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[IF:flag]]');
            $section->addText('Text.');
            $section->addText('[[/IF]]');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('outside of any [[CLAUSE:...]] block');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_empty_condition_throws(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('[[IF:]]');
            $section->addText('Text.');
            $section->addText('[[/IF]]');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('empty condition');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_malformed_repeat_header_throws(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:test_clause]]');
            $section->addText('[[REPEAT:1invalid]]');
            $section->addText('Text.');
            $section->addText('[[/REPEAT]]');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('malformed [[REPEAT:...]] marker');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_clause_with_no_control_markers_still_yields_a_flat_element_array(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:plain]]');
            $section->addText('Just text.');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($path);

            $this->assertCount(1, $clauses['plain']);
            $this->assertInstanceOf(ClauseElement::class, $clauses['plain'][0]);
        } finally {
            @unlink($path);
        }
    }
}
