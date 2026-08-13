<?php

namespace App\Services\Clause;

use PhpOffice\PhpWord\Style\Numbering;
use PhpOffice\PhpWord\Style\Paragraph;

/**
 * A single PHPWord element captured verbatim from inside a clause marker,
 * with its styles already dereferenced to live objects (see ClauseMarkerParser
 * docblock for why this must happen at capture time, not later). Deliberately
 * has no knowledge of Precedent, tags, or ClauseLibrary — a generic "preserved
 * PHPWord element" value object that DocxBuilder can render without depending
 * on the clause concept at all.
 */
final class ClauseElement implements \JsonSerializable
{
    private function __construct(
        public readonly string $kind, // 'text_run'|'list_item_run'|'title'|'page_break'|'table'
        public readonly array $runs = [], // [['text' => string, 'fontStyle' => Font|string|null], ...]
        public readonly Paragraph|string|null $paragraphStyle = null,
        public readonly ?int $depth = null,
        public readonly ?Numbering $numberingStyle = null,
        public readonly ?int $titleDepth = null,
        // kind:'table' only — array<int, array{cells: array<int, array{
        //   gridSpan: ?int, width: ?int, borderSize: ?int, borderColor: ?string,
        //   content: ClauseElement[]
        // }>}>. Cell style is captured as three hand-picked properties (not a
        // generic style passthrough) — Style\Cell is always a live object (never
        // a registry-backed named-style string, unlike paragraph/numbering
        // styles elsewhere in this class), so there's no dereferencing hazard,
        // but ClauseElement's shape stays flat/JSON-serializable regardless.
        // Border is captured as one uniform value (top side only) — real
        // content never sets independent per-side borders; if a precedent ever
        // does, the other three sides are silently dropped.
        public readonly ?array $tableRows = null,
    ) {}

    public static function textRun(array $runs, Paragraph|string|null $paragraphStyle): self
    {
        return new self(kind: 'text_run', runs: $runs, paragraphStyle: $paragraphStyle);
    }

    public static function listItemRun(array $runs, int $depth, ?Numbering $numberingStyle, Paragraph|string|null $paragraphStyle): self
    {
        return new self(
            kind: 'list_item_run',
            runs: $runs,
            paragraphStyle: $paragraphStyle,
            depth: $depth,
            numberingStyle: $numberingStyle,
        );
    }

    public static function title(string $text, int $depth): self
    {
        return new self(kind: 'title', runs: [['text' => $text, 'fontStyle' => null]], titleDepth: $depth);
    }

    public static function pageBreak(): self
    {
        return new self(kind: 'page_break');
    }

    /** @param  array<int, array{cells: array<int, array{gridSpan: ?int, width: ?int, borderSize: ?int, borderColor: ?string, content: ClauseElement[]}>}>  $rows */
    public static function table(array $rows): self
    {
        return new self(kind: 'table', tableRows: $rows);
    }

    /**
     * Swaps only $runs, copying every other property — critically,
     * numberingStyle is copied BY REFERENCE (PHP object assignment never
     * clones), so a placeholder-substituted list item emitted N times inside
     * a [[REPEAT]] still shares one live Numbering object instance across
     * all N copies. That shared identity is what DocxBuilder's
     * numberingNameFor() dedups on to keep "1. 2. 3." sequential instead of
     * each iteration restarting at "1." — see ClauseTemplateExpander.
     *
     * @param  array<int, array{text: string, fontStyle: mixed}>  $newRuns
     */
    public function withRuns(array $newRuns): self
    {
        return new self(
            kind: $this->kind,
            runs: $newRuns,
            paragraphStyle: $this->paragraphStyle,
            depth: $this->depth,
            numberingStyle: $this->numberingStyle,
            titleDepth: $this->titleDepth,
        );
    }

    /**
     * Swaps only $tableRows, copying every other property — sibling to
     * withRuns() rather than folded into it, since it preserves a different
     * invariant: unlike a list item's Numbering identity, no cell style here
     * is registry-backed (see the tableRows property docblock), so there is
     * no sharing hazard to protect — this exists purely so
     * ClauseTemplateExpander can produce a placeholder-substituted copy
     * without hand-constructing every other constructor argument.
     *
     * @param  array<int, array{cells: array<int, array{gridSpan: ?int, width: ?int, borderSize: ?int, borderColor: ?string, content: ClauseElement[]}>}>  $newRows
     */
    public function withTableRows(array $newRows): self
    {
        return new self(kind: $this->kind, tableRows: $newRows);
    }

    /**
     * Flattened, text-only projection for the audit-trail JSON column.
     * Explicitly NOT meant to be re-hydrated — PHPWord style objects aren't
     * JSON-safe, so this only exists so json_encode() doesn't choke or
     * silently drop this element when it's nested inside DocumentRequest's
     * generation_snapshot column.
     */
    public function jsonSerialize(): array
    {
        return [
            'kind' => $this->kind,
            'text' => $this->kind === 'table' ? $this->flattenTableText() : implode('', array_column($this->runs, 'text')),
            'depth' => $this->depth,
        ];
    }

    private function flattenTableText(): string
    {
        return collect($this->tableRows ?? [])
            ->map(fn ($row) => collect($row['cells'])
                ->map(fn ($cell) => collect($cell['content'])->map(fn (self $el) => $el->jsonSerialize()['text'])->implode(' | '))
                ->implode(' | '))
            ->implode("\n");
    }
}
