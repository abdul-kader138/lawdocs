<?php

namespace Tests\Unit;

use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class DocumentRequestApprovalTest extends TestCase
{
    use RefreshDatabase;

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

    private function makeRequest(array $precedentOverrides = [], array $requestOverrides = []): DocumentRequest
    {
        $precedent = Precedent::create(array_merge([
            'title' => 'Test', 'docx_path' => $this->makeTestPrecedentDocx(),
            'questionnaire_fields' => [], 'is_active' => true,
        ], $precedentOverrides));

        return DocumentRequest::create(array_merge([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => User::factory()->create()->id,
            'answers' => [],
            'status' => 'completed',
        ], $requestOverrides));
    }

    public function test_requires_review_defaults_to_true_and_blocks_download_until_approved(): void
    {
        $request = $this->makeRequest();

        $this->assertTrue($request->requiresApproval());
        $this->assertFalse($request->isApproved());
        $this->assertFalse($request->isReadyForDownload());
    }

    public function test_approval_makes_it_ready_for_download(): void
    {
        $request = $this->makeRequest();
        $request->update(['approved_at' => now(), 'approved_by' => User::factory()->create()->id]);

        $this->assertTrue($request->isApproved());
        $this->assertTrue($request->isReadyForDownload());
    }

    public function test_precedent_opting_out_of_review_skips_the_gate(): void
    {
        $request = $this->makeRequest(['requires_review' => false]);

        $this->assertFalse($request->requiresApproval());
        $this->assertTrue($request->isReadyForDownload());
    }

    public function test_not_completed_is_never_ready_for_download_even_if_approved(): void
    {
        $request = $this->makeRequest(['requires_review' => false], ['status' => 'pending', 'approved_at' => now()]);

        $this->assertFalse($request->isReadyForDownload());
    }

    public function test_deleted_precedent_fails_closed_to_requiring_approval(): void
    {
        $request = $this->makeRequest(['requires_review' => false]);
        $request->precedent->delete();
        $request->refresh();

        $this->assertNull($request->precedent);
        $this->assertTrue($request->requiresApproval());
        $this->assertFalse($request->isReadyForDownload());
    }
}
