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
        public readonly string $kind, // 'text_run'|'list_item_run'|'title'|'page_break'
        public readonly array $runs = [], // [['text' => string, 'fontStyle' => Font|string|null], ...]
        public readonly Paragraph|string|null $paragraphStyle = null,
        public readonly ?int $depth = null,
        public readonly ?Numbering $numberingStyle = null,
        public readonly ?int $titleDepth = null,
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
            'kind'  => $this->kind,
            'text'  => implode('', array_column($this->runs, 'text')),
            'depth' => $this->depth,
        ];
    }
}
