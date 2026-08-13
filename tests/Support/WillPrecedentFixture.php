<?php

namespace Tests\Support;

use App\Models\DocumentRequest;
use App\Models\Precedent;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\NumberFormat;
use PhpOffice\PhpWord\Style;

/**
 * Shared real, marker-bearing will precedent builder — used anywhere a test
 * needs an actual Precedent that WillGenerator can successfully generate
 * from (i.e. it must have the 'revocation' and 'executor_powers' clause tags).
 * executor_powers is a genuine nested list (a./b. with i./ii./iii. under b.),
 * matching the original worked example's clause 5 exactly.
 *
 * beneficiaries_clause is likewise genuinely conditional: the per-stirpes
 * proviso is a nested [[IF:beneficiary.per_stirpes]] sub-item (1./1.a.),
 * not unconditional text — see willBeneficiaryRows() for the default rows
 * this fixture pairs it with.
 */
trait WillPrecedentFixture
{
    private function makeWillPrecedent(array $overrides = [], bool $includeFrontMatter = false): Precedent
    {
        Storage::fake('local');

        $phpWord = new PhpWord;
        Style::addNumberingStyle('test_executor_powers_levels', [
            'type' => 'multilevel',
            'levels' => [
                ['format' => NumberFormat::LOWER_LETTER, 'text' => '%1.', 'left' => 720, 'hanging' => 360, 'tabPos' => 720],
                ['format' => NumberFormat::LOWER_ROMAN, 'text' => '%2.', 'left' => 1440, 'hanging' => 360, 'tabPos' => 1440],
            ],
        ]);
        Style::addNumberingStyle('test_beneficiary_levels', [
            'type' => 'multilevel',
            'levels' => [
                ['format' => NumberFormat::DECIMAL, 'text' => '%1.', 'left' => 720, 'hanging' => 360, 'tabPos' => 720],
                ['format' => NumberFormat::LOWER_LETTER, 'text' => '%2.', 'left' => 1440, 'hanging' => 360, 'tabPos' => 1440],
            ],
        ]);

        $section = $phpWord->addSection();
        if ($includeFrontMatter) {
            $section->addText('[[CLAUSE:front_matter]]');
            $section->addText('CUSTOM FRONT MATTER for {{answers.testator_name}}.');
            $section->addText('[[/CLAUSE]]');
        }
        $section->addText('[[CLAUSE:revocation]]');
        $section->addText('I revoke all prior wills and testamentary acts made by me.', ['bold' => true]);
        $section->addText('[[/CLAUSE]]');
        $section->addText('[[CLAUSE:executor_powers]]');
        $section->addListItemRun(0, 'test_executor_powers_levels')->addText('exercise any powers given to them by law;');
        $section->addListItemRun(0, 'test_executor_powers_levels')->addText('exercise the powers of an executor for sale in respect of any assets in my estate:');
        $section->addListItemRun(1, 'test_executor_powers_levels')->addText('without being liable for any loss caused by so doing, postpone sale;');
        $section->addListItemRun(1, 'test_executor_powers_levels')->addText('sell by public auction or private sale, for cash or on credit.');
        $section->addText('[[/CLAUSE]]');
        $section->addText('[[CLAUSE:beneficiaries_clause]]');
        $section->addText('[[REPEAT:beneficiaries AS beneficiary]]');
        $section->addListItemRun(0, 'test_beneficiary_levels')->addText('I give my {{beneficiary.share}}% of my estate to {{beneficiary.name}}.');
        $section->addText('[[IF:beneficiary.per_stirpes]]');
        $section->addListItemRun(1, 'test_beneficiary_levels')->addText(
            'If {{beneficiary.pronoun_subject}} does not survive me, leaving children that do survive me, '
                .'then those children shall take equally the share that would have been received by {{beneficiary.pronoun_object}}.'
        );
        $section->addText('[[/IF]]');
        $section->addText('[[/REPEAT]]');
        $section->addText('[[/CLAUSE]]');

        $tmp = tempnam(sys_get_temp_dir(), 'will_precedent_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/will.docx', file_get_contents($tmp));
        @unlink($tmp);

        return Precedent::create(array_merge([
            'title' => 'Last Will and Testament',
            'category' => 'will',
            'docx_path' => 'precedents/will.docx',
            'generator_class' => 'will',
            'questionnaire_fields' => [],
            'party_groups' => $this->willBeneficiariesPartyGroups(),
            'jurisdiction' => 'NSW',
            'is_active' => true,
        ], $overrides));
    }

    private function willBeneficiariesPartyGroups(): array
    {
        return [
            [
                'key' => 'beneficiaries',
                'label' => 'Beneficiaries',
                'role_type' => 'beneficiary',
                'min_items' => 1,
                'max_items' => null,
                'share_field' => 'share',
                'supports_substitute' => true,
                'supports_per_stirpes' => true,
                'fields' => [
                    ['name' => 'name', 'label' => "Beneficiary's Full Name", 'type' => 'text', 'required' => true],
                    ['name' => 'share', 'label' => 'Share of Estate (%)', 'type' => 'number', 'required' => true],
                    ['name' => 'gender', 'label' => 'Gender', 'type' => 'select', 'required' => true, 'options' => ['male' => 'Male', 'female' => 'Female']],
                ],
            ],
        ];
    }

    private function willAnswers(array $overrides = []): array
    {
        return array_merge([
            'testator_name' => 'Ashley Dewell',
            'testator_street' => '1 First Street',
            'testator_suburb' => 'Sydney',
            'testator_state' => 'State of New South Wales',
            'testator_gender' => 'male',
            'executor_name' => 'Alfred Smith',
            'executor_gender' => 'male',
        ], $overrides);
    }

    /** Default beneficiary rows: two 50/50 beneficiaries, both with per-stirpes substitution enabled. */
    private function willBeneficiaryRows(): array
    {
        return [
            ['name' => 'Alfred Smith', 'share' => 50, 'gender' => 'male', 'per_stirpes' => true],
            ['name' => 'Bernadette Smith', 'share' => 50, 'gender' => 'female', 'per_stirpes' => true],
        ];
    }

    /** Persists $rows as DocumentRequestParty rows under the "beneficiaries" group. */
    private function attachBeneficiaries(DocumentRequest $documentRequest, ?array $rows = null): void
    {
        foreach (array_values($rows ?? $this->willBeneficiaryRows()) as $position => $row) {
            $documentRequest->parties()->create([
                'group_key' => 'beneficiaries',
                'position' => $position,
                'data' => $row,
            ]);
        }
    }
}
