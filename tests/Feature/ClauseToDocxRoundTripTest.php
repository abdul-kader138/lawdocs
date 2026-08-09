<?php

namespace Tests\Feature;

use App\Services\Clause\ClauseMarkerParser;
use App\Services\DocxBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
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
}
