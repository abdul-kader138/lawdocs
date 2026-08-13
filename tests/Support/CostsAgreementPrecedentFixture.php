<?php

namespace Tests\Support;

use App\Models\DocumentRequest;
use App\Models\Precedent;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Shared marker-bearing Costs Agreement precedent builder for tests —
 * mirrors WillPrecedentFixture/PowerOfAttorneyPrecedentFixture's shape: a
 * fresh, lean PhpWord-built docx (not the real, fully-transcribed seeder
 * fixture in database/seeders/fixtures/), so tests stay isolated from
 * content changes there. All 26 clause tags CostsAgreementGenerator's
 * defaultSequence() expects are present (a missing tag would throw
 * ClauseNotFoundException on generate()); most carry a single short line —
 * real content only where a test needs to assert something specific:
 * work_items [[REPEAT]], both rate tables, both GST [[IF]] clauses, and the
 * trust_money placeholder.
 */
trait CostsAgreementPrecedentFixture
{
    private function makeCostsAgreementPrecedent(array $overrides = [], bool $includeFrontMatter = false): Precedent
    {
        Storage::fake('local');

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        $clause = function (string $tag, callable $body) use ($section) {
            $section->addText("[[CLAUSE:{$tag}]]");
            $body($section);
            $section->addText('[[/CLAUSE]]');
        };

        if ($includeFrontMatter) {
            $clause('front_matter', function ($s) {
                $s->addText('CUSTOM FRONT MATTER for {{answers.client_name}}, dated {{answers.agreement_date}}.');
            });
        }

        $clause('the_work', function ($s) {
            $s->addText('[[REPEAT:work_items AS item]]');
            $s->addListItemRun(0)->addText('{{item.description}}');
            $s->addText('[[/REPEAT]]');
            $s->addText('The person responsible for undertaking the work will be {{answers.lawyer_name}}.');
        });

        $clause('our_fees', function ($s) {
            $table = $s->addTable();
            $table->addRow();
            $table->addCell(2000)->addText('Principal');
            $table->addCell(2000)->addText('$550.00', null, ['alignment' => Jc::RIGHT]);
        });

        $clause('disbursements', function ($s) {
            $table = $s->addTable();
            $table->addRow();
            $table->addCell(2000)->addText('Photocopying');
            $table->addCell(2000)->addText('$0.55', null, ['alignment' => Jc::RIGHT]);
        });

        $clause('billing_units_and_increases', fn ($s) => $s->addText('We will charge in six minute units.'));

        $clause('fees_estimate', function ($s) {
            $s->addText('We estimate our fees to be: {{answers.estimate_fees_expenses}}');
            $s->addText('[[IF:estimate_fees_expenses_gst]]');
            $s->addText('(inclusive of GST)');
            $s->addText('[[/IF]]');
        });

        $clause('gst', fn ($s) => $s->addText('All rates are GST exclusive unless stated otherwise.'));
        $clause('interest', fn ($s) => $s->addText('We can charge you interest at the maximum rate allowed under the Uniform Law.'));

        $clause('completion_estimate', function ($s) {
            $s->addText('We estimate that the work will be completed by {{answers.estimated_completion_date}}.');
            $s->addText('Estimate: {{answers.estimate_completion}}');
            $s->addText('[[IF:estimate_completion_gst]]');
            $s->addText('(inclusive of GST)');
            $s->addText('[[/IF]]');
        });

        $clause('billing_arrangements', fn ($s) => $s->addText('You have a right to receive a tax invoice from us.'));
        $clause('itemised_bills', fn ($s) => $s->addText('You may request an itemised bill.'));
        $clause('your_rights', fn ($s) => $s->addText('It is your right to negotiate this costs agreement with us.'));
        $clause('costs_recovery_and_disputes', fn ($s) => $s->addText('The Uniform Law governs recovery of costs.'));
        $clause('progress_reports', fn ($s) => $s->addText('You are entitled to request progress reports.'));
        $clause('substantial_change', fn ($s) => $s->addText('We will inform you of any substantial change.'));

        $clause('trust_money', function ($s) {
            $s->addText('On acceptance we require you to pay to us the sum of:');
            $s->addText('{{answers.trust_money}}', ['bold' => true], ['alignment' => Jc::RIGHT]);
        });

        $clause('authority_to_receive_moneys', fn ($s) => $s->addText('You authorise us to receive moneys on your behalf.'));
        $clause('costs_in_proceedings', fn ($s) => $s->addText('The court may order the other party to pay your costs.'));
        $clause('engaging_another_practitioner', fn ($s) => $s->addText('We may engage another legal practitioner as our agent.'));
        $clause('document_retention', fn ($s) => $s->addText('We will retain your documents for seven years.'));
        $clause('company_signatories', fn ($s) => $s->addText('A company signatory warrants they are authorised to sign.'));
        $clause('legal_aid', fn ($s) => $s->addText('We have discussed Legal Aid eligibility with you.'));
        $clause('suspension', fn ($s) => $s->addText('We can suspend work for non-payment.'));
        $clause('termination', fn ($s) => $s->addText('Either party may terminate this agreement.'));
        $clause('governing_law', fn ($s) => $s->addText('The Law of New South Wales applies.'));
        $clause('email_communication', fn ($s) => $s->addText('We may communicate with you by email.'));
        $clause('severability', fn ($s) => $s->addText('An invalid clause does not affect the rest of this agreement.'));

        $tmp = tempnam(sys_get_temp_dir(), 'costs_agreement_precedent_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/costs-agreement.docx', file_get_contents($tmp));
        @unlink($tmp);

        return Precedent::create(array_merge([
            'title' => 'Costs Agreement',
            'docx_path' => 'precedents/costs-agreement.docx',
            'generator_class' => 'costs_agreement',
            'questionnaire_fields' => [],
            'party_groups' => $this->workItemsPartyGroups(),
            'jurisdiction' => 'NSW',
            'is_active' => true,
        ], $overrides));
    }

    private function workItemsPartyGroups(): array
    {
        return [
            [
                'key' => 'work_items',
                'label' => 'Work Items',
                'role_type' => 'custom',
                'min_items' => 1,
                'max_items' => null,
                'share_field' => null,
                'supports_substitute' => false,
                'supports_per_stirpes' => false,
                'fields' => [
                    ['name' => 'description', 'label' => 'Work item description', 'type' => 'textarea', 'required' => true],
                ],
            ],
        ];
    }

    private function costsAgreementAnswers(array $overrides = []): array
    {
        return array_merge([
            'client_name' => 'Ashley Dewell',
            'lawyer_name' => 'Susan Wild',
            'estimated_completion_date' => '2 September 2026',
            'estimate_completion' => '$5,000 - $8,000',
            'estimate_completion_gst' => false,
            'estimate_fees_expenses' => '$5,000 - $8,000',
            'estimate_fees_expenses_gst' => false,
            'trust_money' => '$2,000.00',
        ], $overrides);
    }

    /** @param array<int, array<string, mixed>>|null $rows */
    private function attachWorkItems(DocumentRequest $documentRequest, ?array $rows = null): void
    {
        $rows ??= [
            ['description' => 'Preparation of initial advice'],
            ['description' => 'Attendance at mediation'],
        ];

        foreach (array_values($rows) as $position => $row) {
            $documentRequest->parties()->create([
                'group_key' => 'work_items',
                'position' => $position,
                'data' => $row,
            ]);
        }
    }
}
