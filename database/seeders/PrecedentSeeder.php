<?php

namespace Database\Seeders;

use App\Models\Precedent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class PrecedentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFromFixture(
            fixture: 'will-precedent.docx',
            title: 'Last Will and Testament',
            category: 'will',
            generatorClass: 'will',
            description: 'Standard NSW last will and testament template. Tagged clauses: revocation, executor_powers, beneficiaries_clause.',
            fields: [
                ['name' => 'testator_name', 'label' => "Testator's Full Name", 'type' => 'text', 'required' => true, 'description' => 'The person making the will.'],
                ['name' => 'testator_dob', 'label' => "Testator's Date of Birth", 'type' => 'date', 'required' => false, 'description' => 'Optional — used only to surface a minimum-age warning on the Review step.'],
                ['name' => 'testator_street', 'label' => 'Testator Street Address', 'type' => 'text', 'required' => true, 'description' => 'e.g. 1 First Street'],
                ['name' => 'testator_suburb', 'label' => 'Testator Suburb/City', 'type' => 'text', 'required' => true, 'description' => 'e.g. Sydney'],
                ['name' => 'testator_state', 'label' => 'Testator State (full text for the document)', 'type' => 'text', 'required' => true, 'description' => 'e.g. "State of New South Wales" — typed as it should read in the document.'],
                ['name' => 'testator_gender', 'label' => "Testator's Gender", 'type' => 'select', 'required' => true, 'description' => 'Used for pronoun agreement.', 'options' => ['male' => 'Male', 'female' => 'Female']],
                ['name' => 'executor_name', 'label' => "Executor's Full Name", 'type' => 'text', 'required' => true, 'description' => 'Who will administer the estate.'],
                ['name' => 'executor_gender', 'label' => "Executor's Gender", 'type' => 'select', 'required' => true, 'description' => 'Used for pronoun agreement.', 'options' => ['male' => 'Male', 'female' => 'Female']],
                ['name' => 'alternate_executor_name', 'label' => 'Alternate Executor Name (optional)', 'type' => 'text', 'required' => false, 'description' => 'Leave blank to skip straight to the Public Trustee fallback.'],
                ['name' => 'alternate_executor_gender', 'label' => "Alternate Executor's Gender", 'type' => 'select', 'required' => false, 'description' => 'Used for pronoun agreement.', 'options' => ['male' => 'Male', 'female' => 'Female']],
            ],
            partyGroups: [
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
                        ['name' => 'name', 'label' => "Beneficiary's Full Name", 'type' => 'text', 'required' => true, 'description' => ''],
                        ['name' => 'share', 'label' => 'Share of Estate (%)', 'type' => 'number', 'required' => true, 'description' => ''],
                        ['name' => 'gender', 'label' => 'Gender', 'type' => 'select', 'required' => true, 'description' => 'Used for pronoun agreement.', 'options' => ['male' => 'Male', 'female' => 'Female']],
                    ],
                ],
            ],
        );

        $this->seedFromFixture(
            fixture: 'poa-precedent.docx',
            title: 'Power of Attorney',
            category: 'power_of_attorney',
            generatorClass: 'power_of_attorney',
            description: 'NSW general/enduring power of attorney template (Powers of Attorney Act 2003 (NSW)). Demo content — needs solicitor review before real client use. Tagged clauses: appointment_clause, enduring_notice, general_powers, revocation.',
            fields: [
                ['name' => 'principal_name', 'label' => "Principal's Full Name", 'type' => 'text', 'required' => true, 'description' => 'The person granting the power.'],
                ['name' => 'principal_dob', 'label' => "Principal's Date of Birth", 'type' => 'date', 'required' => false, 'description' => 'Optional — used only to surface a minimum-age warning on the Review step.'],
                ['name' => 'principal_address', 'label' => "Principal's Address", 'type' => 'text', 'required' => true, 'description' => ''],
                ['name' => 'is_enduring', 'label' => 'Enduring Power of Attorney?', 'type' => 'boolean', 'required' => true, 'description' => 'If yes, continues to have effect after the principal loses capacity, and the enduring notice clause is included.'],
                ['name' => 'attorneys_act_jointly', 'label' => 'Attorneys must act jointly?', 'type' => 'boolean', 'required' => true, 'description' => 'If no, attorneys may act jointly and severally. Irrelevant with a single attorney.'],
            ],
            partyGroups: [
                [
                    'key' => 'attorneys',
                    'label' => 'Attorneys',
                    'role_type' => 'attorney',
                    'min_items' => 1,
                    'max_items' => null,
                    'share_field' => null,
                    'supports_substitute' => false,
                    'supports_per_stirpes' => false,
                    'fields' => [
                        ['name' => 'name', 'label' => "Attorney's Full Name", 'type' => 'text', 'required' => true, 'description' => ''],
                        ['name' => 'address', 'label' => "Attorney's Address", 'type' => 'text', 'required' => true, 'description' => ''],
                        ['name' => 'relationship', 'label' => 'Relationship to Principal', 'type' => 'text', 'required' => false, 'description' => ''],
                    ],
                ],
            ],
        );

        $this->seedFromFixture(
            fixture: 'enduring-guardianship-precedent.docx',
            title: 'Enduring Guardianship',
            category: 'enduring_guardianship',
            generatorClass: 'enduring_guardianship',
            description: 'NSW appointment of enduring guardian template (Guardianship Act 1987 (NSW)). Demo content — needs solicitor review before real client use. Tagged clauses: appointment_clause, guardian_functions, revocation.',
            fields: [
                ['name' => 'principal_name', 'label' => "Principal's Full Name", 'type' => 'text', 'required' => true, 'description' => 'The person appointing the guardian.'],
                ['name' => 'principal_dob', 'label' => "Principal's Date of Birth", 'type' => 'date', 'required' => false, 'description' => 'Optional — used only to surface a minimum-age warning on the Review step.'],
                ['name' => 'principal_address', 'label' => "Principal's Address", 'type' => 'text', 'required' => true, 'description' => ''],
                ['name' => 'guardians_act_jointly', 'label' => 'Guardians must act jointly?', 'type' => 'boolean', 'required' => true, 'description' => 'If no, guardians may act jointly and severally. Irrelevant with a single guardian.'],
            ],
            partyGroups: [
                [
                    'key' => 'guardians',
                    'label' => 'Guardians',
                    'role_type' => 'guardian',
                    'min_items' => 1,
                    'max_items' => null,
                    'share_field' => null,
                    'supports_substitute' => false,
                    'supports_per_stirpes' => false,
                    'fields' => [
                        ['name' => 'name', 'label' => "Guardian's Full Name", 'type' => 'text', 'required' => true, 'description' => ''],
                        ['name' => 'address', 'label' => "Guardian's Address", 'type' => 'text', 'required' => true, 'description' => ''],
                        ['name' => 'relationship', 'label' => 'Relationship to Principal', 'type' => 'text', 'required' => false, 'description' => ''],
                    ],
                ],
            ],
        );

        $this->seedFromFixture(
            fixture: 'costs-agreement-precedent.docx',
            title: 'Costs Agreement',
            category: 'costs_agreement',
            generatorClass: 'costs_agreement',
            description: 'NSW costs agreement and disclosure (Legal Profession Uniform Law). Content ported verbatim from the firm\'s own prior costs agreement — needs solicitor review post-migration before real client use. Tagged clauses: the_work, our_fees, disbursements, billing_units_and_increases, fees_estimate, gst, interest, completion_estimate, billing_arrangements, itemised_bills, your_rights, costs_recovery_and_disputes, progress_reports, substantial_change, trust_money, authority_to_receive_moneys, costs_in_proceedings, engaging_another_practitioner, document_retention, company_signatories, legal_aid, suspension, termination, governing_law, email_communication, severability.',
            fields: [
                ['name' => 'client_name', 'label' => "Client's Full Name", 'type' => 'text', 'required' => true, 'description' => 'The person or entity engaging the firm.'],
                ['name' => 'lawyer_name', 'label' => 'Responsible Lawyer', 'type' => 'select', 'required' => true, 'description' => 'Who is responsible for the work — admin-editable roster, replaces a hardcoded initials lookup from the original system.', 'options' => [
                    'Paul McPhee' => 'Paul McPhee',
                    'Trevor Cork' => 'Trevor Cork',
                    'Steven Nicholson' => 'Steven Nicholson',
                    'Ashley Dewell' => 'Ashley Dewell',
                    'Justine Cole' => 'Justine Cole',
                    'Susan Wild' => 'Susan Wild',
                ]],
                ['name' => 'estimated_completion_date', 'label' => 'Estimated Completion Date', 'type' => 'date', 'required' => true, 'description' => 'When the work is estimated to be complete.'],
                ['name' => 'estimate_completion', 'label' => 'Estimate of Completion Amount', 'type' => 'text', 'required' => true, 'description' => 'e.g. "$5,000 - $8,000"'],
                ['name' => 'estimate_completion_gst', 'label' => 'Completion estimate is inclusive of GST?', 'type' => 'boolean', 'required' => false, 'description' => ''],
                ['name' => 'estimate_fees_expenses', 'label' => 'Estimate of Fees and Expenses Amount', 'type' => 'text', 'required' => true, 'description' => 'e.g. "$5,000 - $8,000"'],
                ['name' => 'estimate_fees_expenses_gst', 'label' => 'Fees and expenses estimate is inclusive of GST?', 'type' => 'boolean', 'required' => false, 'description' => ''],
                ['name' => 'trust_money', 'label' => 'Trust Money Required on Acceptance', 'type' => 'text', 'required' => true, 'description' => 'e.g. "$2,000.00"'],
            ],
            partyGroups: [
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
                        ['name' => 'description', 'label' => 'Work item description', 'type' => 'textarea', 'required' => true, 'description' => ''],
                    ],
                ],
            ],
        );
    }

    private function seedFromFixture(string $fixture, string $title, string $category, ?string $generatorClass, string $description, array $fields, array $partyGroups = []): void
    {
        $fixturePath = database_path("seeders/fixtures/{$fixture}");
        $storedPath = "precedents/{$fixture}";

        // The database may have been imported without the private storage
        // directory. Repair the seeded precedent file even when its row
        // already exists, otherwise regeneration fails on the live server.
        if (! Storage::disk('local')->exists($storedPath)) {
            $stored = Storage::disk('local')->put($storedPath, file_get_contents($fixturePath));

            if (! $stored) {
                throw new \RuntimeException("Unable to store precedent fixture: {$storedPath}");
            }
        }

        if (Precedent::where('title', $title)->exists()) {
            return;
        }

        Precedent::create([
            'title' => $title,
            'category' => $category,
            'generator_class' => $generatorClass,
            'description' => $description,
            'docx_path' => $storedPath,
            'docx_original_filename' => $fixture,
            'questionnaire_fields' => $fields,
            'party_groups' => $partyGroups,
            'is_active' => true,
        ]);
    }
}
