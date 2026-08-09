<?php

namespace Tests\Unit;

use App\Services\PrecedentTextExtractor;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style;
use Tests\TestCase;

class PrecedentTextExtractorTest extends TestCase
{
    /**
     * Builds a realistic sample precedent: headings via real Word heading
     * styles (registered before addTitle(), exactly as a document authored
     * in Word itself would have them), a bulleted list, and a table — the
     * table specifically to exercise the documented best-effort/known-gap
     * path, not just the happy path.
     */
    private function makeSamplePrecedent(): string
    {
        $phpWord = new PhpWord();
        Style::addTitleStyle(1, ['bold' => true, 'size' => 16]);
        Style::addTitleStyle(2, ['bold' => true, 'size' => 13]);

        $section = $phpWord->addSection();
        $section->addTitle('LAST WILL AND TESTAMENT', 1);
        $section->addText('This is the will of JOHN SAMPLE DOE.');
        $section->addTitle('1. Executor', 2);
        $section->addText('I appoint JANE SAMPLE SMITH as Executor.');
        $section->addTitle('2. Beneficiaries', 2);
        $section->addListItem('My spouse - 50%', 0);
        $section->addListItem('My son - 50%', 0);
        $section->addTitle('3. Witness Schedule', 2);
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(4000)->addText('Witness Name');
        $table->addCell(4000)->addText('Signature');
        $table->addRow();
        $table->addCell(4000)->addText('Sample Witness One');

        $path = tempnam(sys_get_temp_dir(), 'precedent_extractor_test_') . '.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    public function test_extracts_headings_lists_and_table_content_with_structural_markers(): void
    {
        $path = $this->makeSamplePrecedent();

        try {
            $text = app(PrecedentTextExtractor::class)->extract($path);

            $this->assertStringContainsString('# LAST WILL AND TESTAMENT', $text);
            $this->assertStringContainsString('## 1. Executor', $text);
            $this->assertStringContainsString('- My spouse - 50%', $text);
            $this->assertStringContainsString('- My son - 50%', $text);
            // Table content survives on a best-effort basis (documented gap:
            // no row/column structure preserved), but must not vanish entirely.
            $this->assertStringContainsString('Witness Name', $text);
            $this->assertStringContainsString('Sample Witness One', $text);
        } finally {
            @unlink($path);
        }
    }
}
