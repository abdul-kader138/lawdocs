<?php

namespace Tests\Unit;

use App\Services\DocxBuilder;
use App\Models\Precedent;
use App\Services\Clause\ClauseElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\Element\PreserveText;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Paragraph;
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

    public function test_formatting_overrides_apply_custom_font(): void
    {
        $phpWord = app(DocxBuilder::class)->build('Title', [], [
            'font_family' => 'Arial',
            'font_size' => 14,
        ]);

        $this->assertSame('Arial', $phpWord->getDefaultFontName());
        $this->assertEquals(14, $phpWord->getDefaultFontSize());
    }

    /**
     * Regression test: DocxBuilder must read formatting overrides with ??,
     * never ?:/empty() — heading_bold=false and heading_size_step=0 are both
     * meaningful, falsy override values that those operators would silently
     * discard back to the global default (bold=true, step=2).
     */
    public function test_falsy_heading_overrides_actually_take_effect(): void
    {
        app(DocxBuilder::class)->build('Title', [
            ['type' => 'heading', 'level' => 2, 'text' => 'Section'],
        ], [
            'font_size' => 14,
            'heading_bold' => false,
            'heading_size_step' => 0,
        ]);

        $headingStyle = Style::getStyle('Heading_2');

        $this->assertNotNull($headingStyle);
        $this->assertFalse($headingStyle->isBold());
        // step=0 => every heading level renders at the plain font size, not
        // stepped up per level.
        $this->assertEquals(14, $headingStyle->getSize());
    }

    public function test_professional_page_and_paragraph_formatting_is_applied(): void
    {
        $phpWord = app(DocxBuilder::class)->build('Agreement', [
            ['type' => 'paragraph', 'text' => 'Professional body text.'],
        ], [
            'body_alignment' => 'both',
            'line_spacing' => 1.5,
            'paragraph_space_after' => 6,
            'first_line_indent' => 12.7,
            'left_indent' => 5,
            'right_indent' => 5,
            'margin_left' => 38.1,
            'footer_text' => 'Confidential',
            'page_numbers' => true,
        ]);

        $section = $phpWord->getSections()[0];
        $this->assertEqualsWithDelta(2160, $section->getStyle()->getMarginLeft(), 1);

        $body = collect($section->getElements())->first(fn ($element) => $element instanceof Text);
        $this->assertSame('both', $body->getParagraphStyle()->getAlignment());
        $this->assertEquals(1.5, $body->getParagraphStyle()->getLineHeight());
        $this->assertEquals(120, $body->getParagraphStyle()->getSpaceAfter());
        $this->assertEqualsWithDelta(720, $body->getParagraphStyle()->getIndentFirstLine(), 1);

        $footerElements = collect($section->getFooters())->first()->getElements();
        $this->assertTrue(collect($footerElements)->contains(fn ($element) => $element instanceof PreserveText));
    }

    public function test_formatting_profile_provides_defaults_that_individual_fields_can_override(): void
    {
        $precedent = new Precedent([
            'formatting' => [
                'profile' => 'legal_traditional',
                'font_size' => 11,
            ],
        ]);

        $formatting = $precedent->formattingConfig();

        $this->assertSame('Times New Roman', $formatting['font_family']);
        $this->assertSame(11, $formatting['font_size']);
        $this->assertSame('both', $formatting['body_alignment']);
        $this->assertTrue($formatting['page_numbers']);
        $this->assertTrue($formatting['apply_paragraph_style_to_clauses']);
    }

    public function test_uniform_paragraph_style_applies_to_uploaded_clause_paragraphs(): void
    {
        $sourceStyle = new Paragraph(['alignment' => 'center', 'spaceAfter' => 0]);
        $clause = ClauseElement::textRun(
            [['text' => 'Preserved bold clause', 'fontStyle' => ['bold' => true]]],
            $sourceStyle,
        );

        $phpWord = app(DocxBuilder::class)->build('Agreement', [
            ['type' => 'raw', 'elements' => [$clause]],
        ], [
            'body_alignment' => 'both',
            'line_spacing' => 1.5,
            'paragraph_space_after' => 6,
            'first_line_indent' => 10,
            'apply_paragraph_style_to_clauses' => true,
        ]);

        $textRun = collect($phpWord->getSections()[0]->getElements())
            ->first(fn ($element) => $element instanceof \PhpOffice\PhpWord\Element\TextRun);

        $this->assertSame('both', $textRun->getParagraphStyle()->getAlignment());
        $this->assertEquals(1.5, $textRun->getParagraphStyle()->getLineHeight());
        $this->assertEquals(120, $textRun->getParagraphStyle()->getSpaceAfter());
        $this->assertEqualsWithDelta(567, $textRun->getParagraphStyle()->getIndentFirstLine(), 1);
        $this->assertTrue($textRun->getElements()[0]->getFontStyle()->isBold());
    }
}
