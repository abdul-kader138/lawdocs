<?php

namespace Tests\Support;

use App\Models\DocumentRequest;
use App\Models\Precedent;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Shared marker-bearing Power of Attorney precedent builder, mirroring
 * WillPrecedentFixture's shape for PowerOfAttorneyGenerator. Includes one
 * [[REPEAT]] clause (attorneys) and two [[IF]]-gated clauses (joint/several
 * wording, enduring notice) so tests can exercise both control constructs
 * together, not just in isolation.
 */
trait PowerOfAttorneyPrecedentFixture
{
    private function makePowerOfAttorneyPrecedent(array $overrides = []): Precedent
    {
        Storage::fake('local');

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();

        $section->addText('[[CLAUSE:appointment_clause]]');
        $section->addText('[[REPEAT:attorneys AS attorney]]');
        $section->addText('I appoint {{attorney.name}} of {{attorney.address}} to be my Attorney.');
        $section->addText('[[/REPEAT]]');
        $section->addText('[[IF:attorneys_act_jointly]]');
        $section->addText('My Attorneys must act jointly in the exercise of this power.');
        $section->addText('[[ELSE]]');
        $section->addText('My Attorneys may act jointly and severally in the exercise of this power.');
        $section->addText('[[/IF]]');
        $section->addText('[[/CLAUSE]]');

        $section->addText('[[CLAUSE:enduring_notice]]');
        $section->addText('[[IF:is_enduring]]');
        $section->addText('This Power of Attorney is intended to be an Enduring Power of Attorney and continues to have effect even if I become mentally incapacitated.', ['bold' => true]);
        $section->addText('[[/IF]]');
        $section->addText('[[/CLAUSE]]');

        $section->addText('[[CLAUSE:general_powers]]');
        $section->addText('My Attorney(s) may do anything I may lawfully do by an attorney in relation to my financial and property matters.');
        $section->addText('[[/CLAUSE]]');

        $section->addText('[[CLAUSE:revocation]]');
        $section->addText('I revoke all prior powers of attorney made by me.', ['bold' => true]);
        $section->addText('[[/CLAUSE]]');

        $tmp = tempnam(sys_get_temp_dir(), 'poa_precedent_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/poa.docx', file_get_contents($tmp));
        @unlink($tmp);

        return Precedent::create(array_merge([
            'title' => 'Power of Attorney',
            'docx_path' => 'precedents/poa.docx',
            'generator_class' => 'power_of_attorney',
            'questionnaire_fields' => [],
            'party_groups' => $this->attorneysPartyGroups(),
            'jurisdiction' => 'NSW',
            'is_active' => true,
        ], $overrides));
    }

    private function attorneysPartyGroups(): array
    {
        return [
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
                    ['name' => 'name', 'label' => "Attorney's Full Name", 'type' => 'text', 'required' => true],
                    ['name' => 'address', 'label' => "Attorney's Address", 'type' => 'text', 'required' => true],
                    ['name' => 'relationship', 'label' => 'Relationship to Principal', 'type' => 'text', 'required' => false],
                ],
            ],
        ];
    }

    private function powerOfAttorneyAnswers(array $overrides = []): array
    {
        return array_merge([
            'principal_name' => 'Ashley Dewell',
            'principal_address' => '1 First Street, Sydney NSW',
            'is_enduring' => true,
            'attorneys_act_jointly' => false,
        ], $overrides);
    }

    /** @param array<int, array<string, mixed>>|null $rows */
    private function attachAttorneys(DocumentRequest $documentRequest, ?array $rows = null): void
    {
        $rows ??= [
            ['name' => 'Bernadette Smith', 'address' => '2 Second Street, Sydney NSW', 'relationship' => 'Spouse'],
        ];

        foreach (array_values($rows) as $position => $row) {
            $documentRequest->parties()->create([
                'group_key' => 'attorneys',
                'position' => $position,
                'data' => $row,
            ]);
        }
    }
}
