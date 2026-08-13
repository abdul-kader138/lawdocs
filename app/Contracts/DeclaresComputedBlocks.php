<?php

namespace App\Contracts;

/**
 * Optional companion to DocumentGenerator, parallel to DeclaresPartyFlags —
 * a generator implements this only when it has a section that genuinely
 * can't be expressed as a [[CLAUSE:tag]] render (real branching logic,
 * grammar resolution, etc.), e.g. WillGenerator's executor paragraph. Most
 * generators need none of these and simply don't implement this interface.
 *
 * Consumed by ClauseSequenceRenderer's $computedResolver dispatch (a
 * clause_sequence entry of kind:'computed' names one of these keys instead
 * of a clause tag) and, like DeclaresPartyFlags, surfaced read-only in
 * PrecedentResource's authoring reference and cross-checked at save time in
 * Precedent::validateMarkerReferences().
 */
interface DeclaresComputedBlocks
{
    /** @return array<string, string> computed block key => human description */
    public function availableComputedBlocks(): array;
}
