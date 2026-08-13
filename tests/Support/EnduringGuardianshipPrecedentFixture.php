<?php

namespace Tests\Support;

use App\Models\DocumentRequest;
use App\Models\Precedent;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Shared marker-bearing Enduring Guardianship precedent builder, mirroring
 * WillPrecedentFixture's shape for EnduringGuardianshipGenerator.
 */
trait EnduringGuardianshipPrecedentFixture
{
    private function makeEnduringGuardianshipPrecedent(array $overrides = []): Precedent
    {
        Storage::fake('local');

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        $section->addText('[[CLAUSE:appointment_clause]]');
        $section->addText('[[REPEAT:guardians AS guardian]]');
        $section->addText('I appoint {{guardian.name}} of {{guardian.address}} to be my Enduring Guardian.');
        $section->addText('[[/REPEAT]]');
        $section->addText('[[IF:guardians_act_jointly]]');
        $section->addText('My Guardians must act jointly in the exercise of this appointment.');
        $section->addText('[[ELSE]]');
        $section->addText('My Guardians may act jointly and severally in the exercise of this appointment.');
        $section->addText('[[/IF]]');
        $section->addText('[[/CLAUSE]]');

        $section->addText('[[CLAUSE:guardian_functions]]');
        $section->addText('My Guardian(s) may make decisions in relation to my accommodation, and consent to medical, dental, and health care services on my behalf.', ['bold' => true]);
        $section->addText('[[/CLAUSE]]');

        $section->addText('[[CLAUSE:revocation]]');
        $section->addText('I revoke all prior appointments of enduring guardian made by me.', ['bold' => true]);
        $section->addText('[[/CLAUSE]]');

        $tmp = tempnam(sys_get_temp_dir(), 'eg_precedent_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/enduring_guardianship.docx', file_get_contents($tmp));
        @unlink($tmp);

        return Precedent::create(array_merge([
            'title' => 'Enduring Guardianship',
            'docx_path' => 'precedents/enduring_guardianship.docx',
            'generator_class' => 'enduring_guardianship',
            'questionnaire_fields' => [],
            'party_groups' => $this->guardiansPartyGroups(),
            'jurisdiction' => 'NSW',
            'is_active' => true,
        ], $overrides));
    }

    private function guardiansPartyGroups(): array
    {
        return [
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
                    ['name' => 'name', 'label' => "Guardian's Full Name", 'type' => 'text', 'required' => true],
                    ['name' => 'address', 'label' => "Guardian's Address", 'type' => 'text', 'required' => true],
                    ['name' => 'relationship', 'label' => 'Relationship to Principal', 'type' => 'text', 'required' => false],
                ],
            ],
        ];
    }

    private function enduringGuardianshipAnswers(array $overrides = []): array
    {
        return array_merge([
            'principal_name' => 'Ashley Dewell',
            'principal_address' => '1 First Street, Sydney NSW',
            'guardians_act_jointly' => false,
        ], $overrides);
    }

    /** @param array<int, array<string, mixed>>|null $rows */
    private function attachGuardians(DocumentRequest $documentRequest, ?array $rows = null): void
    {
        $rows ??= [
            ['name' => 'Bernadette Smith', 'address' => '2 Second Street, Sydney NSW', 'relationship' => 'Spouse'],
        ];

        foreach (array_values($rows) as $position => $row) {
            $documentRequest->parties()->create([
                'group_key' => 'guardians',
                'position' => $position,
                'data' => $row,
            ]);
        }
    }
}
