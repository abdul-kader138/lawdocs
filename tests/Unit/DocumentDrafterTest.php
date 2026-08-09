<?php

namespace Tests\Unit;

use App\Exceptions\DocumentDraftTruncatedException;
use App\Models\Precedent;
use App\Models\Setting;
use App\Services\DocumentDrafter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class DocumentDrafterTest extends TestCase
{
    use RefreshDatabase;

    private function precedent(): Precedent
    {
        // The saving() observer runs real PHPWord extraction against
        // docx_path regardless of how the model is created, so a real (if
        // minimal) .docx must exist there — this test is about the Claude
        // request/response contract, not ingestion quality (that's covered
        // by PrecedentTextExtractorTest).
        Storage::fake('local');
        $phpWord = new PhpWord();
        $phpWord->addSection()->addText('placeholder');
        $tmp = tempnam(sys_get_temp_dir(), 'precedent_') . '.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/test.docx', file_get_contents($tmp));
        @unlink($tmp);

        $precedent = Precedent::create([
            'title'                => 'Last Will and Testament',
            'docx_path'            => 'precedents/test.docx',
            'questionnaire_fields' => [
                ['name' => 'testator_name', 'label' => "Testator's Name", 'type' => 'text', 'required' => true, 'description' => 'Full legal name'],
                ['name' => 'executor_name', 'label' => "Executor's Name", 'type' => 'text', 'required' => true, 'description' => 'Who administers the estate'],
            ],
            'is_active' => true,
        ]);

        // Override with fixed sample text so this test doesn't depend on the
        // real extractor's output shape.
        $precedent->forceFill(['extracted_text' => "# LAST WILL AND TESTAMENT\n## 1. Executor\nSample text."])->saveQuietly();

        return $precedent;
    }

    public function test_forces_the_draft_document_tool_and_returns_parsed_blocks(): void
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
                            ['type' => 'heading', 'level' => 1, 'text' => 'Last Will and Testament of John Doe'],
                            ['type' => 'paragraph', 'text' => 'I appoint Jane Doe as Executor.'],
                        ],
                    ],
                ]],
            ]),
        ]);

        $result = app(DocumentDrafter::class)->draft($this->precedent(), [
            'testator_name' => 'John Doe',
            'executor_name' => 'Jane Doe',
        ]);

        $this->assertSame('Last Will and Testament of John Doe', $result['title']);
        $this->assertCount(2, $result['blocks']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['tool_choice'] === ['type' => 'tool', 'name' => 'draft_document']
                && $body['tools'][0]['name'] === 'draft_document'
                && str_contains($body['messages'][0]['content'], 'John Doe')
                && str_contains($body['messages'][0]['content'], 'LAST WILL AND TESTAMENT'); // precedent text included
        });
    }

    public function test_truncated_output_throws_instead_of_returning_a_partial_document(): void
    {
        Setting::set('claude_api_key', 'sk-test', 'claude');

        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'stop_reason' => 'max_tokens',
                'content'     => [],
            ]),
        ]);

        $this->expectException(DocumentDraftTruncatedException::class);

        app(DocumentDrafter::class)->draft($this->precedent(), ['testator_name' => 'John Doe']);
    }

    public function test_uses_settings_for_model_and_temperature_not_hardcoded_defaults(): void
    {
        Setting::set('claude_api_key', 'sk-test', 'claude');
        Setting::set('claude_model', 'claude-opus-4-8', 'claude');
        Setting::set('claude_temperature', 0.9, 'claude');
        Setting::set('claude_max_tokens', 2048, 'claude');

        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'stop_reason' => 'tool_use',
                'content'     => [['type' => 'tool_use', 'name' => 'draft_document', 'input' => ['title' => 'x', 'blocks' => []]]],
            ]),
        ]);

        app(DocumentDrafter::class)->draft($this->precedent(), []);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['model'] === 'claude-opus-4-8'
                && (float) $body['temperature'] === 0.9
                && (int) $body['max_tokens'] === 2048;
        });
    }
}
