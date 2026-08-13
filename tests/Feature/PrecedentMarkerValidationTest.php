<?php

namespace Tests\Feature;

use App\Models\Precedent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class PrecedentMarkerValidationTest extends TestCase
{
    use RefreshDatabase;

    private function storeDocx(callable $build): string
    {
        Storage::fake('local');
        $phpWord = new PhpWord;
        $build($phpWord->addSection());

        $tmp = tempnam(sys_get_temp_dir(), 'marker_validation_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/x.docx', file_get_contents($tmp));
        @unlink($tmp);

        return 'precedents/x.docx';
    }

    private function baseWillFields(): array
    {
        return [
            ['name' => 'testator_name', 'label' => 'Name', 'type' => 'text', 'required' => true],
        ];
    }

    private function baseWillPartyGroups(): array
    {
        return [
            [
                'key' => 'beneficiaries',
                'label' => 'Beneficiaries',
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                    ['name' => 'share', 'label' => 'Share', 'type' => 'number', 'required' => true],
                ],
            ],
        ];
    }

    public function test_unknown_if_flag_is_flagged_with_a_suggestion(): void
    {
        $docxPath = $this->storeDocx(function ($section) {
            $section->addText('[[CLAUSE:beneficiaries_clause]]');
            $section->addText('[[REPEAT:beneficiaries AS beneficiary]]');
            $section->addText('[[IF:beneficiary.per_stirpe]]'); // typo: missing trailing "s"
            $section->addText('text');
            $section->addText('[[/IF]]');
            $section->addText('[[/REPEAT]]');
            $section->addText('[[/CLAUSE]]');
        });

        $precedent = Precedent::create([
            'title' => 'Test', 'docx_path' => $docxPath, 'generator_class' => 'will',
            'questionnaire_fields' => $this->baseWillFields(), 'party_groups' => $this->baseWillPartyGroups(),
            'is_active' => true,
        ]);

        $this->assertNotNull($precedent->clause_marker_error);
        $this->assertStringContainsString('unknown flag "beneficiary.per_stirpe"', $precedent->clause_marker_error);
        $this->assertStringContainsString('Did you mean "beneficiary.per_stirpes"?', $precedent->clause_marker_error);
    }

    public function test_unknown_repeat_group_is_flagged(): void
    {
        $docxPath = $this->storeDocx(function ($section) {
            $section->addText('[[CLAUSE:beneficiaries_clause]]');
            $section->addText('[[REPEAT:beneficiarys]]'); // typo
            $section->addText('text');
            $section->addText('[[/REPEAT]]');
            $section->addText('[[/CLAUSE]]');
        });

        $precedent = Precedent::create([
            'title' => 'Test', 'docx_path' => $docxPath, 'generator_class' => 'will',
            'questionnaire_fields' => $this->baseWillFields(), 'party_groups' => $this->baseWillPartyGroups(),
            'is_active' => true,
        ]);

        $this->assertNotNull($precedent->clause_marker_error);
        $this->assertStringContainsString('unknown party group', $precedent->clause_marker_error);
        $this->assertStringContainsString('beneficiarys', $precedent->clause_marker_error);
    }

    public function test_unknown_answers_placeholder_is_flagged(): void
    {
        $docxPath = $this->storeDocx(function ($section) {
            $section->addText('[[CLAUSE:beneficiaries_clause]]');
            $section->addText('{{answers.testator_nam}}'); // typo
            $section->addText('[[/CLAUSE]]');
        });

        $precedent = Precedent::create([
            'title' => 'Test', 'docx_path' => $docxPath, 'generator_class' => 'will',
            'questionnaire_fields' => $this->baseWillFields(), 'party_groups' => $this->baseWillPartyGroups(),
            'is_active' => true,
        ]);

        $this->assertNotNull($precedent->clause_marker_error);
        $this->assertStringContainsString('unknown questionnaire field', $precedent->clause_marker_error);
    }

    public function test_placeholder_alias_out_of_scope_is_flagged(): void
    {
        $docxPath = $this->storeDocx(function ($section) {
            $section->addText('[[CLAUSE:beneficiaries_clause]]');
            $section->addText('{{beneficiary.name}}'); // no enclosing REPEAT
            $section->addText('[[/CLAUSE]]');
        });

        $precedent = Precedent::create([
            'title' => 'Test', 'docx_path' => $docxPath, 'generator_class' => 'will',
            'questionnaire_fields' => $this->baseWillFields(), 'party_groups' => $this->baseWillPartyGroups(),
            'is_active' => true,
        ]);

        $this->assertNotNull($precedent->clause_marker_error);
        $this->assertStringContainsString('not a [[REPEAT:', $precedent->clause_marker_error);
    }

    public function test_field_not_declared_on_party_group_is_flagged(): void
    {
        $docxPath = $this->storeDocx(function ($section) {
            $section->addText('[[CLAUSE:beneficiaries_clause]]');
            $section->addText('[[REPEAT:beneficiaries AS beneficiary]]');
            $section->addText('{{beneficiary.nonexistent_field}}');
            $section->addText('[[/REPEAT]]');
            $section->addText('[[/CLAUSE]]');
        });

        $precedent = Precedent::create([
            'title' => 'Test', 'docx_path' => $docxPath, 'generator_class' => 'will',
            'questionnaire_fields' => $this->baseWillFields(), 'party_groups' => $this->baseWillPartyGroups(),
            'is_active' => true,
        ]);

        $this->assertNotNull($precedent->clause_marker_error);
        $this->assertStringContainsString('not declared on party group "beneficiaries"', $precedent->clause_marker_error);
    }

    public function test_valid_markers_produce_no_error(): void
    {
        $docxPath = $this->storeDocx(function ($section) {
            $section->addText('[[CLAUSE:beneficiaries_clause]]');
            $section->addText('[[REPEAT:beneficiaries AS beneficiary]]');
            $section->addText('{{beneficiary.name}} gets {{beneficiary.share}}%. {{answers.testator_name}}.');
            $section->addText('[[IF:beneficiary.per_stirpes]]');
            $section->addText('Per stirpes wording.');
            $section->addText('[[/IF]]');
            $section->addText('[[/REPEAT]]');
            $section->addText('[[/CLAUSE]]');
        });

        $precedent = Precedent::create([
            'title' => 'Test', 'docx_path' => $docxPath, 'generator_class' => 'will',
            'questionnaire_fields' => $this->baseWillFields(), 'party_groups' => $this->baseWillPartyGroups(),
            'is_active' => true,
        ]);

        $this->assertNull($precedent->clause_marker_error);
    }

    public function test_synthetic_pronoun_fields_are_not_flagged_as_unknown(): void
    {
        // pronoun_subject/pronoun_object are injected at generation time by
        // PartyGroupAssembler from a row's "gender" field — never declared
        // in party_groups[].fields, but legitimate to reference.
        $docxPath = $this->storeDocx(function ($section) {
            $section->addText('[[CLAUSE:beneficiaries_clause]]');
            $section->addText('[[REPEAT:beneficiaries AS beneficiary]]');
            $section->addText('if {{beneficiary.pronoun_subject}} does not survive, {{beneficiary.pronoun_object}}.');
            $section->addText('[[/REPEAT]]');
            $section->addText('[[/CLAUSE]]');
        });

        $precedent = Precedent::create([
            'title' => 'Test', 'docx_path' => $docxPath, 'generator_class' => 'will',
            'questionnaire_fields' => $this->baseWillFields(), 'party_groups' => $this->baseWillPartyGroups(),
            'is_active' => true,
        ]);

        $this->assertNull($precedent->clause_marker_error);
    }

    public function test_generator_without_declares_party_flags_skips_validation(): void
    {
        // No generator with markers requiring flags/groups is registered
        // under a bogus key, so resolve() throws and validation is skipped
        // silently rather than raising a spurious error.
        $docxPath = $this->storeDocx(function ($section) {
            $section->addText('[[CLAUSE:plain]]');
            $section->addText('Just text.');
            $section->addText('[[/CLAUSE]]');
        });

        $precedent = Precedent::create([
            'title' => 'Test', 'docx_path' => $docxPath, 'generator_class' => null,
            'questionnaire_fields' => [], 'is_active' => true,
        ]);

        $this->assertNull($precedent->clause_marker_error);
    }
}
