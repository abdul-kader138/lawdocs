<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\Clause\ClauseElement;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\ListItem as ListItemStyle;
use PhpOffice\PhpWord\Style\Numbering;
use PhpOffice\PhpWord\Style\Paragraph;
use PhpOffice\PhpWord\SimpleType\Jc;

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

    private array $bodyParagraphStyle = [];

    private bool $applyParagraphStyleToClauses = false;

    /**
     * Deterministically build a .docx from a generator's structured block
     * output. Blocks are either generated-text instructions (heading,
     * paragraph, list_item, page_break) or a 'raw' block carrying verbatim
     * ClauseElement[] captured from a precedent — no markdown/prose parsing,
     * a straight mapping to PHPWord calls either way.
     *
     * $formattingOverrides is Precedent::formattingConfig()'s shape
     * ({font_family, font_size, heading_bold, heading_size_step}, any key
     * nullable) — every key falls back to the global Setting/hardcoded
     * default when null. Read with ?? specifically (never ?:/empty()):
     * heading_bold === false and heading_size_step === 0 are both
     * meaningful, falsy override values that those operators would silently
     * discard.
     */
    public function build(string $title, array $blocks, array $formattingOverrides = []): PhpWord
    {
        $this->numberingNameByObjectId = [];

        $phpWord = new PhpWord();

        $fontFamily = $formattingOverrides['font_family'] ?? Setting::get('docx_font_family', 'Times New Roman');
        $fontSize   = (int) ($formattingOverrides['font_size'] ?? Setting::get('docx_font_size', 12));
        $headingBold = $formattingOverrides['heading_bold'] ?? true;
        $headingSizeStep = (int) ($formattingOverrides['heading_size_step'] ?? 2);
        $alignment = $formattingOverrides['body_alignment'] ?? Jc::START;
        $lineSpacing = (float) ($formattingOverrides['line_spacing'] ?? 1.0);
        $paragraphSpaceAfter = (float) ($formattingOverrides['paragraph_space_after'] ?? 10);

        $this->bodyParagraphStyle = [
            'alignment' => $alignment,
            'lineHeight' => $lineSpacing,
            'spaceAfter' => (int) round($paragraphSpaceAfter * 20),
            'widowControl' => true,
            'indentation' => array_filter([
                'firstLine' => $this->millimetresToTwips($formattingOverrides['first_line_indent'] ?? null),
                'left' => $this->millimetresToTwips($formattingOverrides['left_indent'] ?? null),
                'right' => $this->millimetresToTwips($formattingOverrides['right_indent'] ?? null),
            ], fn ($value) => $value !== null),
        ];
        $this->applyParagraphStyleToClauses = (bool) ($formattingOverrides['apply_paragraph_style_to_clauses'] ?? false);

        $phpWord->setDefaultFontName($fontFamily);
        $phpWord->setDefaultFontSize($fontSize);

        // Real Word "Heading N" styles (not just bold text) — shows up in
        // Word's Navigation pane / a lawyer's table of contents.
        for ($depth = 1; $depth <= 4; $depth++) {
            Style::addTitleStyle(
                $depth,
                ['bold' => $headingBold, 'size' => $fontSize + (5 - $depth) * $headingSizeStep],
                ['spaceBefore' => $depth === 1 ? 0 : 240, 'spaceAfter' => 120, 'keepNext' => true],
            );
        }

        $section = $phpWord->addSection(array_filter([
            'marginTop' => $this->millimetresToTwips($formattingOverrides['margin_top'] ?? null),
            'marginRight' => $this->millimetresToTwips($formattingOverrides['margin_right'] ?? null),
            'marginBottom' => $this->millimetresToTwips($formattingOverrides['margin_bottom'] ?? null),
            'marginLeft' => $this->millimetresToTwips($formattingOverrides['margin_left'] ?? null),
        ], fn ($value) => $value !== null));
        $section->addTitle($title, 1);

        foreach ($blocks as $block) {
            $this->addBlock($section, $block);
        }

        $footerText = $formattingOverrides['footer_text'] ?? null;
        $pageNumbers = $formattingOverrides['page_numbers'] ?? false;

        if ($footerText || $pageNumbers) {
            $footer = $section->addFooter();

            if ($footerText) {
                $footer->addText($footerText, ['size' => max(8, $fontSize - 2)], ['alignment' => Jc::CENTER]);
            }

            if ($pageNumbers) {
                $footer->addPreserveText('Page {PAGE} of {NUMPAGES}', ['size' => max(8, $fontSize - 2)], ['alignment' => Jc::END]);
            }
        }

        return $phpWord;
    }

    public function buildAndSave(string $title, array $blocks, string $absolutePath, array $formattingOverrides = []): void
    {
        $phpWord = $this->build($title, $blocks, $formattingOverrides);
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
            'list_item'  => $section->addListItem($text, 0, $fontStyle ?: null, $listStyle, $this->listParagraphStyle()),
            'page_break' => $section->addPageBreak(),
            default      => $section->addText($text, $fontStyle ?: null, $this->bodyParagraphStyle),
        };
    }

    private function millimetresToTwips(mixed $millimetres): ?int
    {
        return $millimetres === null || $millimetres === ''
            ? null
            : (int) round((float) $millimetres * 56.692913);
    }

    /**
     * $section is a Section for top-level clause content, or a Cell when
     * called recursively from addTableElement() to emit a table cell's
     * content — both extend AbstractContainer and expose the identical
     * addTextRun()/addListItemRun()/addTitle()/addPageBreak()/addTable() API,
     * so no kind-specific branching is needed here for that distinction.
     *
     * @param  ClauseElement[]  $elements
     */
    private function addRawElements(AbstractContainer $section, array $elements): void
    {
        foreach ($elements as $el) {
            match ($el->kind) {
                'title'         => $section->addTitle($this->flattenRuns($el->runs), $el->titleDepth),
                'page_break'    => $section->addPageBreak(),
                'text_run'      => $this->emitRun($section->addTextRun($this->clauseParagraphStyle($el->paragraphStyle)), $el->runs),
                'list_item_run' => $this->emitRun(
                    $section->addListItemRun($el->depth, $this->numberingNameFor($el->numberingStyle), $this->clauseParagraphStyle($el->paragraphStyle, list: true)),
                    $el->runs
                ),
                'table' => $this->addTableElement($section, $el),
                default => throw new \InvalidArgumentException("Unknown ClauseElement kind [{$el->kind}]."),
            };
        }
    }

    private function listParagraphStyle(): array
    {
        $style = $this->bodyParagraphStyle;
        unset($style['indentation']['firstLine']);

        return $style;
    }

    private function clauseParagraphStyle(Paragraph|string|null $source, bool $list = false): Paragraph|string|array|null
    {
        if (! $this->applyParagraphStyleToClauses) {
            return $source;
        }

        $overrides = $list ? $this->listParagraphStyle() : $this->bodyParagraphStyle;

        if (! $source instanceof Paragraph) {
            return $overrides;
        }

        $style = clone $source;
        $style->setAlignment($overrides['alignment']);
        $style->setLineHeight($overrides['lineHeight']);
        $style->setSpaceAfter($overrides['spaceAfter']);
        $style->setWidowControl(true);

        if ($overrides['indentation'] !== []) {
            $style->setIndentation($overrides['indentation']);
        }

        return $style;
    }

    /**
     * No numbering-registry work needed here beyond what numberingNameFor()
     * already does — $numberingNameByObjectId is keyed by spl_object_id() and
     * shared for the whole build() call, so a multilevel list split across
     * several cells of one table (real usage in ported precedent content)
     * dedupes onto one sequential numId automatically via the recursive
     * addRawElements() call below, exactly as it already does for list items
     * split across ordinary top-level clause content.
     */
    private function addTableElement(AbstractContainer $section, ClauseElement $el): void
    {
        $table = $section->addTable();

        foreach ($el->tableRows ?? [] as $row) {
            $phpWordRow = $table->addRow();

            foreach ($row['cells'] as $cellSpec) {
                $cellStyle = array_filter([
                    'gridSpan' => $cellSpec['gridSpan'],
                    'borderSize' => $cellSpec['borderSize'],
                    'borderColor' => $cellSpec['borderColor'],
                ], fn ($v) => $v !== null);

                $cell = $phpWordRow->addCell($cellSpec['width'], $cellStyle ?: null);
                $this->addRawElements($cell, $cellSpec['content']);
            }
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
