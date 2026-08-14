<?php

namespace App\Services;

use App\Exceptions\ClauseMarkerException;
use App\Exceptions\ClauseNotFoundException;
use App\Models\Precedent;
use App\Services\Clause\ClauseBlockNode;
use App\Services\Clause\ClauseElement;
use App\Services\Clause\ClauseMarkerParser;
use App\Services\Clause\ClauseTemplateExpander;
use Illuminate\Support\Facades\Storage;

/**
 * The precedent-aware façade generators use to pull verbatim clause text.
 * ClauseMarkerParser itself knows nothing about Precedent — this class is
 * what adds that context (which file to parse, and which precedent a parse
 * error happened in).
 */
class ClauseLibrary
{
    /**
     * @var array<string, array<string, array<int, ClauseElement|ClauseBlockNode>>>
     * In-memory only. The file path and model version are included so a
     * long-running queue worker cannot serve parsed clauses from a replaced
     * template (and recreated database IDs cannot collide).
     */
    private array $cache = [];

    public function __construct(
        private readonly ClauseMarkerParser $parser = new ClauseMarkerParser,
        private readonly ClauseTemplateExpander $expander = new ClauseTemplateExpander,
    ) {}

    /**
     * Verbatim retrieval — the tree ClauseMarkerParser returns for a clause
     * with no [[IF]]/[[REPEAT]] is already a flat ClauseElement[], so this
     * keeps working exactly as before for every existing call site
     * (revocation, executor_powers, ...) with zero change.
     *
     * @return ClauseElement[]
     */
    public function get(Precedent $precedent, string $tag): array
    {
        return $this->nodesForTag($precedent, $tag);
    }

    /**
     * Resolves [[IF]]/[[REPEAT]]/{{...}} control markup for a clause against
     * $context (see ClauseTemplateExpander) and returns the resulting flat
     * ClauseElement[]. Use this instead of get() for any clause a precedent
     * has authored with control markers; get() alone would hand back
     * ClauseBlockNode instances DocxBuilder doesn't understand.
     *
     * @return ClauseElement[]
     */
    public function render(Precedent $precedent, string $tag, array $context): array
    {
        return $this->expander->expand($this->nodesForTag($precedent, $tag), $context);
    }

    public function has(Precedent $precedent, string $tag): bool
    {
        return array_key_exists($tag, $this->allFor($precedent));
    }

    private function nodesForTag(Precedent $precedent, string $tag): array
    {
        $all = $this->allFor($precedent);

        if (! array_key_exists($tag, $all)) {
            throw ClauseNotFoundException::forTag($precedent, $tag, array_keys($all));
        }

        return $all[$tag];
    }

    /**
     * Extracts every clause in one pass per precedent, memoized in-memory
     * only (never persisted on the model like extracted_text is — a
     * Font/Paragraph/Numbering object can't be faithfully round-tripped
     * through JSON without real risk of silent, lossy corruption; re-parsing
     * the .docx is a cheap ~10-30s-generation-relative cost by comparison).
     *
     * @return array<string, array<int, ClauseElement|ClauseBlockNode>>
     */
    public function allFor(Precedent $precedent): array
    {
        $key = implode('|', [
            $precedent->getConnectionName() ?: config('database.default'),
            $precedent->getKey(),
            $precedent->docx_path,
            $precedent->updated_at?->format('U.u') ?? 'unsaved',
        ]);

        return $this->cache[$key] ??= $this->parseOrFail($precedent);
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
