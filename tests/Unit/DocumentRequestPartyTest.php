<?php

namespace Tests\Unit;

use App\Models\DocumentRequest;
use App\Models\DocumentRequestParty;
use App\Models\Precedent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class DocumentRequestPartyTest extends TestCase
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

    public function test_data_is_cast_to_array(): void
    {
        $documentRequest = $this->makeDocumentRequest();

        $party = $documentRequest->parties()->create([
            'group_key' => 'beneficiaries',
            'position' => 0,
            'data' => ['name' => 'Alfred Smith', 'share' => 50],
        ]);

        $party->refresh();

        $this->assertIsArray($party->data);
        $this->assertSame('Alfred Smith', $party->data['name']);
    }

    public function test_belongs_to_document_request(): void
    {
        $documentRequest = $this->makeDocumentRequest();
        $party = $documentRequest->parties()->create(['group_key' => 'beneficiaries', 'position' => 0, 'data' => []]);

        $this->assertTrue($party->documentRequest->is($documentRequest));
    }

    public function test_substitute_relation_resolves_to_another_party_row(): void
    {
        $documentRequest = $this->makeDocumentRequest();
        $primary = $documentRequest->parties()->create(['group_key' => 'executors', 'position' => 0, 'data' => ['name' => 'Alfred Smith']]);
        $substitute = $documentRequest->parties()->create(['group_key' => 'executors', 'position' => 1, 'data' => ['name' => 'Bernadette Smith']]);

        $primary->update(['substitute_party_id' => $substitute->id]);
        $primary->refresh();

        $this->assertTrue($primary->substitute->is($substitute));
    }

    public function test_deleting_document_request_cascades_to_parties(): void
    {
        $documentRequest = $this->makeDocumentRequest();
        $documentRequest->parties()->create(['group_key' => 'beneficiaries', 'position' => 0, 'data' => []]);

        $documentRequest->delete();

        $this->assertSame(0, DocumentRequestParty::count());
    }

    public function test_deleting_substitute_row_nulls_the_pointer_rather_than_cascading(): void
    {
        $documentRequest = $this->makeDocumentRequest();
        $primary = $documentRequest->parties()->create(['group_key' => 'executors', 'position' => 0, 'data' => []]);
        $substitute = $documentRequest->parties()->create(['group_key' => 'executors', 'position' => 1, 'data' => []]);
        $primary->update(['substitute_party_id' => $substitute->id]);

        $substitute->delete();
        $primary->refresh();

        $this->assertNotNull(DocumentRequestParty::find($primary->id));
        $this->assertNull($primary->substitute_party_id);
    }
}
