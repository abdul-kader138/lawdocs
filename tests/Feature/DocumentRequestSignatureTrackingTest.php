<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentRequestResource\Pages\ViewDocumentRequest;
use App\Models\DocumentRequest;
use App\Models\User;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\WillPrecedentFixture;
use Tests\TestCase;

class DocumentRequestSignatureTrackingTest extends TestCase
{
    use RefreshDatabase;
    use WillPrecedentFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShieldSeeder::class);
    }

    private function makeReadyDocumentRequest(): DocumentRequest
    {
        $requester = User::factory()->create();
        $requester->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent(['requires_review' => false]);

        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => $requester->id,
            'answers' => $this->willAnswers(),
            'status' => 'completed',
            'generated_title' => 'Test Will',
            'generated_docx_path' => 'generated/does-not-matter.docx',
        ]);

        $this->assertTrue($documentRequest->isReadyForDownload());

        return $documentRequest;
    }

    public function test_send_for_signature_action_records_a_timestamp(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $documentRequest = $this->makeReadyDocumentRequest();
        $this->actingAs($operator);

        Livewire::test(ViewDocumentRequest::class, ['record' => $documentRequest->id])
            ->assertActionVisible('sendForSignature')
            ->assertActionHidden('markAsSigned')
            ->callAction('sendForSignature');

        $documentRequest->refresh();
        $this->assertTrue($documentRequest->isSentForSignature());
        $this->assertFalse($documentRequest->isSigned());
    }

    public function test_mark_as_signed_records_timestamp_and_uploaded_document(): void
    {
        Storage::fake('local');
        $operator = User::factory()->create();
        $operator->assignRole('operator');
        $documentRequest = $this->makeReadyDocumentRequest();
        $documentRequest->update(['sent_for_signature_at' => now()]);
        $this->actingAs($operator);

        $file = UploadedFile::fake()->create('signed-will.pdf', 100, 'application/pdf');

        Livewire::test(ViewDocumentRequest::class, ['record' => $documentRequest->id])
            ->assertActionVisible('markAsSigned')
            ->callAction('markAsSigned', data: ['signed_document_path' => $file]);

        $documentRequest->refresh();
        $this->assertTrue($documentRequest->isSigned());
        $this->assertNotNull($documentRequest->signed_document_path);
        Storage::disk('local')->assertExists($documentRequest->signed_document_path);
    }

    public function test_download_signed_copy_route_requires_a_signed_document(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('panel_user');
        $documentRequest = $this->makeReadyDocumentRequest();

        $this->actingAs($requester)
            ->get(route('document-requests.download-signed', $documentRequest))
            ->assertNotFound();
    }

    public function test_download_signed_copy_route_serves_the_uploaded_file(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('panel_user');
        // makeReadyDocumentRequest() -> makeWillPrecedent() calls
        // Storage::fake('local') internally — write the signed file AFTER,
        // or this put() targets a fake disk instance that gets reset.
        $documentRequest = $this->makeReadyDocumentRequest();
        Storage::disk('local')->put('signed/test.pdf', 'fake-pdf-content');
        $documentRequest->update(['signed_at' => now(), 'signed_document_path' => 'signed/test.pdf']);

        $this->actingAs($requester)
            ->get(route('document-requests.download-signed', $documentRequest))
            ->assertOk();
    }

    public function test_send_for_signature_hidden_until_ready_for_download(): void
    {
        $requester = User::factory()->create();
        $requester->assignRole('panel_user');
        $precedent = $this->makeWillPrecedent();
        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => $requester->id,
            'answers' => [],
            'status' => 'pending',
        ]);
        $this->actingAs($requester);

        Livewire::test(ViewDocumentRequest::class, ['record' => $documentRequest->id])
            ->assertActionHidden('sendForSignature');
    }
}
