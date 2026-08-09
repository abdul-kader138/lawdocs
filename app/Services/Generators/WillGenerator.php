<?php

namespace App\Services\Generators;

use App\Contracts\DocumentGenerator;
use App\Models\Precedent;
use App\Services\ClauseLibrary;
use App\Services\GrammarResolver;

/**
 * Concrete example proving the two-mechanism design: verbatim clauses pulled
 * from the precedent (revocation, executor powers — boilerplate that must
 * never vary), and generated text (executor/alternate/Public Trustee chain,
 * per-beneficiary survivorship) using GrammarResolver for pronoun agreement.
 *
 * Expects the precedent to tag two clauses: "revocation" and "executor_powers".
 *
 * Simplification, documented rather than silent: numbered top-level clauses
 * (1. Revocation, 2. Executor, ...) are rendered as headings rather than a
 * single continuous Word auto-numbered list, because a raw/verbatim clause's
 * own numbering (if any) and freshly-generated list numbering are separate
 * PHPWord numbering definitions that can't be cleanly unified into one
 * continuous counter without deliberately linking numbering IDs — out of
 * scope for this example. Nested multi-level clause numbering (a./i./ii.)
 * is a documented v1.1 gap per the implementation plan.
 */
class WillGenerator implements DocumentGenerator
{
    public function __construct(
        private readonly ClauseLibrary $clauses,
        private readonly GrammarResolver $grammar,
    ) {}

    public function generate(Precedent $precedent, array $answers): array
    {
        $blocks = [];

        $blocks[] = [
            'type' => 'paragraph',
            'text' => "This is the last will and testament of {$answers['testator_name']} of "
                . "{$answers['testator_street']}, {$answers['testator_suburb']} in the {$answers['testator_state']}.",
        ];

        $blocks[] = ['type' => 'heading', 'level' => 2, 'text' => '1. Revocation'];
        $blocks[] = ['type' => 'raw', 'elements' => $this->clauses->get($precedent, 'revocation')];

        $blocks[] = ['type' => 'heading', 'level' => 2, 'text' => '2. Executor'];
        $blocks[] = ['type' => 'paragraph', 'text' => $this->executorParagraph($answers)];

        $blocks[] = ['type' => 'heading', 'level' => 2, 'text' => '3. Beneficiaries'];
        foreach ($this->beneficiaries($answers) as $beneficiary) {
            $blocks[] = ['type' => 'list_item', 'list_type' => 'number', 'text' => $this->beneficiaryParagraph($beneficiary)];
        }

        $blocks[] = ['type' => 'heading', 'level' => 2, 'text' => '4. Executor Powers'];
        $blocks[] = ['type' => 'raw', 'elements' => $this->clauses->get($precedent, 'executor_powers')];

        return [
            'title'  => "Last Will and Testament of {$answers['testator_name']}",
            'blocks' => $blocks,
        ];
    }

    private function executorParagraph(array $answers): string
    {
        $subject = $this->grammar->pronoun($answers['executor_gender'] ?? 'entity', 'subject');

        $text = "I appoint {$answers['executor_name']} as my executor.";

        if (! empty($answers['alternate_executor_name'])) {
            $alternateSubject = $this->grammar->pronoun($answers['alternate_executor_gender'] ?? 'entity', 'subject');

            $text .= " If {$subject} is unable to act or continue to act as my executor then I appoint "
                . "{$answers['alternate_executor_name']} as my executor, provided that if {$alternateSubject} "
                . 'does not survive me then I appoint the Public Trustee as my executor.';
        } else {
            $text .= " If {$subject} is unable to act or continue to act as my executor then I appoint "
                . 'the Public Trustee as my executor.';
        }

        return $text;
    }

    private function beneficiaryParagraph(array $beneficiary): string
    {
        $subject = $this->grammar->pronoun($beneficiary['gender'], 'subject');
        $object  = $this->grammar->pronoun($beneficiary['gender'], 'object');

        return "I give my {$beneficiary['share']}% of my estate to {$beneficiary['name']} provided however that "
            . "if {$subject} does not survive me, leaving children that do survive me, then those children shall "
            . "take equally the share that would have been received by {$object}.";
    }

    /**
     * Format documented to staff on the questionnaire field itself: one
     * beneficiary per line, "Name - Share% - Gender".
     *
     * @return array<int, array{name: string, share: string, gender: string}>
     */
    private function beneficiaries(array $answers): array
    {
        $raw = $answers['beneficiaries'] ?? '';
        $lines = array_filter(array_map('trim', explode("\n", (string) $raw)));

        return array_values(array_map(function (string $line) {
            $parts = array_map('trim', explode('-', $line));

            return [
                'name'   => $parts[0] ?? '',
                'share'  => $parts[1] ?? '',
                'gender' => $parts[2] ?? 'entity',
            ];
        }, $lines));
    }
}
