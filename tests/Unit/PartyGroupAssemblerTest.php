<?php

namespace Tests\Unit;

use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\User;
use App\Services\PartyGroupAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class PartyGroupAssemblerTest extends TestCase
{
    use RefreshDatabase;

    /** A minimal but real .docx — Precedent's saving hook parses whatever's at docx_path. */
    private function makeTestPrecedentDocx(): string
    {
        Storage::fake('local');
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('placeholder');
        $tmp = tempnam(sys_get_temp_dir(), 'test_precedent_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/x.docx', file_get_contents($tmp));
        @unlink($tmp);

        return 'precedents/x.docx';
    }

    private function makeDocumentRequest(): DocumentRequest
    {
        $precedent = Precedent::create([
            'title' => 'Test Precedent', 'docx_path' => $this->makeTestPrecedentDocx(),
            'questionnaire_fields' => [], 'is_active' => true,
        ]);

        return DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => User::factory()->create()->id,
            'answers' => [],
            'status' => 'pending',
        ]);
    }

    public function test_groups_rows_by_group_key_ordered_by_position(): void
    {
        $documentRequest = $this->makeDocumentRequest();
        $documentRequest->parties()->create(['group_key' => 'beneficiaries', 'position' => 1, 'data' => ['name' => 'Second']]);
        $documentRequest->parties()->create(['group_key' => 'beneficiaries', 'position' => 0, 'data' => ['name' => 'First']]);
        $documentRequest->parties()->create(['group_key' => 'executors', 'position' => 0, 'data' => ['name' => 'Exec']]);

        $result = app(PartyGroupAssembler::class)->forRequest($documentRequest);

        $this->assertSame(['First', 'Second'], array_column($result['beneficiaries'], 'name'));
        $this->assertSame(['Exec'], array_column($result['executors'], 'name'));
    }

    public function test_is_minor_computed_from_dob_and_not_stored(): void
    {
        $documentRequest = $this->makeDocumentRequest();
        $documentRequest->parties()->create([
            'group_key' => 'beneficiaries', 'position' => 0,
            'data' => ['name' => 'Child', 'dob' => now()->subYears(10)->toDateString()],
        ]);
        $documentRequest->parties()->create([
            'group_key' => 'beneficiaries', 'position' => 1,
            'data' => ['name' => 'Adult', 'dob' => now()->subYears(40)->toDateString()],
        ]);

        $result = app(PartyGroupAssembler::class)->forRequest($documentRequest);

        $this->assertTrue($result['beneficiaries'][0]['is_minor']);
        $this->assertFalse($result['beneficiaries'][1]['is_minor']);
    }

    public function test_no_dob_field_means_no_is_minor_key(): void
    {
        $documentRequest = $this->makeDocumentRequest();
        $documentRequest->parties()->create(['group_key' => 'beneficiaries', 'position' => 0, 'data' => ['name' => 'A']]);

        $result = app(PartyGroupAssembler::class)->forRequest($documentRequest);

        $this->assertArrayNotHasKey('is_minor', $result['beneficiaries'][0]);
    }

    public function test_gender_injects_pronoun_fields(): void
    {
        $documentRequest = $this->makeDocumentRequest();
        $documentRequest->parties()->create(['group_key' => 'beneficiaries', 'position' => 0, 'data' => ['name' => 'A', 'gender' => 'female']]);

        $result = app(PartyGroupAssembler::class)->forRequest($documentRequest);
        $row = $result['beneficiaries'][0];

        $this->assertSame('she', $row['pronoun_subject']);
        $this->assertSame('her', $row['pronoun_object']);
        $this->assertSame('her', $row['pronoun_possessive']);
        $this->assertSame('herself', $row['pronoun_reflexive']);
    }

    public function test_substitute_row_is_inlined(): void
    {
        $documentRequest = $this->makeDocumentRequest();
        $substitute = $documentRequest->parties()->create(['group_key' => 'executors', 'position' => 1, 'data' => ['name' => 'Substitute']]);
        $documentRequest->parties()->create([
            'group_key' => 'executors', 'position' => 0,
            'data' => ['name' => 'Primary'], 'substitute_party_id' => $substitute->id,
        ]);

        $result = app(PartyGroupAssembler::class)->forRequest($documentRequest);
        $primaryRow = collect($result['executors'])->firstWhere('name', 'Primary');

        $this->assertSame('Substitute', $primaryRow['substitute']['name']);
    }
}
