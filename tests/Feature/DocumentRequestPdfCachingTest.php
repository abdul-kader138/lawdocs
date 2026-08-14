<?php

namespace Tests\Feature;

use App\Jobs\GenerateDocumentJob;
use App\Models\DocumentRequest;
use App\Models\User;
use App\Services\DocxToPdfConverter;
use Database\Seeders\ShieldSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\WillPrecedentFixture;
use Tests\TestCase;

class DocumentRequestPdfCachingTest extends TestCase
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

    private function makeCompletedDocumentRequest(User $requester): DocumentRequest
    {
        $precedent = $this->makeWillPrecedent(['requires_review' => false]);
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

    /** A fake "converted PDF" living in its own throwaway dir, mirroring DocxToPdfConverter::convert()'s real contract. */
    private function fakeConvertedPdfPath(): string
    {
        $workDir = storage_path('app/pdf-conversions/'.Str::uuid());
        File::ensureDirectoryExists($workDir);
        $path = $workDir.'/fake.pdf';
        File::put($path, "%PDF-1.4\nfake test pdf\n");

        return $path;
    }

    public function test_second_preview_request_reuses_the_cached_pdf_without_reconverting(): void
    {
        $requester = $this->makeRequester();
        $documentRequest = $this->makeCompletedDocumentRequest($requester);
        $this->assertNull($documentRequest->generated_pdf_path);

        $this->mock(DocxToPdfConverter::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('convert')->once()->andReturnUsing(fn () => $this->fakeConvertedPdfPath());
        });

        $this->actingAs($requester)
            ->get(route('document-requests.preview-pdf', $documentRequest))
            ->assertOk();

        $documentRequest->refresh();
        $this->assertNotNull($documentRequest->generated_pdf_path);

        // Second request must NOT call convert() again — the mock's ->once()
        // expectation above would fail the test if it did.
        $this->actingAs($requester)
            ->get(route('document-requests.preview-pdf', $documentRequest))
            ->assertOk();
    }

    public function test_download_pdf_reuses_a_pdf_already_cached_by_a_prior_preview(): void
    {
        $requester = $this->makeRequester();
        $documentRequest = $this->makeCompletedDocumentRequest($requester);

        $this->mock(DocxToPdfConverter::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('convert')->once()->andReturnUsing(fn () => $this->fakeConvertedPdfPath());
        });

        $this->actingAs($requester)
            ->get(route('document-requests.preview-pdf', $documentRequest))
            ->assertOk();

        // Download hits a different controller/route entirely, but must
        // reuse the same cached file rather than converting again.
        $this->actingAs($requester)
            ->get(route('document-requests.download-pdf', $documentRequest))
            ->assertOk();
    }

    public function test_cached_pdf_path_is_persisted_and_points_to_a_real_file(): void
    {
        $requester = $this->makeRequester();
        $documentRequest = $this->makeCompletedDocumentRequest($requester);

        $this->mock(DocxToPdfConverter::class, function ($mock) {
            $mock->shouldReceive('isAvailable')->andReturn(true);
            $mock->shouldReceive('convert')->once()->andReturnUsing(fn () => $this->fakeConvertedPdfPath());
        });

        $this->actingAs($requester)
            ->get(route('document-requests.preview-pdf', $documentRequest))
            ->assertOk();

        $documentRequest->refresh();
        $this->assertNotNull($documentRequest->generated_pdf_path);
        $this->assertTrue(Storage::disk('local')->exists($documentRequest->generated_pdf_path));
    }
}
