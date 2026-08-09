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
            description: 'Standard last will and testament template. Tagged clauses: revocation, executor_powers.',
            fields: [
                ['name' => 'testator_name', 'label' => "Testator's Full Name", 'type' => 'text', 'required' => true, 'description' => 'The person making the will.'],
                ['name' => 'testator_street', 'label' => 'Testator Street Address', 'type' => 'text', 'required' => true, 'description' => 'e.g. 1 First Street'],
                ['name' => 'testator_suburb', 'label' => 'Testator Suburb/City', 'type' => 'text', 'required' => true, 'description' => 'e.g. Sydney'],
                ['name' => 'testator_state', 'label' => 'Testator State (full text for the document)', 'type' => 'text', 'required' => true, 'description' => 'e.g. "State of New South Wales" — typed as it should read in the document.'],
                ['name' => 'testator_gender', 'label' => "Testator's Gender", 'type' => 'select', 'required' => true, 'description' => 'Used for pronoun agreement.', 'options' => ['male' => 'Male', 'female' => 'Female']],
                ['name' => 'executor_name', 'label' => "Executor's Full Name", 'type' => 'text', 'required' => true, 'description' => 'Who will administer the estate.'],
                ['name' => 'executor_gender', 'label' => "Executor's Gender", 'type' => 'select', 'required' => true, 'description' => 'Used for pronoun agreement.', 'options' => ['male' => 'Male', 'female' => 'Female']],
                ['name' => 'alternate_executor_name', 'label' => 'Alternate Executor Name (optional)', 'type' => 'text', 'required' => false, 'description' => 'Leave blank to skip straight to the Public Trustee fallback.'],
                ['name' => 'alternate_executor_gender', 'label' => "Alternate Executor's Gender", 'type' => 'select', 'required' => false, 'description' => 'Used for pronoun agreement.', 'options' => ['male' => 'Male', 'female' => 'Female']],
                ['name' => 'beneficiaries', 'label' => 'Beneficiaries', 'type' => 'textarea', 'required' => true, 'description' => 'One per line, format: Name - Share% - Gender (male/female). e.g. "Alfred Smith - 50 - male".'],
            ],
        );

        $this->seedFromFixture(
            fixture: 'poa-precedent.docx',
            title: 'Power of Attorney',
            category: 'power_of_attorney',
            generatorClass: null, // no PowerOfAttorneyGenerator built yet — a developer adds one and assigns it here.
            description: 'General power of attorney template. Not yet wired to a generator.',
            fields: [
                ['name' => 'principal_name', 'label' => "Principal's Full Name", 'type' => 'text', 'required' => true, 'description' => 'The person granting the power.'],
                ['name' => 'attorney_name', 'label' => "Attorney-in-Fact's Full Name", 'type' => 'text', 'required' => true, 'description' => 'The person receiving the power.'],
                ['name' => 'scope', 'label' => 'Scope of Authority', 'type' => 'textarea', 'required' => true, 'description' => 'What matters the attorney-in-fact is authorized to act on.'],
                ['name' => 'effective_date', 'label' => 'Effective Date', 'type' => 'date', 'required' => true, 'description' => 'When this power of attorney takes effect.'],
            ],
        );
    }

    private function seedFromFixture(string $fixture, string $title, string $category, ?string $generatorClass, string $description, array $fields): void
    {
        if (Precedent::where('title', $title)->exists()) {
            return;
        }

        $fixturePath = database_path("seeders/fixtures/{$fixture}");
        $storedPath  = "precedents/{$fixture}";

        Storage::disk('local')->put($storedPath, file_get_contents($fixturePath));

        Precedent::create([
            'title'                  => $title,
            'category'               => $category,
            'generator_class'        => $generatorClass,
            'description'            => $description,
            'docx_path'              => $storedPath,
            'docx_original_filename' => $fixture,
            'questionnaire_fields'   => $fields,
            'is_active'              => true,
        ]);
    }
}
