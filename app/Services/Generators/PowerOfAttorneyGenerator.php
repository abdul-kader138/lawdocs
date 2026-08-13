<?php

namespace App\Services\Generators;

use App\Contracts\DeclaresPartyFlags;
use App\Contracts\DocumentGenerator;
use App\Models\DocumentRequest;
use App\Services\ClauseLibrary;
use App\Services\Clause\ClauseSequenceRenderer;
use App\Services\PartyGroupAssembler;

/**
 * Second proof point (after WillGenerator) that a new document type is
 * mostly party-data assembly + a handful of named flags, not bespoke prose —
 * every clause here is either fully verbatim or marker-driven; this class
 * contains no legal wording of its own.
 *
 * Expects the precedent to tag: "appointment_clause" ([[REPEAT:attorneys AS
 * attorney]] + [[IF:attorneys_act_jointly]]), "enduring_notice"
 * ([[IF:is_enduring]], verbatim NSW-prescribed wording when true), and two
 * fully verbatim clauses "general_powers" and "revocation". Declares a party
 * group keyed "attorneys" (fields: name, address, relationship).
 *
 * Section order/headings/inclusion come from ClauseSequenceRenderer: an
 * admin can override defaultSequence() below per-precedent via the
 * Structure tab (Precedent::clause_sequence) without touching this class —
 * see plan "Admin-Configurable Clause Sequence". Unlike WillGenerator, both
 * top-level flags here (attorneys_act_jointly, is_enduring) are already
 * usable in a Structure "show only if" condition today.
 *
 * LEGAL CONTENT CAVEAT: the seeded/demo precedent this generator ships
 * against is drafted to plausibly match Powers of Attorney Act 2003 (NSW)
 * structure and terminology, but is NOT solicitor-reviewed. The engineering
 * here is production-ready; the actual clause wording in any precedent
 * assigned to it needs a solicitor sign-off pass before real client use —
 * see open risk #1 in the plan.
 *
 * Optional front matter: if the precedent tags a [[CLAUSE:front_matter]]
 * block, it fully replaces the hardcoded opening sentence below (admin-
 * editable via re-upload, using {{answers.principal_name}} /
 * {{answers.principal_address}}) — omit it to keep today's fixed wording.
 */
class PowerOfAttorneyGenerator implements DeclaresPartyFlags, DocumentGenerator
{
    public function __construct(
        private readonly ClauseSequenceRenderer $sequence,
        private readonly PartyGroupAssembler $parties,
        private readonly ClauseLibrary $clauses,
    ) {}

    public function generate(DocumentRequest $documentRequest): array
    {
        $precedent = $documentRequest->precedent;
        $answers = $documentRequest->answers ?? [];
        $isEnduring = (bool) ($answers['is_enduring'] ?? false);

        $context = [
            'answers' => $answers,
            'flags' => [
                'attorneys_act_jointly' => (bool) ($answers['attorneys_act_jointly'] ?? false),
                'is_enduring' => $isEnduring,
            ],
            'items' => $this->parties->forRequest($documentRequest),
        ];

        $blocks = $this->clauses->has($precedent, 'front_matter')
            ? [['type' => 'raw', 'elements' => $this->clauses->render($precedent, 'front_matter', $context)]]
            : [
                [
                    'type' => 'paragraph',
                    'text' => "This Power of Attorney is made by {$answers['principal_name']} of {$answers['principal_address']}.",
                ],
            ];

        array_push($blocks, ...$this->sequence->render($precedent, $context, $this->defaultSequence()));

        $title = $isEnduring ? 'Enduring Power of Attorney' : 'General Power of Attorney';

        return [
            'title' => "{$title} of {$answers['principal_name']}",
            'blocks' => $blocks,
        ];
    }

    public function availableFlags(): array
    {
        return [
            'attorneys_act_jointly' => 'True when the Attorneys must act jointly rather than jointly and severally — computed from the "attorneys_act_jointly" answer.',
            'is_enduring' => 'True when this is an Enduring Power of Attorney (survives the principal\'s loss of capacity) — computed from the "is_enduring" answer.',
        ];
    }

    public function availableGroups(): array
    {
        return [
            'attorneys' => 'Attorney(s)-in-fact appointed by the principal — expects fields name, address, relationship.',
        ];
    }

    /** @return array<int, array{heading: string, kind: string, tag_or_key: string, condition: ?string}> */
    private function defaultSequence(): array
    {
        return [
            ['heading' => 'Appointment of Attorney(s)', 'kind' => 'clause', 'tag_or_key' => 'appointment_clause', 'condition' => null],
            ['heading' => 'Enduring Power Notice', 'kind' => 'clause', 'tag_or_key' => 'enduring_notice', 'condition' => null],
            ['heading' => 'Powers Granted', 'kind' => 'clause', 'tag_or_key' => 'general_powers', 'condition' => null],
            ['heading' => 'Revocation', 'kind' => 'clause', 'tag_or_key' => 'revocation', 'condition' => null],
        ];
    }
}
