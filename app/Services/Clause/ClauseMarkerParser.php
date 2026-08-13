<?php

namespace App\Services\Clause;

use App\Exceptions\ClauseMarkerException;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\ListItemRun;
use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
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
 * Inside an open clause, two control constructs may additionally nest
 * (but never at the top level, outside any [[CLAUSE:...]] — see
 * controlMarkerOutsideClause):
 *
 *   [[IF:condition]] ... [[ELSE]] ... [[/IF]]
 *   [[REPEAT:group_key]] ... [[/REPEAT]]           (or [[REPEAT:group_key AS alias]])
 *
 * IF/REPEAT may nest inside each other freely. A clause with none of these
 * still parses to a flat ClauseElement[] exactly as before — the return
 * type is a strict superset (array<ClauseElement|ClauseBlockNode>), so any
 * caller that only ever handled leaves keeps working unchanged.
 *
 * A marker line must be a paragraph whose ENTIRE trimmed text matches the
 * pattern — "See [[CLAUSE:foo]] below" embedded mid-paragraph is ordinary
 * content, not a boundary. The literal condition/text of an [[IF:...]] is
 * NOT validated here beyond "non-empty" — full grammar validation (allowed
 * tokens, unknown flags) happens at expansion time in ConditionEvaluator,
 * since that's the only place the set of valid flags is actually known.
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

    private const OPEN_IF_RE = '/^\[\[IF:(.*)\]\]$/';

    private const LOOKS_LIKE_IF_RE = '/^\[\[IF:/';

    private const ELSE_RE = '/^\[\[ELSE\]\]$/';

    private const CLOSE_IF_RE = '/^\[\[\/IF\]\]$/';

    private const OPEN_REPEAT_RE = '/^\[\[REPEAT:([A-Za-z_][A-Za-z0-9_]*)(?:\s+AS\s+([A-Za-z_][A-Za-z0-9_]*))?\]\]$/';

    private const LOOKS_LIKE_REPEAT_RE = '/^\[\[REPEAT:/';

    private const CLOSE_REPEAT_RE = '/^\[\[\/REPEAT\]\]$/';

    /**
     * @return array<string, array<int, ClauseElement|ClauseBlockNode>> tag => ordered captured tree
     *
     * @throws ClauseMarkerException on unclosed/duplicate/nested/stray markers
     *                               or an unsupported element type inside a clause
     */
    public function parse(string $absoluteDocxPath): array
    {
        $phpWord = IOFactory::load($absoluteDocxPath, 'Word2007');

        $clauses = [];
        $currentTag = null;
        $currentElements = [];
        $seenTags = [];
        /** @var array<int, array<string, mixed>> $stack open [[IF]]/[[REPEAT]] frames, innermost last */
        $stack = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $markerText = $this->markerLineText($element);
                $trimmed = $markerText !== null ? trim($markerText) : null;

                if ($trimmed !== null && preg_match(self::OPEN_RE, $trimmed, $m)) {
                    if ($currentTag !== null) {
                        throw ClauseMarkerException::nestedOpen($currentTag, $m[1]);
                    }
                    if (isset($seenTags[$m[1]])) {
                        throw ClauseMarkerException::duplicateTag($m[1]);
                    }
                    $currentTag = $m[1];
                    $seenTags[$currentTag] = true;
                    $currentElements = [];
                    $stack = [];

                    continue;
                }

                if ($trimmed !== null && preg_match(self::CLOSE_RE, $trimmed)) {
                    if ($currentTag === null) {
                        throw ClauseMarkerException::strayClose();
                    }
                    if ($stack !== []) {
                        $open = $stack[count($stack) - 1];
                        throw $open['kind'] === 'if'
                            ? ClauseMarkerException::unclosedIf($currentTag)
                            : ClauseMarkerException::unclosedRepeat($currentTag);
                    }
                    $clauses[$currentTag] = $currentElements;
                    $currentTag = null;

                    continue;
                }

                if ($trimmed !== null && preg_match(self::OPEN_IF_RE, $trimmed, $m)) {
                    if ($currentTag === null) {
                        throw ClauseMarkerException::controlMarkerOutsideClause('IF');
                    }
                    $condition = trim($m[1]);
                    if ($condition === '') {
                        throw ClauseMarkerException::malformedConditionSyntax($currentTag, $trimmed);
                    }
                    $stack[] = ['kind' => 'if', 'condition' => $condition, 'in_else' => false, 'then' => [], 'else' => []];

                    continue;
                }

                if ($trimmed !== null && preg_match(self::ELSE_RE, $trimmed)) {
                    if ($currentTag === null) {
                        throw ClauseMarkerException::controlMarkerOutsideClause('ELSE');
                    }
                    $this->requireOpenFrame($stack, 'if', $currentTag, ClauseMarkerException::strayElse());
                    if ($stack[count($stack) - 1]['in_else']) {
                        throw ClauseMarkerException::doubleElse($currentTag);
                    }
                    $stack[count($stack) - 1]['in_else'] = true;

                    continue;
                }

                if ($trimmed !== null && preg_match(self::CLOSE_IF_RE, $trimmed)) {
                    if ($currentTag === null) {
                        throw ClauseMarkerException::controlMarkerOutsideClause('/IF');
                    }
                    $this->requireOpenFrame($stack, 'if', $currentTag, ClauseMarkerException::strayEndIf());
                    $frame = array_pop($stack);
                    $node = ClauseBlockNode::ifNode(
                        $frame['condition'],
                        $frame['then'],
                        $frame['in_else'] ? $frame['else'] : null,
                    );
                    $this->appendNode($stack, $currentElements, $node);

                    continue;
                }

                if ($trimmed !== null && preg_match(self::OPEN_REPEAT_RE, $trimmed, $m)) {
                    if ($currentTag === null) {
                        throw ClauseMarkerException::controlMarkerOutsideClause('REPEAT');
                    }
                    $stack[] = ['kind' => 'repeat', 'group_key' => $m[1], 'alias' => ($m[2] ?? '') !== '' ? $m[2] : $m[1], 'children' => []];

                    continue;
                }

                if ($trimmed !== null && preg_match(self::LOOKS_LIKE_REPEAT_RE, $trimmed) && ! preg_match(self::CLOSE_REPEAT_RE, $trimmed)) {
                    if ($currentTag === null) {
                        throw ClauseMarkerException::controlMarkerOutsideClause('REPEAT');
                    }
                    throw ClauseMarkerException::malformedRepeatHeader($currentTag, $trimmed);
                }

                if ($trimmed !== null && preg_match(self::CLOSE_REPEAT_RE, $trimmed)) {
                    if ($currentTag === null) {
                        throw ClauseMarkerException::controlMarkerOutsideClause('/REPEAT');
                    }
                    $this->requireOpenFrame($stack, 'repeat', $currentTag, ClauseMarkerException::strayEndRepeat());
                    $frame = array_pop($stack);
                    $node = ClauseBlockNode::repeatNode($frame['group_key'], $frame['alias'], $frame['children']);
                    $this->appendNode($stack, $currentElements, $node);

                    continue;
                }

                if ($currentTag !== null) {
                    $this->appendNode($stack, $currentElements, $this->capture($element, $currentTag));
                }
            }
        }

        if ($currentTag !== null) {
            if ($stack !== []) {
                $open = $stack[count($stack) - 1];
                throw $open['kind'] === 'if'
                    ? ClauseMarkerException::unclosedIf($currentTag)
                    : ClauseMarkerException::unclosedRepeat($currentTag);
            }
            throw ClauseMarkerException::unclosed($currentTag);
        }

        return $clauses;
    }

    private function requireOpenFrame(array $stack, string $expectedKind, string $tag, ClauseMarkerException $strayException): void
    {
        if ($stack === [] || $stack[count($stack) - 1]['kind'] !== $expectedKind) {
            throw $strayException;
        }
    }

    /**
     * Appends a captured leaf or completed control node to whatever's
     * currently accumulating: the innermost open [[IF]]/[[REPEAT]] frame's
     * active buffer, or — once the stack is empty — the clause's top-level
     * element list directly.
     *
     * @param  array<int, array<string, mixed>>  $stack
     * @param  array<int, ClauseElement|ClauseBlockNode>  $currentElements
     */
    private function appendNode(array &$stack, array &$currentElements, ClauseElement|ClauseBlockNode $node): void
    {
        if ($stack === []) {
            $currentElements[] = $node;

            return;
        }

        $top = count($stack) - 1;
        if ($stack[$top]['kind'] === 'if') {
            if ($stack[$top]['in_else']) {
                $stack[$top]['else'][] = $node;
            } else {
                $stack[$top]['then'][] = $node;
            }
        } else {
            $stack[$top]['children'][] = $node;
        }
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
            return $this->captureTable($element, $tagForErrors);
        }

        throw ClauseMarkerException::unsupportedElement($tagForErrors, get_class($element));
    }

    /**
     * Static tables only (see class docblock's scope note) — a cell may
     * contain plain paragraphs/list items/titles with {{ns.field}}
     * placeholders, resolved the same way as any other clause content, but
     * NOT [[IF]]/[[REPEAT]] (captureCellElements() rejects those loudly
     * rather than silently treating marker-looking text as literal content).
     */
    private function captureTable(Table $table, string $tagForErrors): ClauseElement
    {
        $rows = [];

        foreach ($table->getRows() as $row) {
            $cells = [];

            foreach ($row->getCells() as $cell) {
                // Style\Cell is always a live object built directly from the
                // parsed w:tcPr XML — never a named-style string reference like
                // TextRun::getParagraphStyle() can be — so, unlike everywhere
                // else in this class, no resolveStyle() dereference is needed.
                $style = $cell->getStyle();

                $cells[] = [
                    'gridSpan' => $style?->getGridSpan(),
                    'width' => $cell->getWidth(),
                    // Uniform border only (top side) — matches every real
                    // precedent's usage; independent per-side borders, if a
                    // precedent ever set them, are silently collapsed to one.
                    'borderSize' => $style?->getBorderTopSize(),
                    'borderColor' => $style?->getBorderTopColor(),
                    'content' => $this->captureCellElements($cell, $tagForErrors),
                ];
            }

            $rows[] = ['cells' => $cells];
        }

        return ClauseElement::table($rows);
    }

    /** @return ClauseElement[] */
    private function captureCellElements(Cell $cell, string $tagForErrors): array
    {
        $elements = [];

        foreach ($cell->getElements() as $element) {
            $markerText = $this->markerLineText($element);
            $trimmed = $markerText !== null ? trim($markerText) : null;

            if ($trimmed !== null && $this->looksLikeAnyMarker($trimmed)) {
                throw ClauseMarkerException::controlMarkerInsideTableCell($tagForErrors, $trimmed);
            }

            if ($element instanceof Table) {
                throw ClauseMarkerException::nestedTableUnsupported($tagForErrors);
            }

            // A genuinely empty cell (no addText()/addTextRun() call at all —
            // a real, common pattern for spacer cells in ported table content)
            // round-trips through save/reload as a lone TextBreak, since OOXML
            // requires every cell to contain at least one paragraph. Not
            // content to preserve, not a marker, not an error — just skip it.
            if ($element instanceof TextBreak) {
                continue;
            }

            $elements[] = $this->capture($element, $tagForErrors);
        }

        return $elements;
    }

    /** True if $trimmed looks like ANY [[...]] control marker (well-formed or not) — reuses the same regexes the top-level state machine matches against, so there's one source of truth for "what a marker line looks like." */
    private function looksLikeAnyMarker(string $trimmed): bool
    {
        foreach ([self::OPEN_RE, self::CLOSE_RE, self::LOOKS_LIKE_IF_RE, self::ELSE_RE, self::CLOSE_IF_RE, self::LOOKS_LIKE_REPEAT_RE, self::CLOSE_REPEAT_RE] as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return true;
            }
        }

        return false;
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
