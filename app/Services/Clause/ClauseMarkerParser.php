<?php

namespace App\Services\Clause;

use App\Exceptions\ClauseMarkerException;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Title;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Numbering;

/**
 * Parses the plain-text clause marker convention out of a .docx:
 *
 *   [[CLAUSE:executor_powers]]
 *   ... any number of paragraphs/list items ...
 *   [[/CLAUSE]]
 *
 * A marker line must be a paragraph whose ENTIRE trimmed text matches the
 * pattern — "See [[CLAUSE:foo]] below" embedded mid-paragraph is ordinary
 * content, not a boundary.
 *
 * Deliberately has no knowledge of Precedent/ClauseLibrary — pure parsing,
 * so it's unit-testable against bare files.
 *
 * Critical: PhpWord::__construct() calls Style::resetStyles() (process-wide
 * static registry), and both IOFactory::load() and DocxBuilder::build() call
 * `new PhpWord()` internally. Any named style (a string) captured from an
 * element is worthless the moment anything else parses/builds a document in
 * this same PHP process — so every style must be dereferenced to a live
 * object HERE, during this walk, never deferred. See ClauseElement/DocxBuilder
 * for the other half of this (numbering objects must be RE-registered under a
 * fresh name in the target document, not just held onto).
 */
class ClauseMarkerParser
{
    private const OPEN_RE = '/^\[\[CLAUSE:([A-Za-z][A-Za-z0-9_]*)\]\]$/';
    private const CLOSE_RE = '/^\[\[\/CLAUSE\]\]$/';

    /**
     * @return array<string, ClauseElement[]> tag => ordered captured elements
     *
     * @throws ClauseMarkerException on unclosed/duplicate/nested/stray markers
     *         or an unsupported element type inside a clause
     */
    public function parse(string $absoluteDocxPath): array
    {
        $phpWord = IOFactory::load($absoluteDocxPath, 'Word2007');

        $clauses = [];
        $currentTag = null;
        $currentElements = [];
        $seenTags = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $markerText = $this->markerLineText($element);

                if ($markerText !== null && preg_match(self::OPEN_RE, trim($markerText), $m)) {
                    if ($currentTag !== null) {
                        throw ClauseMarkerException::nestedOpen($currentTag, $m[1]);
                    }
                    if (isset($seenTags[$m[1]])) {
                        throw ClauseMarkerException::duplicateTag($m[1]);
                    }
                    $currentTag = $m[1];
                    $seenTags[$currentTag] = true;
                    $currentElements = [];

                    continue;
                }

                if ($markerText !== null && preg_match(self::CLOSE_RE, trim($markerText))) {
                    if ($currentTag === null) {
                        throw ClauseMarkerException::strayClose();
                    }
                    $clauses[$currentTag] = $currentElements;
                    $currentTag = null;

                    continue;
                }

                if ($currentTag !== null) {
                    $currentElements[] = $this->capture($element, $currentTag);
                }
            }
        }

        if ($currentTag !== null) {
            throw ClauseMarkerException::unclosed($currentTag);
        }

        return $clauses;
    }

    /** Null means "not a plausible marker line" (not that it isn't one, just not markable). */
    private function markerLineText(mixed $element): ?string
    {
        // ListItemRun extends TextRun, so this also defensively catches a marker
        // accidentally typed as a list item, not just the mainline plain-paragraph case.
        if ($element instanceof TextRun) {
            return $element->getText();
        }

        if ($element instanceof Title) {
            $text = $element->getText();

            return $text instanceof TextRun ? $text->getText() : (string) $text;
        }

        return null;
    }

    private function capture(mixed $element, string $tagForErrors): ClauseElement
    {
        // Order matters: ListItemRun extends TextRun, so it must be checked first.
        if ($element instanceof ListItemRun) {
            return $this->captureListItemRun($element);
        }

        if ($element instanceof TextRun) {
            return $this->captureTextRun($element);
        }

        if ($element instanceof Title) {
            return $this->captureTitle($element);
        }

        if ($element instanceof PageBreak) {
            return ClauseElement::pageBreak();
        }

        if ($element instanceof Table) {
            throw ClauseMarkerException::unsupportedElement($tagForErrors, Table::class);
        }

        throw ClauseMarkerException::unsupportedElement($tagForErrors, get_class($element));
    }

    private function captureTextRun(TextRun $textRun): ClauseElement
    {
        return ClauseElement::textRun(
            $this->captureRuns($textRun),
            $this->resolveStyle($textRun->getParagraphStyle())
        );
    }

    private function captureListItemRun(ListItemRun $listItemRun): ClauseElement
    {
        $numberingStyle = null;
        $listStyle = $listItemRun->getStyle();

        if ($listStyle) {
            // ListItemStyle only exposes the numbering STYLE NAME (a string) —
            // there is no direct object getter. The live Numbering object must
            // be fetched from the registry ourselves, right now, while it's
            // still populated (see class docblock).
            $numStyleName = $listStyle->getNumStyle();
            if (is_string($numStyleName)) {
                $resolved = Style::getStyle($numStyleName);
                if ($resolved instanceof Numbering) {
                    $numberingStyle = $resolved;
                }
            }
        }

        return ClauseElement::listItemRun(
            $this->captureRuns($listItemRun),
            (int) $listItemRun->getDepth(),
            $numberingStyle,
            $this->resolveStyle($listItemRun->getParagraphStyle())
        );
    }

    private function captureTitle(Title $title): ClauseElement
    {
        $text = $title->getText();
        $flatText = $text instanceof TextRun ? $text->getText() : (string) $text;

        return ClauseElement::title($flatText, (int) $title->getDepth());
    }

    /** @return array<int, array{text: string, fontStyle: mixed}> */
    private function captureRuns(TextRun $container): array
    {
        $runs = [];

        foreach ($container->getElements() as $child) {
            if ($child instanceof Text) {
                $runs[] = [
                    'text' => $child->getText(),
                    'fontStyle' => $this->resolveStyle($child->getFontStyle()),
                ];
            }
            // Other inline element kinds (bookmarks, etc.) are skipped for v1 —
            // not part of the stated requirement and not silently mis-rendered,
            // just omitted from the reassembled run.
        }

        return $runs;
    }

    /**
     * A named style comes back from PHPWord as a plain string (a reference
     * into the styles.xml registry). Dereference it to the live object NOW —
     * see class docblock for why this can't be deferred.
     */
    private function resolveStyle(mixed $style): mixed
    {
        if (is_string($style)) {
            return Style::getStyle($style) ?? $style;
        }

        return $style;
    }
}
