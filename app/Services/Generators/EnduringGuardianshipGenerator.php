<?php

namespace App\Services\Generators;

use App\Contracts\DeclaresPartyFlags;
use App\Contracts\DocumentGenerator;
use App\Models\DocumentRequest;
use App\Services\ClauseLibrary;
use App\Services\Clause\ClauseSequenceRenderer;
use App\Services\PartyGroupAssembler;

/**
 * Same thin-adapter shape as WillGenerator/PowerOfAttorneyGenerator —
 * appointment wording repeats over structured party data via markers;
 * everything else is verbatim.
 *
 * Expects the precedent to tag: "appointment_clause" ([[REPEAT:guardians AS
 * guardian]] + [[IF:guardians_act_jointly]]), and two fully verbatim clauses
 * "guardian_functions" and "revocation". Declares a party group keyed
 * "guardians" (fields: name, address, relationship).
 *
 * Section order/headings/inclusion come from ClauseSequenceRenderer: an
 * admin can override defaultSequence() below per-precedent via the
 * Structure tab (Precedent::clause_sequence) without touching this class —
 * see plan "Admin-Configurable Clause Sequence".
 *
 * LEGAL CONTENT CAVEAT: same as PowerOfAttorneyGenerator — the demo
 * precedent is drafted to plausibly match Guardianship Act 1987 (NSW)
 * structure/terminology but is NOT solicitor-reviewed; needs sign-off
 * before real client use (plan open risk #1).
 *
 * Optional front matter: if the precedent tags a [[CLAUSE:front_matter]]
 * block, it fully replaces the hardcoded opening sentence below (admin-
 * editable via re-upload, using {{answers.principal_name}} /
 * {{answers.principal_address}}) — omit it to keep today's fixed wording.
 */
class EnduringGuardianshipGenerator implements DeclaresPartyFlags, DocumentGenerator
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

        $context = [
            'answers' => $answers,
            'flags' => [
                'guardians_act_jointly' => (bool) ($answers['guardians_act_jointly'] ?? false),
            ],
            'items' => $this->parties->forRequest($documentRequest),
        ];

        $blocks = $this->clauses->has($precedent, 'front_matter')
            ? [['type' => 'raw', 'elements' => $this->clauses->render($precedent, 'front_matter', $context)]]
            : [
                [
                    'type' => 'paragraph',
                    'text' => "This Appointment of Enduring Guardian is made by {$answers['principal_name']} of {$answers['principal_address']}.",
                ],
            ];

        array_push($blocks, ...$this->sequence->render($precedent, $context, $this->defaultSequence()));

        return [
            'title' => "Appointment of Enduring Guardian by {$answers['principal_name']}",
            'blocks' => $blocks,
        ];
    }

    public function availableFlags(): array
    {
        return [
            'guardians_act_jointly' => 'True when the Guardians must act jointly rather than jointly and severally — computed from the "guardians_act_jointly" answer.',
        ];
    }

    public function availableGroups(): array
    {
        return [
            'guardians' => 'Enduring guardian(s) appointed by the principal — expects fields name, address, relationship.',
        ];
    }

    /** @return array<int, array{heading: string, kind: string, tag_or_key: string, condition: ?string}> */
    private function defaultSequence(): array
    {
        return [
            ['heading' => 'Appointment of Enduring Guardian(s)', 'kind' => 'clause', 'tag_or_key' => 'appointment_clause', 'condition' => null],
            ['heading' => 'Functions of the Guardian', 'kind' => 'clause', 'tag_or_key' => 'guardian_functions', 'condition' => null],
            ['heading' => 'Revocation', 'kind' => 'clause', 'tag_or_key' => 'revocation', 'condition' => null],
        ];
    }
}
