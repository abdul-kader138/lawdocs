<?php

namespace App\Services\Generators;

use App\Contracts\DeclaresComputedBlocks;
use App\Contracts\DeclaresPartyFlags;
use App\Contracts\DocumentGenerator;
use App\Models\DocumentRequest;
use App\Services\AnswerContextBuilder;
use App\Services\Clause\ClauseSequenceRenderer;
use App\Services\ClauseLibrary;
use App\Services\GrammarResolver;
use App\Services\PartyGroupAssembler;

/**
 * Concrete example proving the two-mechanism design: verbatim clauses pulled
 * from the precedent (revocation, executor powers — boilerplate that must
 * never vary), and marker-driven clauses (beneficiaries — repeats over
 * structured party data via [[REPEAT:beneficiaries AS beneficiary]], with
 * {{beneficiary.*}} placeholders resolved by ClauseLibrary::render()), plus
 * a generator-computed section (the executor/alternate/Public Trustee chain,
 * which stays PHP because each step's phrasing differs by position — a poor
 * fit for a uniform REPEAT — dispatched via DeclaresComputedBlocks/
 * ClauseSequenceRenderer's computed-block resolver rather than a bare
 * hardcoded block).
 *
 * Expects the precedent to tag three clauses: "revocation", "executor_powers"
 * (verbatim), and "beneficiaries_clause" (a [[REPEAT:beneficiaries]] over a
 * party group with fields name/share/gender, optionally per-item
 * [[IF:beneficiary.per_stirpes]] wording) — and to declare a party group
 * keyed "beneficiaries", read via PartyGroupAssembler.
 *
 * Section order/headings/inclusion come from ClauseSequenceRenderer: an
 * admin can override defaultSequence() below per-precedent via the
 * Structure tab (Precedent::clause_sequence) without touching this class —
 * see plan "Admin-Configurable Clause Sequence".
 *
 * Simplification, documented rather than silent: numbered top-level clauses
 * (1. Revocation, 2. Executor, ...) are rendered as headings rather than a
 * single continuous Word auto-numbered list, because a raw/verbatim clause's
 * own numbering (if any) and freshly-generated list numbering are separate
 * PHPWord numbering definitions that can't be cleanly unified into one
 * continuous counter without deliberately linking numbering IDs — out of
 * scope for this example.
 *
 * Nested multi-level numbering WITHIN a clause (a./b. sub-items, i./ii./iii.
 * sub-sub-items — e.g. an executor_powers clause authored in Word with a
 * genuine multi-level list, or a beneficiaries_clause [[REPEAT]] with a
 * per-item [[IF]] sub-item) is fully supported: depth and per-level
 * formatting are preserved automatically by ClauseMarkerParser/DocxBuilder's
 * existing capture/re-emit path, with no special-casing needed here. See
 * ClauseToDocxRoundTripTest::test_nested_multilevel_clause_preserves_depth_and_per_level_format()
 * and ::test_repeat_with_nested_if_produces_correct_multilevel_numbering_only_for_matching_items().
 *
 * Structure "show only if" conditions: every declared questionnaire field is
 * automatically usable as a top-level [[IF:...]]/condition flag (see
 * AnswerContextBuilder — a boolean field's real value, or "was this field
 * filled in" for anything else), independent of this class declaring
 * anything itself. beneficiary.per_stirpes remains the only flag this class
 * declares directly, since it's REPEAT-scoped, not a top-level answer.
 *
 * Optional front matter: if the precedent tags a [[CLAUSE:front_matter]]
 * block, it fully replaces the hardcoded opening sentence below (admin-
 * editable via re-upload, using {{answers.testator_name}} /
 * {{answers.testator_street}} / {{answers.testator_suburb}} /
 * {{answers.testator_state}}) — omit it to keep today's fixed wording.
 */
class WillGenerator implements DeclaresComputedBlocks, DeclaresPartyFlags, DocumentGenerator
{
    public function __construct(
        private readonly ClauseSequenceRenderer $sequence,
        private readonly GrammarResolver $grammar,
        private readonly PartyGroupAssembler $parties,
        private readonly ClauseLibrary $clauses,
        private readonly AnswerContextBuilder $answerContext,
    ) {}

    public function generate(DocumentRequest $documentRequest): array
    {
        $precedent = $documentRequest->precedent;
        $answers = $documentRequest->answers ?? [];
        $built = $this->answerContext->build($answers, array_keys($precedent->questionnaireFieldsConfig()));

        $context = [
            'answers' => $built['answers'],
            'flags' => $built['flags'],
            'items' => $this->parties->forRequest($documentRequest),
        ];

        $blocks = $this->clauses->has($precedent, 'front_matter')
            ? [['type' => 'raw', 'elements' => $this->clauses->render($precedent, 'front_matter', $context)]]
            : [
                [
                    'type' => 'paragraph',
                    'text' => "This is the last will and testament of {$answers['testator_name']} of "
                        ."{$answers['testator_street']}, {$answers['testator_suburb']} in the {$answers['testator_state']}.",
                ],
            ];

        array_push($blocks, ...$this->sequence->render(
            $precedent,
            $context,
            $this->defaultSequence(),
            fn (string $key, array $context) => match ($key) {
                'executor_paragraph' => ['type' => 'paragraph', 'text' => $this->executorParagraph($context['answers'])],
            },
        ));

        return [
            'title' => "Last Will and Testament of {$answers['testator_name']}",
            'blocks' => $blocks,
        ];
    }

    public function availableFlags(): array
    {
        return [
            'beneficiary.per_stirpes' => 'Inside [[REPEAT:beneficiaries]] only — true when this beneficiary row has per-stirpes substitution enabled.',
        ];
    }

    public function availableGroups(): array
    {
        return [
            'beneficiaries' => 'Beneficiaries of the estate — expects fields name, share, gender (and, if the precedent enables it, per_stirpes).',
        ];
    }

    public function availableComputedBlocks(): array
    {
        return [
            'executor_paragraph' => 'The executor/alternate/Public Trustee appointment paragraph — computed from testator_name/executor_name/alternate_executor_name and resolved pronoun grammar. Not expressible as a clause tag.',
        ];
    }

    /** @return array<int, array{heading: string, kind: string, tag_or_key: string, condition: ?string}> */
    private function defaultSequence(): array
    {
        return [
            ['heading' => 'Revocation', 'kind' => 'clause', 'tag_or_key' => 'revocation', 'condition' => null],
            ['heading' => 'Executor', 'kind' => 'computed', 'tag_or_key' => 'executor_paragraph', 'condition' => null],
            ['heading' => 'Beneficiaries', 'kind' => 'clause', 'tag_or_key' => 'beneficiaries_clause', 'condition' => null],
            ['heading' => 'Executor Powers', 'kind' => 'clause', 'tag_or_key' => 'executor_powers', 'condition' => null],
        ];
    }

    private function executorParagraph(array $answers): string
    {
        $subject = $this->grammar->pronoun($answers['executor_gender'] ?? 'entity', 'subject');

        $text = "I appoint {$answers['executor_name']} as my executor.";

        if (! empty($answers['alternate_executor_name'])) {
            $alternateSubject = $this->grammar->pronoun($answers['alternate_executor_gender'] ?? 'entity', 'subject');

            $text .= " If {$subject} is unable to act or continue to act as my executor then I appoint "
                ."{$answers['alternate_executor_name']} as my executor, provided that if {$alternateSubject} "
                .'does not survive me then I appoint the Public Trustee as my executor.';
        } else {
            $text .= " If {$subject} is unable to act or continue to act as my executor then I appoint "
                .'the Public Trustee as my executor.';
        }

        return $text;
    }
}
