<?php

namespace Tests\Unit;

use App\Exceptions\ClauseMarkerException;
use App\Services\Clause\ClauseMarkerParser;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style;
use Tests\TestCase;

class ClauseMarkerParserTest extends TestCase
{
    private function buildDocx(callable $build): string
    {
        $phpWord = new PhpWord();
        $build($phpWord->addSection());
        $path = tempnam(sys_get_temp_dir(), 'clause_parser_test_') . '.docx';
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

    public function test_table_inside_a_clause_throws_a_documented_gap_exception(): void
    {
        $path = $this->buildDocx(function ($section) {
            $section->addText('[[CLAUSE:has_table]]');
            $table = $section->addTable();
            $table->addRow();
            $table->addCell(2000)->addText('cell');
            $section->addText('[[/CLAUSE]]');
        });

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('not supported inside clause markers yet');
            app(ClauseMarkerParser::class)->parse($path);
        } finally {
            @unlink($path);
        }
    }
}
