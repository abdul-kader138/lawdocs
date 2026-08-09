<?php

namespace App\Services;

use App\Exceptions\ClauseMarkerException;
use App\Exceptions\ClauseNotFoundException;
use App\Models\Precedent;
use App\Services\Clause\ClauseMarkerParser;
use Illuminate\Support\Facades\Storage;

/**
 * The precedent-aware façade generators use to pull verbatim clause text.
 * ClauseMarkerParser itself knows nothing about Precedent — this class is
 * what adds that context (which file to parse, and which precedent a parse
 * error happened in).
 */
class ClauseLibrary
{
    /** @var array<int, array<string, \App\Services\Clause\ClauseElement[]>> in-memory only, keyed by Precedent::id */
    private array $cache = [];

    public function __construct(private readonly ClauseMarkerParser $parser = new ClauseMarkerParser()) {}

    /** @return \App\Services\Clause\ClauseElement[] */
    public function get(Precedent $precedent, string $tag): array
    {
        $all = $this->allFor($precedent);

        if (! array_key_exists($tag, $all)) {
            throw ClauseNotFoundException::forTag($precedent, $tag, array_keys($all));
        }

        return $all[$tag];
    }

    public function has(Precedent $precedent, string $tag): bool
    {
        return array_key_exists($tag, $this->allFor($precedent));
    }

    /**
     * Extracts every clause in one pass per precedent, memoized in-memory
     * only (never persisted on the model like extracted_text is — a
     * Font/Paragraph/Numbering object can't be faithfully round-tripped
     * through JSON without real risk of silent, lossy corruption; re-parsing
     * the .docx is a cheap ~10-30s-generation-relative cost by comparison).
     *
     * @return array<string, \App\Services\Clause\ClauseElement[]>
     */
    public function allFor(Precedent $precedent): array
    {
        return $this->cache[$precedent->id] ??= $this->parseOrFail($precedent);
    }

    private function parseOrFail(Precedent $precedent): array
    {
        try {
            return $this->parser->parse(Storage::disk('local')->path($precedent->docx_path));
        } catch (ClauseMarkerException $e) {
            throw $e->withPrecedentContext($precedent);
        }
    }
}
