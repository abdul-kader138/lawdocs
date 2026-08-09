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
            description: 'Standard last will and testament template.',
            fields: [
                ['name' => 'testator_name', 'label' => "Testator's Full Name", 'type' => 'text', 'required' => true, 'description' => "The person making the will."],
                ['name' => 'executor_name', 'label' => "Executor's Full Name", 'type' => 'text', 'required' => true, 'description' => 'Who will administer the estate.'],
                ['name' => 'beneficiaries', 'label' => 'Beneficiaries', 'type' => 'textarea', 'required' => true, 'description' => 'One per line, in the format: Name - Relationship - Share%.'],
                ['name' => 'guardian_name', 'label' => "Guardian's Name (if minor children)", 'type' => 'text', 'required' => false, 'description' => 'Leave blank if not applicable.'],
            ],
        );

        $this->seedFromFixture(
            fixture: 'poa-precedent.docx',
            title: 'Power of Attorney',
            category: 'power_of_attorney',
            description: 'General power of attorney template.',
            fields: [
                ['name' => 'principal_name', 'label' => "Principal's Full Name", 'type' => 'text', 'required' => true, 'description' => 'The person granting the power.'],
                ['name' => 'attorney_name', 'label' => "Attorney-in-Fact's Full Name", 'type' => 'text', 'required' => true, 'description' => 'The person receiving the power.'],
                ['name' => 'scope', 'label' => 'Scope of Authority', 'type' => 'textarea', 'required' => true, 'description' => 'What matters the attorney-in-fact is authorized to act on.'],
                ['name' => 'effective_date', 'label' => 'Effective Date', 'type' => 'date', 'required' => true, 'description' => 'When this power of attorney takes effect.'],
            ],
        );
    }

    private function seedFromFixture(string $fixture, string $title, string $category, string $description, array $fields): void
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
            'description'            => $description,
            'docx_path'              => $storedPath,
            'docx_original_filename' => $fixture,
            'questionnaire_fields'   => $fields,
            'is_active'              => true,
        ]);
    }
}
