<?php

namespace App\Services\Clause;

use App\Models\Precedent;
use App\Services\ClauseLibrary;
use RuntimeException;

/**
 * Turns a precedent's section list — either the admin-configured
 * clause_sequence or a generator's hardcoded fallback, both in the same
 * normalized {heading, kind, tag_or_key, condition} shape — into ordered,
 * auto-numbered DocxBuilder blocks. One code path for both cases: whichever
 * generators previously wrote as
 *
 *   $blocks[] = ['type'=>'heading','level'=>2,'text'=>'1. Revocation'];
 *   $blocks[] = ['type'=>'raw','elements'=>$this->clauses->get($precedent,'revocation')];
 *
 * repeated per section, they now hand their default sequence to render()
 * here instead — admin-authored sequences (Precedent::clauseSequenceConfig())
 * override it entirely when non-empty. Numbering is computed from position
 * among entries whose condition (if any) actually passes, so hiding a
 * section never leaves a gap like "1. 3. 4.".
 *
 * kind:'clause' entries always go through ClauseLibrary::render() (not
 * get()) — safe even for a fully verbatim clause with no [[IF]]/[[REPEAT]]/
 * {{...}}, since ClauseTemplateExpander is a no-op when there's nothing to
 * substitute. kind:'computed' entries are dispatched to $computedResolver,
 * the one escape hatch for a section that's genuinely not a clause-tag
 * render (see App\Contracts\DeclaresComputedBlocks).
 */
class ClauseSequenceRenderer
{
    public function __construct(
        private readonly ClauseLibrary $clauses,
        private readonly ConditionEvaluator $conditions = new ConditionEvaluator,
    ) {}

    /**
     * @param  array{answers?: array, flags?: array<string,bool>, items?: array}  $context
     * @param  array<int, array{heading: string, kind: string, tag_or_key: string, condition: ?string}>  $fallback  the calling generator's default sequence, already in Precedent::clauseSequenceConfig()'s normalized shape
     * @param  null|\Closure(string $key, array $context): array<string,mixed>  $computedResolver  required only if $fallback or the precedent's own sequence contains a kind:'computed' entry
     * @return array<int, array<string,mixed>> DocxBuilder-ready blocks
     */
    public function render(Precedent $precedent, array $context, array $fallback, ?\Closure $computedResolver = null): array
    {
        $entries = $precedent->clauseSequenceConfig();
        $entries = $entries !== [] ? $entries : $fallback;

        $flags = $context['flags'] ?? [];
        $blocks = [];
        $number = 0;

        foreach ($entries as $entry) {
            if ($entry['condition'] !== null && ! $this->conditions->evaluate($entry['condition'], $flags)) {
                continue;
            }

            $number++;
            $blocks[] = ['type' => 'heading', 'level' => 2, 'text' => "{$number}. {$entry['heading']}"];

            if ($entry['kind'] === 'computed') {
                if ($computedResolver === null) {
                    throw new RuntimeException("Clause sequence entry \"{$entry['heading']}\" is kind:computed but no computed-block resolver was supplied.");
                }

                $blocks[] = $computedResolver($entry['tag_or_key'], $context);

                continue;
            }

            $blocks[] = ['type' => 'raw', 'elements' => $this->clauses->render($precedent, $entry['tag_or_key'], $context)];
        }

        return $blocks;
    }
}
