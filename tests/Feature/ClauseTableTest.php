<?php

namespace Tests\Feature;

use App\Exceptions\ClauseMarkerException;
use App\Services\Clause\ClauseMarkerParser;
use App\Services\Clause\ClauseTemplateExpander;
use App\Services\DocxBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\ListItem as ListItemStyle;
use Tests\TestCase;

class ClauseTableTest extends TestCase
{
    use RefreshDatabase;

    private function saveDocx(PhpWord $phpWord): string
    {
        $path = tempnam(sys_get_temp_dir(), 'clause_table_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return $path;
    }

    public function test_table_round_trips_with_gridspan_border_and_placeholder_substitution(): void
    {
        $sourcePhpWord = new PhpWord;
        $section = $sourcePhpWord->addSection();
        $section->addText('[[CLAUSE:rates]]');
        $table = $section->addTable();

        $table->addRow();
        $table->addCell(2000)->addText('Role');
        $table->addCell(2000)->addText('Rate');

        $table->addRow();
        $table->addCell(2000)->addText('Principal');
        $table->addCell(2000)->addText('{{answers.principal_rate}}', null, ['alignment' => Jc::RIGHT]);

        $table->addRow();
        $cell = $table->addCell(4000, ['gridSpan' => 2, 'borderSize' => 6, 'borderColor' => '000000']);
        $cell->addText('Total estimate: {{answers.total}}');

        $section->addText('[[/CLAUSE]]');

        $sourcePath = $this->saveDocx($sourcePhpWord);
        $outputPath = tempnam(sys_get_temp_dir(), 'clause_table_out_').'.docx';

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($sourcePath);
            $this->assertCount(1, $clauses['rates']);
            $this->assertSame('table', $clauses['rates'][0]->kind);

            $rendered = app(ClauseTemplateExpander::class)->expand($clauses['rates'], [
                'answers' => ['principal_rate' => '$550.00', 'total' => '$1,200.00'],
            ]);

            app(DocxBuilder::class)->buildAndSave('Rates', [['type' => 'raw', 'elements' => $rendered]], $outputPath);

            $reloaded = IOFactory::load($outputPath, 'Word2007');
            $tables = [];
            foreach ($reloaded->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof Table) {
                        $tables[] = $element;
                    }
                }
            }
            $this->assertCount(1, $tables);

            $rows = $tables[0]->getRows();
            $this->assertCount(3, $rows);

            // Header row
            $headerCells = $rows[0]->getCells();
            $this->assertSame('Role', trim((string) $headerCells[0]->getElements()[0]->getText()));

            // Placeholder-substituted rate cell
            $rateCells = $rows[1]->getCells();
            $rateText = $rateCells[1]->getElements()[0]->getText();
            $this->assertStringContainsString('$550.00', is_string($rateText) ? $rateText : (string) $rateText);

            // Merged/bordered totals cell
            $totalRow = $rows[2]->getCells();
            $this->assertCount(1, $totalRow, 'GridSpan should merge the two source cells into one on reload.');
            $this->assertSame(2, $totalRow[0]->getStyle()->getGridSpan());
            $this->assertSame(6.0, (float) $totalRow[0]->getStyle()->getBorderTopSize());
            $totalText = $totalRow[0]->getElements()[0]->getText();
            $this->assertStringContainsString('$1,200.00', is_string($totalText) ? $totalText : (string) $totalText);
        } finally {
            @unlink($sourcePath);
            @unlink($outputPath);
        }
    }

    public function test_multilevel_list_split_across_table_cells_numbers_sequentially(): void
    {
        $sourcePhpWord = new PhpWord;
        $section = $sourcePhpWord->addSection();
        $section->addText('[[CLAUSE:rates]]');
        $table = $section->addTable();

        foreach (['Item one', 'Item two', 'Item three'] as $text) {
            $table->addRow();
            $table->addCell(2000);
            $table->addCell(4000)->addListItem($text, 0, null, ListItemStyle::TYPE_NUMBER);
        }

        $section->addText('[[/CLAUSE]]');

        $sourcePath = $this->saveDocx($sourcePhpWord);
        $outputPath = tempnam(sys_get_temp_dir(), 'clause_table_list_').'.docx';

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($sourcePath);

            app(DocxBuilder::class)->buildAndSave('List', [['type' => 'raw', 'elements' => $clauses['rates']]], $outputPath);

            $reloaded = IOFactory::load($outputPath, 'Word2007');
            $listItems = [];
            foreach ($reloaded->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (! $element instanceof Table) {
                        continue;
                    }
                    foreach ($element->getRows() as $row) {
                        foreach ($row->getCells() as $cell) {
                            foreach ($cell->getElements() as $cellElement) {
                                if ($cellElement instanceof ListItemRun) {
                                    $listItems[] = $cellElement;
                                }
                            }
                        }
                    }
                }
            }

            $this->assertCount(3, $listItems);
            $numIds = array_map(fn ($item) => $item->getStyle()->getNumId(), $listItems);
            $this->assertSame(
                [$numIds[0], $numIds[0], $numIds[0]],
                $numIds,
                'List items split across table cells must share one numId so Word renders "1. 2. 3." sequentially.'
            );
        } finally {
            @unlink($sourcePath);
            @unlink($outputPath);
        }
    }

    public function test_control_marker_inside_table_cell_throws(): void
    {
        $sourcePhpWord = new PhpWord;
        $section = $sourcePhpWord->addSection();
        $section->addText('[[CLAUSE:rates]]');
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('[[IF:some_flag]]');
        $section->addText('[[/CLAUSE]]');

        $sourcePath = $this->saveDocx($sourcePhpWord);

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('table cell');
            app(ClauseMarkerParser::class)->parse($sourcePath);
        } finally {
            @unlink($sourcePath);
        }
    }

    public function test_nested_table_throws(): void
    {
        $sourcePhpWord = new PhpWord;
        $section = $sourcePhpWord->addSection();
        $section->addText('[[CLAUSE:rates]]');
        $table = $section->addTable();
        $table->addRow();
        $outerCell = $table->addCell(2000);
        $outerCell->addTable()->addRow();
        $section->addText('[[/CLAUSE]]');

        $sourcePath = $this->saveDocx($sourcePhpWord);

        try {
            $this->expectException(ClauseMarkerException::class);
            $this->expectExceptionMessage('nested inside another table');
            app(ClauseMarkerParser::class)->parse($sourcePath);
        } finally {
            @unlink($sourcePath);
        }
    }
}
