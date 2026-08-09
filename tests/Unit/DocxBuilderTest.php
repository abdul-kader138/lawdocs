<?php

namespace Tests\Unit;

use App\Services\DocxBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use Tests\TestCase;

class DocxBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_a_valid_docx_with_real_heading_styles_and_lists(): void
    {
        $blocks = [
            ['type' => 'heading', 'level' => 2, 'text' => '1. Executor'],
            ['type' => 'paragraph', 'text' => 'I appoint Jane Doe to be my Executor.'],
            ['type' => 'heading', 'level' => 2, 'text' => '2. Beneficiaries'],
            ['type' => 'list_item', 'list_type' => 'bullet', 'text' => 'My spouse - 50%'],
            ['type' => 'list_item', 'list_type' => 'bullet', 'text' => 'My son - 50%'],
            ['type' => 'page_break', 'text' => ''],
            ['type' => 'paragraph', 'text' => 'Signed on the date below.', 'bold' => true],
        ];

        $path = tempnam(sys_get_temp_dir(), 'docxbuilder_test_') . '.docx';

        try {
            app(DocxBuilder::class)->buildAndSave('Last Will and Testament', $blocks, $path);

            $this->assertFileExists($path);

            // Round-trip it back through PHPWord to prove it's a genuinely
            // valid, readable .docx — not just "a file got written somewhere."
            $reloaded = IOFactory::load($path, 'Word2007');
            $sections = $reloaded->getSections();
            $this->assertCount(1, $sections);

            $elements = $sections[0]->getElements();

            $headings = array_values(array_filter($elements, fn ($e) => $e instanceof Title));
            // Document title (depth 1) + the two "heading" blocks (depth 2) = 3.
            $this->assertCount(3, $headings);
            $this->assertSame('Last Will and Testament', $headings[0]->getText());
            $this->assertSame('1. Executor', $headings[1]->getText());
            $this->assertSame('2. Beneficiaries', $headings[2]->getText());

            $listItems = array_values(array_filter($elements, fn ($e) => $e instanceof ListItemRun));
            $this->assertCount(2, $listItems);
            $this->assertStringContainsString('My spouse - 50%', $listItems[0]->getText());

            $pageBreaks = array_filter($elements, fn ($e) => $e instanceof PageBreak);
            $this->assertCount(1, $pageBreaks);
        } finally {
            @unlink($path);
        }
    }
}
