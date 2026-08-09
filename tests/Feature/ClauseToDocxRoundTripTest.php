<?php

namespace Tests\Feature;

use App\Services\Clause\ClauseMarkerParser;
use App\Services\DocxBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\NumberFormat;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\ListItem as ListItemStyle;
use Tests\TestCase;

class ClauseToDocxRoundTripTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The single most important regression test for the whole clause-library
     * feature: PHPWord silently discards a Numbering object passed to
     * addListItemRun() unless it's re-registered under a fresh name in the
     * TARGET document (see DocxBuilder::numberingNameFor() docblock). Get the
     * dedup-by-object-identity wrong and 3 numbered items that should read
     * "1. 2. 3." instead all restart at "1." — this proves that doesn't happen.
     */
    public function test_numbered_list_items_from_a_clause_stay_sequentially_numbered_after_reemission(): void
    {
        // Source precedent: 3 numbered list items sharing one legacy TYPE_NUMBER
        // style — confirmed empirically that PHPWord's writer groups these
        // under one shared numbering definition, matching how a real
        // Word-authored numbered list behaves.
        $sourcePhpWord = new PhpWord();
        $section = $sourcePhpWord->addSection();
        $section->addText('[[CLAUSE:powers]]');
        $section->addListItem('Power one', 0, null, ListItemStyle::TYPE_NUMBER);
        $section->addListItem('Power two', 0, null, ListItemStyle::TYPE_NUMBER);
        $section->addListItem('Power three', 0, null, ListItemStyle::TYPE_NUMBER);
        $section->addText('[[/CLAUSE]]');

        $sourcePath = tempnam(sys_get_temp_dir(), 'clause_source_') . '.docx';
        IOFactory::createWriter($sourcePhpWord, 'Word2007')->save($sourcePath);

        $outputPath = tempnam(sys_get_temp_dir(), 'clause_output_') . '.docx';

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($sourcePath);
            $this->assertCount(3, $clauses['powers']);

            app(DocxBuilder::class)->buildAndSave(
                'Test Document',
                [['type' => 'raw', 'elements' => $clauses['powers']]],
                $outputPath
            );

            $reloaded = IOFactory::load($outputPath, 'Word2007');
            $listItems = [];
            foreach ($reloaded->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof ListItemRun) {
                        $listItems[] = $element;
                    }
                }
            }

            $this->assertCount(3, $listItems, 'Expected all 3 re-emitted list items to survive the round trip.');

            $numIds = array_map(fn ($item) => $item->getStyle()->getNumId(), $listItems);
            $this->assertSame(
                [$numIds[0], $numIds[0], $numIds[0]],
                $numIds,
                'All 3 items must share one numId so Word renders "1. 2. 3." — not restart at "1." for each.'
            );

            $this->assertSame(['Power one', 'Power two', 'Power three'], array_map(fn ($item) => $item->getText(), $listItems));
        } finally {
            @unlink($sourcePath);
            @unlink($outputPath);
        }
    }

    /**
     * A genuinely nested clause (a./b. sub-items, i./ii. sub-sub-items under
     * b.) — matches the "5. I authorise my executor/s to..." structure from
     * the original worked example. Depth-threading and full-object numbering
     * re-registration (built for the flat case above) turned out to already
     * generalize to multi-level lists with zero extra code — this formalizes
     * that discovery as a permanent regression test rather than leaving it
     * as a one-off manual check.
     */
    public function test_nested_multilevel_clause_preserves_depth_and_per_level_format(): void
    {
        $sourcePhpWord = new PhpWord();
        Style::addNumberingStyle('exec_powers_levels', [
            'type' => 'multilevel',
            'levels' => [
                ['format' => NumberFormat::LOWER_LETTER, 'text' => '%1.', 'left' => 720, 'hanging' => 360, 'tabPos' => 720],
                ['format' => NumberFormat::LOWER_ROMAN, 'text' => '%2.', 'left' => 1440, 'hanging' => 360, 'tabPos' => 1440],
            ],
        ]);

        $section = $sourcePhpWord->addSection();
        $section->addText('[[CLAUSE:executor_powers]]');
        $section->addListItemRun(0, 'exec_powers_levels')->addText('exercise any powers given to them by law');
        $section->addListItemRun(0, 'exec_powers_levels')->addText('exercise the powers of an executor for sale, and to:');
        $section->addListItemRun(1, 'exec_powers_levels')->addText('postpone sale without being liable for any loss');
        $section->addListItemRun(1, 'exec_powers_levels')->addText('sell by public auction or private sale');
        $section->addText('[[/CLAUSE]]');

        $sourcePath = tempnam(sys_get_temp_dir(), 'nested_clause_source_') . '.docx';
        IOFactory::createWriter($sourcePhpWord, 'Word2007')->save($sourcePath);
        $outputPath = tempnam(sys_get_temp_dir(), 'nested_clause_output_') . '.docx';

        try {
            $clauses = app(ClauseMarkerParser::class)->parse($sourcePath);
            $this->assertCount(4, $clauses['executor_powers']);
            $this->assertSame([0, 0, 1, 1], array_map(fn ($el) => $el->depth, $clauses['executor_powers']));

            app(DocxBuilder::class)->buildAndSave(
                'Test Document',
                [['type' => 'raw', 'elements' => $clauses['executor_powers']]],
                $outputPath
            );

            $reloaded = IOFactory::load($outputPath, 'Word2007');
            $listItems = [];
            foreach ($reloaded->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof ListItemRun) {
                        $listItems[] = $element;
                    }
                }
            }

            $this->assertCount(4, $listItems);
            // PHPWord's reader hands back w:ilvl as a numeric string, not an int
            // (an XML-attribute-typing artifact, not a real depth mismatch) —
            // cast for comparison since the VALUE, not the PHP type, is what matters.
            $this->assertSame([0, 0, 1, 1], array_map(fn ($item) => (int) $item->getDepth(), $listItems), 'Depth (a./b. vs i./ii.) must survive the round trip.');

            $numIds = array_map(fn ($item) => $item->getStyle()->getNumId(), $listItems);
            $this->assertSame([$numIds[0], $numIds[0], $numIds[0], $numIds[0]], $numIds, 'All 4 items belong to one list.');

            foreach ($listItems as $item) {
                $numObj = Style::getStyle($item->getStyle()->getNumStyle());
                $formats = array_map(fn ($level) => $level->getFormat(), $numObj->getLevels());
                $this->assertSame(
                    [NumberFormat::LOWER_LETTER, NumberFormat::LOWER_ROMAN],
                    $formats,
                    'Both the a./b. and i./ii. level formats must survive intact, not just the numId.'
                );
            }
        } finally {
            @unlink($sourcePath);
            @unlink($outputPath);
        }
    }
}
