<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\Clause\ClauseElement;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\ListItem as ListItemStyle;
use PhpOffice\PhpWord\Style\Numbering;

class DocxBuilder
{
    /**
     * Reused across a single build() call so multiple list items sharing one
     * SOURCE numbering definition (spl_object_id-keyed) share one
     * re-registered name in the TARGET document too — otherwise each would
     * get its own numbering definition and a numbered list would re-emit as
     * "1. 1. 1." instead of "1. 2. 3.". Reset per build() since Style's
     * registry itself is wiped by the `new PhpWord()` inside build().
     */
    private array $numberingNameByObjectId = [];

    /**
     * Deterministically build a .docx from a generator's structured block
     * output. Blocks are either generated-text instructions (heading,
     * paragraph, list_item, page_break) or a 'raw' block carrying verbatim
     * ClauseElement[] captured from a precedent — no markdown/prose parsing,
     * a straight mapping to PHPWord calls either way.
     */
    public function build(string $title, array $blocks): PhpWord
    {
        $this->numberingNameByObjectId = [];

        $phpWord = new PhpWord();

        $fontFamily = Setting::get('docx_font_family', 'Times New Roman');
        $fontSize   = (int) Setting::get('docx_font_size', 12);

        $phpWord->setDefaultFontName($fontFamily);
        $phpWord->setDefaultFontSize($fontSize);

        // Real Word "Heading N" styles (not just bold text) — shows up in
        // Word's Navigation pane / a lawyer's table of contents.
        for ($depth = 1; $depth <= 4; $depth++) {
            Style::addTitleStyle($depth, ['bold' => true, 'size' => $fontSize + (5 - $depth) * 2]);
        }

        $section = $phpWord->addSection();
        $section->addTitle($title, 1);

        foreach ($blocks as $block) {
            $this->addBlock($section, $block);
        }

        return $phpWord;
    }

    public function buildAndSave(string $title, array $blocks, string $absolutePath): void
    {
        $phpWord = $this->build($title, $blocks);
        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);
    }

    private function addBlock(Section $section, array $block): void
    {
        $type = $block['type'] ?? 'paragraph';

        if ($type === 'raw') {
            $this->addRawElements($section, $block['elements'] ?? []);

            return;
        }

        $text = $block['text'] ?? '';

        $fontStyle = array_filter([
            'bold'   => $block['bold'] ?? null,
            'italic' => $block['italic'] ?? null,
        ], fn ($v) => $v !== null);

        $listStyle = ($block['list_type'] ?? 'bullet') === 'number'
            ? ['listType' => ListItemStyle::TYPE_NUMBER]
            : null;

        match ($type) {
            'heading'    => $section->addTitle($text, max(1, min(4, (int) ($block['level'] ?? 2)))),
            'list_item'  => $section->addListItem($text, 0, $fontStyle ?: null, $listStyle),
            'page_break' => $section->addPageBreak(),
            default      => $section->addText($text, $fontStyle ?: null, ['spaceAfter' => 200]),
        };
    }

    /** @param ClauseElement[] $elements */
    private function addRawElements(Section $section, array $elements): void
    {
        foreach ($elements as $el) {
            match ($el->kind) {
                'title'         => $section->addTitle($this->flattenRuns($el->runs), $el->titleDepth),
                'page_break'    => $section->addPageBreak(),
                'text_run'      => $this->emitRun($section->addTextRun($el->paragraphStyle), $el->runs),
                'list_item_run' => $this->emitRun(
                    $section->addListItemRun($el->depth, $this->numberingNameFor($el->numberingStyle), $el->paragraphStyle),
                    $el->runs
                ),
                default => throw new \InvalidArgumentException("Unknown ClauseElement kind [{$el->kind}]."),
            };
        }
    }

    /**
     * A captured Numbering object can't be handed straight to
     * addListItemRun() — PHPWord silently discards any $listStyle argument
     * that isn't a string (registry name) or array. It must be re-registered
     * into THIS (post-reset) document's Style registry under a fresh name.
     * Deduping by the source object's identity is what keeps consecutive
     * items from the same original list numbered sequentially instead of
     * each restarting at "1.".
     */
    private function numberingNameFor(?Numbering $style): ?string
    {
        if ($style === null) {
            return null;
        }

        $id = spl_object_id($style);

        return $this->numberingNameByObjectId[$id] ??= (function () use ($style, $id) {
            $name = "clause_num_{$id}";
            Style::addNumberingStyle($name, $style);

            return $name;
        })();
    }

    /** @param array<int, array{text: string, fontStyle: mixed}> $runs */
    private function emitRun(TextRun $target, array $runs): void
    {
        foreach ($runs as $run) {
            $target->addText($run['text'], $run['fontStyle']);
        }
    }

    /** @param array<int, array{text: string, fontStyle: mixed}> $runs */
    private function flattenRuns(array $runs): string
    {
        return implode('', array_column($runs, 'text'));
    }
}
