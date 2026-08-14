<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentRequestResource\Pages\ListDocumentRequests;
use App\Filament\Resources\DocumentRequestResource\Pages\ViewDocumentRequest;
use App\Jobs\GenerateDocumentJob;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Services\DocxToPdfConverter;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\WillPrecedentFixture;
use Tests\TestCase;

class DocumentRequestPdfPreviewTest extends TestCase
{
    use RefreshDatabase;
    use WillPrecedentFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ShieldSeeder::class);
    }

    private function makeRequester(): User
    {
        $user = User::factory()->create();
        $user->assignRole('panel_user');

        return $user;
    }

    private function makeCompletedDocumentRequest(User $requester, bool $requiresReview = false): DocumentRequest
    {
        $precedent = $this->makeWillPrecedent(['requires_review' => $requiresReview]);
        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => $requester->id,
            'answers' => $this->willAnswers(),
            'status' => 'pending',
        ]);
        $this->attachBeneficiaries($documentRequest);

        GenerateDocumentJob::dispatchSync($documentRequest);

        return $documentRequest->refresh();
    }

    public function test_preview_is_available_before_approval(): void
    {
        if (! app(DocxToPdfConverter::class)->isAvailable()) {
            $this->markTestSkipped('LibreOffice not installed in this environment.');
        }

        $requester = $this->makeRequester();
        // requires_review true — not yet approved.
        $documentRequest = $this->makeCompletedDocumentRequest($requester, requiresReview: true);
        $this->assertFalse($documentRequest->isReadyForDownload());

        $this->actingAs($requester)
            ->get(route('document-requests.preview-pdf', $documentRequest))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeaderContains('Content-Disposition', 'inline');
    }

    public function test_preview_still_works_once_approved(): void
    {
        if (! app(DocxToPdfConverter::class)->isAvailable()) {
            $this->markTestSkipped('LibreOffice not installed in this environment.');
        }

        $requester = $this->makeRequester();
        $documentRequest = $this->makeCompletedDocumentRequest($requester, requiresReview: true);
        $documentRequest->update(['approved_at' => now(), 'approved_by' => $requester->id]);

        $this->actingAs($requester)
            ->get(route('document-requests.preview-pdf', $documentRequest))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_preview_404s_when_not_completed(): void
    {
        $requester = $this->makeRequester();
        $precedent = $this->makeWillPrecedent();
        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => $requester->id,
            'answers' => [],
            'status' => 'pending',
        ]);

        $this->actingAs($requester)
            ->get(route('document-requests.preview-pdf', $documentRequest))
            ->assertNotFound();
    }

    public function test_preview_action_visible_on_table_before_approval_while_download_is_hidden(): void
    {
        if (! app(DocxToPdfConverter::class)->isAvailable()) {
            $this->markTestSkipped('LibreOffice not installed in this environment.');
        }

        $requester = $this->makeRequester();
        $documentRequest = $this->makeCompletedDocumentRequest($requester, requiresReview: true);

        $this->actingAs($requester);

        Livewire::test(ListDocumentRequests::class)
            ->assertTableActionVisible('previewPdf', $documentRequest)
            ->assertTableActionHidden('download', $documentRequest)
            ->assertTableActionHidden('downloadPdf', $documentRequest);
    }

    public function test_preview_action_visible_on_page_before_approval_while_download_is_hidden(): void
    {
        if (! app(DocxToPdfConverter::class)->isAvailable()) {
            $this->markTestSkipped('LibreOffice not installed in this environment.');
        }

        $requester = $this->makeRequester();
        $documentRequest = $this->makeCompletedDocumentRequest($requester, requiresReview: true);

        $this->actingAs($requester);

        Livewire::test(ViewDocumentRequest::class, ['record' => $documentRequest->id])
            ->assertActionVisible('previewPdf')
            ->assertActionHidden('download')
            ->assertActionHidden('downloadPdf');
    }
}
