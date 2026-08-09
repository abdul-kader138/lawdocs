<?php

namespace App\Services;

use App\Models\Setting;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\ListItem as ListItemStyle;

class DocxBuilder
{
    /**
     * Deterministically build a .docx from Claude's structured block output.
     * No markdown/prose parsing here — the block structure is already
     * unambiguous, so this is a straight mapping to PHPWord calls.
     */
    public function build(string $title, array $blocks): PhpWord
    {
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

    private function addBlock(\PhpOffice\PhpWord\Element\Section $section, array $block): void
    {
        $type = $block['type'] ?? 'paragraph';
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
}
