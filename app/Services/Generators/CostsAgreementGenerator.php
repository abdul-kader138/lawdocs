<?php

namespace App\Services\Generators;

use App\Contracts\DeclaresPartyFlags;
use App\Contracts\DocumentGenerator;
use App\Models\DocumentRequest;
use App\Services\ClauseLibrary;
use App\Services\Clause\ClauseSequenceRenderer;
use App\Services\PartyGroupAssembler;

/**
 * Ported from the firm's real prior costs agreement (a raw hand-coded
 * PHPWord script, formerly this repo's TU_CostAgreement.php) rather than
 * demo content — every clause below is transcribed verbatim from that
 * source. Reorganized from one continuous ~900-line table into ~26
 * independently-editable named clause sections (see defaultSequence()) so
 * wording/order/inclusion can be managed via the admin Structure tab and
 * .docx re-upload instead of a developer editing PHP — the exact capability
 * gap that source file represented. The two genuinely tabular sections
 * (hourly rate schedule, disbursement fee schedule) use real [[CLAUSE]]
 * tables (see ClauseMarkerParser::captureTable()); everything else is
 * ordinary marker-driven clause text.
 *
 * Three concrete improvements over the source, not just a reorganization:
 *   - The source's hardcoded lawyer-initials PHP switch (PMM/TC/SJN/AGD/JC)
 *     becomes the `lawyer_name` questionnaire Select — an admin edits the
 *     roster via the Questionnaire Fields tab, no code.
 *   - The source's hardcoded 10-item WorkDescription01..10 cap becomes the
 *     `work_items` party group — an unlimited "Add row" list, the same
 *     mechanism Beneficiaries/Attorneys/Guardians already use.
 *   - The source's hardcoded, stale completion date ("2 September 2024",
 *     baked into clause15's literal text) becomes a real
 *     `estimated_completion_date` field.
 *
 * No computed blocks needed — unlike WillGenerator's executor logic, every
 * section here is expressible as a plain or marker-driven clause, which is
 * itself a useful proof point for how much of a real document this
 * architecture covers without any generator-authored branching.
 *
 * Expects the precedent to tag: the_work ([[REPEAT:work_items AS item]]),
 * our_fees (a table, no markers), disbursements (a table, no markers),
 * billing_units_and_increases, fees_estimate ([[IF:estimate_fees_expenses_gst]]),
 * gst, interest, completion_estimate ([[IF:estimate_completion_gst]]),
 * billing_arrangements, itemised_bills, your_rights,
 * costs_recovery_and_disputes, progress_reports, substantial_change,
 * trust_money, authority_to_receive_moneys, costs_in_proceedings,
 * engaging_another_practitioner, document_retention, company_signatories,
 * legal_aid, suspension, termination, governing_law, email_communication,
 * severability — all fully verbatim except where noted. Declares a party
 * group keyed "work_items" (field: description).
 *
 * LEGAL CONTENT CAVEAT: unlike the Will/PoA/Guardianship demo precedents,
 * this wording is the firm's own real prior content, not invented — but it
 * was mechanically reorganized (verbatim per-clause, but table layout and
 * some paragraph groupings changed from the source's single continuous
 * table) and the stale hardcoded date was replaced with a live field. It
 * should still get a solicitor's once-over before real client use, same as
 * every other precedent in this system.
 *
 * Optional front matter: if the precedent tags a [[CLAUSE:front_matter]]
 * block, it fully replaces the hardcoded BETWEEN/AND/DATED + offer/
 * acceptance boilerplate below (admin-editable via re-upload, using
 * {{answers.client_name}} and {{answers.agreement_date}} — the latter is
 * injected at generation time, not a real questionnaire field) — omit it to
 * keep today's fixed wording.
 */
class CostsAgreementGenerator implements DeclaresPartyFlags, DocumentGenerator
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
        $answers['agreement_date'] = now()->format('j F Y');

        $context = [
            'answers' => $answers,
            'flags' => [
                'estimate_completion_gst' => (bool) ($answers['estimate_completion_gst'] ?? false),
                'estimate_fees_expenses_gst' => (bool) ($answers['estimate_fees_expenses_gst'] ?? false),
            ],
            'items' => $this->parties->forRequest($documentRequest),
        ];

        $blocks = $this->clauses->has($precedent, 'front_matter')
            ? [['type' => 'raw', 'elements' => $this->clauses->render($precedent, 'front_matter', $context)]]
            : [
                // DocxBuilder already renders $result['title'] below as the document's
                // H1 — no separate "COSTS AGREEMENT" heading block needed here.
                ['type' => 'paragraph', 'text' => "BETWEEN\tMCPHEE KELSHAW PTY LTD trading under registered business name McPhee Kelshaw (McPhee Kelshaw)"],
                ['type' => 'paragraph', 'text' => "AND\t{$answers['client_name']} (\"you\", \"your\")"],
                ['type' => 'paragraph', 'text' => "DATED\t{$answers['agreement_date']}"],
                [
                    'type' => 'paragraph',
                    'text' => 'This document is an offer to provide legal services to you and constitutes our costs agreement '
                        .'and disclosure under the Legal Professional Uniform Law (NSW) ("the Uniform Law").',
                ],
                ['type' => 'paragraph', 'text' => 'You are entitled to obtain independent legal advice before entering into this agreement.'],
                ['type' => 'paragraph', 'text' => 'You can negotiate the terms of this agreement with us or accept it as it is.'],
                ['type' => 'paragraph', 'text' => 'You can accept this agreement by:'],
                ['type' => 'list_item', 'text' => 'Signing this agreement and returning it to us;'],
                ['type' => 'list_item', 'text' => 'Communicating to us either verbally or in writing that you accept this offer;'],
                ['type' => 'list_item', 'text' => 'Requesting us to undertake any of the work.'],
                [
                    'type' => 'paragraph',
                    'text' => 'If you do not accept this agreement within seven days then we can withdraw our offer and charge you '
                        .'for the work done to the date we withdraw that offer. That work will be charged on the same basis as '
                        .'set out in this agreement and disclosure.',
                ],
                ['type' => 'paragraph', 'text' => 'We can refuse to commence work until a copy of this offer is signed by you and returned to us.'],
            ];

        array_push($blocks, ...$this->sequence->render($precedent, $context, $this->defaultSequence()));

        return [
            'title' => "Costs Agreement with {$answers['client_name']}",
            'blocks' => $blocks,
        ];
    }

    public function availableFlags(): array
    {
        return [
            'estimate_completion_gst' => 'True when the completion-of-work estimate should display "(inc. GST)" — computed from the "estimate_completion_gst" answer.',
            'estimate_fees_expenses_gst' => 'True when the fees-and-expenses estimate should display "(inc. GST)" — computed from the "estimate_fees_expenses_gst" answer.',
        ];
    }

    public function availableGroups(): array
    {
        return [
            'work_items' => 'The itemised description of work the client has engaged the firm to do — expects field description.',
        ];
    }

    /** @return array<int, array{heading: string, kind: string, tag_or_key: string, condition: ?string}> */
    private function defaultSequence(): array
    {
        $clause = fn (string $heading, string $tag) => ['heading' => $heading, 'kind' => 'clause', 'tag_or_key' => $tag, 'condition' => null];

        return [
            $clause('The Work', 'the_work'),
            $clause('Our Fees', 'our_fees'),
            $clause('Disbursements', 'disbursements'),
            $clause('Billing Units and Rate Increases', 'billing_units_and_increases'),
            $clause('Estimate of Fees and Expenses', 'fees_estimate'),
            $clause('GST', 'gst'),
            $clause('Interest', 'interest'),
            $clause('Estimate of Completion of the Work', 'completion_estimate'),
            $clause('Billing Arrangements', 'billing_arrangements'),
            $clause('Itemised Bills', 'itemised_bills'),
            $clause('Your Rights', 'your_rights'),
            $clause('Recovery of Costs and Dispute Resolution', 'costs_recovery_and_disputes'),
            $clause('Progress Reports', 'progress_reports'),
            $clause('Substantial Change of Circumstances', 'substantial_change'),
            $clause('Trust Money', 'trust_money'),
            $clause('Authority to Receive and Transfer Moneys', 'authority_to_receive_moneys'),
            $clause('Costs in Court Proceedings', 'costs_in_proceedings'),
            $clause('Engagement of Another Legal Practitioner', 'engaging_another_practitioner'),
            $clause('Document Retention', 'document_retention'),
            $clause('Company Signatories', 'company_signatories'),
            $clause('Legal Aid', 'legal_aid'),
            $clause('Suspension of Work', 'suspension'),
            $clause('Termination', 'termination'),
            $clause('Governing Law', 'governing_law'),
            $clause('Email Communication', 'email_communication'),
            $clause('Severability', 'severability'),
        ];
    }
}
