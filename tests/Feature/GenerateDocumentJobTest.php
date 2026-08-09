<?php

namespace Tests\Feature;

use App\Jobs\GenerateDocumentJob;
use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class GenerateDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    private function makePrecedent(): Precedent
    {
        Storage::fake('local');
        $phpWord = new PhpWord();
        $phpWord->addSection()->addText('placeholder');
        $tmp = tempnam(sys_get_temp_dir(), 'precedent_') . '.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/will.docx', file_get_contents($tmp));
        @unlink($tmp);

        return Precedent::create([
            'title'                => 'Last Will and Testament',
            'docx_path'            => 'precedents/will.docx',
            'questionnaire_fields' => [
                ['name' => 'testator_name', 'label' => "Testator's Name", 'type' => 'text', 'required' => true, 'description' => 'Full legal name'],
            ],
            'is_active' => true,
        ]);
    }

    public function test_end_to_end_generation_produces_a_downloadable_docx(): void
    {
        Setting::set('claude_api_key', 'sk-test', 'claude');

        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'stop_reason' => 'tool_use',
                'content'     => [[
                    'type'  => 'tool_use',
                    'name'  => 'draft_document',
                    'input' => [
                        'title'  => 'Last Will and Testament of John Doe',
                        'blocks' => [
                            ['type' => 'heading', 'level' => 2, 'text' => '1. Executor'],
                            ['type' => 'paragraph', 'text' => 'I appoint Jane Doe as Executor.'],
                            ['type' => 'list_item', 'text' => 'My spouse - 100%'],
                        ],
                    ],
                ]],
            ]),
        ]);

        $user = User::factory()->create();
        $documentRequest = DocumentRequest::create([
            'precedent_id'             => $this->makePrecedent()->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by'             => $user->id,
            'answers'                  => ['testator_name' => 'John Doe'],
            'status'                   => 'pending',
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);

        $documentRequest->refresh();

        $this->assertSame('completed', $documentRequest->status);
        $this->assertSame('Last Will and Testament of John Doe', $documentRequest->generated_title);
        $this->assertNotNull($documentRequest->generated_docx_path);
        $this->assertNotNull($documentRequest->generated_at);
        $this->assertTrue(Storage::disk('local')->exists($documentRequest->generated_docx_path));

        // The generated file must be a genuinely valid, readable .docx.
        $reloaded = IOFactory::load(Storage::disk('local')->path($documentRequest->generated_docx_path), 'Word2007');
        $this->assertCount(1, $reloaded->getSections());
    }

    public function test_claude_failure_marks_the_request_failed_with_a_clear_message(): void
    {
        Setting::set('claude_api_key', 'sk-test', 'claude');

        Http::fake([
            'https://api.anthropic.com/*' => Http::response(['error' => 'bad request'], 401),
        ]);

        $user = User::factory()->create();
        $documentRequest = DocumentRequest::create([
            'precedent_id'             => $this->makePrecedent()->id,
            'precedent_title_snapshot' => 'Last Will and Testament',
            'requested_by'             => $user->id,
            'answers'                  => ['testator_name' => 'John Doe'],
            'status'                   => 'pending',
        ]);

        GenerateDocumentJob::dispatchSync($documentRequest);

        $documentRequest->refresh();

        $this->assertSame('failed', $documentRequest->status);
        $this->assertNotNull($documentRequest->error_message);
        $this->assertNull($documentRequest->generated_docx_path);
    }
}
