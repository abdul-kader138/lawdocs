<?php

namespace App\Services;

use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\ListItem;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;

class PrecedentTextExtractor
{
    /**
     * Extract plain text from a .docx precedent, preserving a lightweight
     * structural signal (heading depth, list items) — losing this is exactly
     * the "structure to mirror" signal that's the whole point of using a
     * precedent, so a flat text dump is deliberately avoided.
     *
     * Known limitation: PHPWord's Word2007 reader does not reliably extract
     * text from tables, text boxes, or headers/footers. If a precedent uses
     * a table for e.g. a signature block or schedule, that content may not
     * appear here — always check the extracted text preview after uploading.
     */
    public function extract(string $absolutePath): string
    {
        $phpWord = IOFactory::load($absolutePath, 'Word2007');

        $lines = [];
        foreach ($phpWord->getSections() as $section) {
            $this->walkContainer($section, $lines);
        }

        return trim(implode("\n", $lines));
    }

    private function walkContainer(AbstractContainer $container, array &$lines): void
    {
        foreach ($container->getElements() as $element) {
            if ($element instanceof Title) {
                $depth = (int) $element->getDepth();
                $lines[] = str_repeat('#', max(1, min(4, $depth))) . ' ' . $this->elementText($element);
                continue;
            }

            if ($element instanceof ListItem) {
                $lines[] = '- ' . $this->elementText($element);
                continue;
            }

            // A saved-then-reloaded list item round-trips as a ListItemRun
            // (a TextRun subclass carrying real Word numbering), not a plain
            // ListItem — must be checked before the generic TextRun branch
            // below, or it loses its "- " marker and reads as a bare paragraph.
            if ($element instanceof ListItemRun) {
                $text = $element->getText();
                if (trim($text) !== '') {
                    $lines[] = '- ' . $text;
                }
                continue;
            }

            if ($element instanceof Table) {
                // Known gap — see class docblock. Cells are walked on a
                // best-effort basis so at least some content survives.
                foreach ($element->getRows() as $row) {
                    foreach ($row->getCells() as $cell) {
                        $this->walkContainer($cell, $lines);
                    }
                }
                continue;
            }

            if ($element instanceof TextRun) {
                $text = $element->getText();
                if (trim($text) !== '') {
                    $lines[] = $text;
                }
                continue;
            }

            if ($element instanceof Text) {
                $text = $element->getText();
                if (trim((string) $text) !== '') {
                    $lines[] = $text;
                }
                continue;
            }
        }
    }

    private function elementText(Title|ListItem $element): string
    {
        $text = $element->getText();

        // Title/ListItem::getText() can return either a plain string or a
        // TextRun (when the heading/item contains mixed formatting).
        return $text instanceof TextRun ? $text->getText() : (string) $text;
    }
}
