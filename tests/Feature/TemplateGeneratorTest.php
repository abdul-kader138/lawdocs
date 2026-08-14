<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\User;
use App\Services\Generators\TemplateGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class TemplateGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_code_precedent_generates_dynamic_title_conditions_and_clause_order(): void
    {
        Storage::fake('local');

        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('[[CLAUSE:introduction]]');
        $section->addText('Agreement for {{answers.client_name}}.');
        $section->addText('[[IF:include_note]]');
        $section->addText('Optional note included.');
        $section->addText('[[/IF]]');
        $section->addText('[[/CLAUSE]]');
        $temporaryPath = tempnam(sys_get_temp_dir(), 'generic_precedent_').'.docx';
        IOFactory::createWriter($word, 'Word2007')->save($temporaryPath);
        Storage::disk('local')->put('precedents/generic.docx', file_get_contents($temporaryPath));
        @unlink($temporaryPath);

        $precedent = Precedent::create([
            'title' => 'Client Agreement',
            'output_title_template' => 'Agreement — {{answers.client_name}}',
            'docx_path' => 'precedents/generic.docx',
            'generator_class' => 'template',
            'questionnaire_fields' => [
                ['name' => 'client_name', 'label' => 'Client name', 'type' => 'text', 'required' => true],
                ['name' => 'include_note', 'label' => 'Include note', 'type' => 'boolean', 'required' => false],
            ],
            'is_active' => true,
        ]);

        $request = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => User::factory()->create()->id,
            'answers' => ['client_name' => 'Jordan Lee', 'include_note' => true],
            'status' => 'pending',
        ]);

        $draft = app(TemplateGenerator::class)->generate($request);

        $this->assertSame('Agreement — Jordan Lee', $draft['title']);
        $this->assertSame('1. Introduction', $draft['blocks'][0]['text']);
        $text = collect($draft['blocks'][1]['elements'])
            ->flatMap(fn ($element) => $element->runs)
            ->pluck('text')
            ->implode(' ');
        $this->assertStringContainsString('Agreement for Jordan Lee.', $text);
        $this->assertStringContainsString('Optional note included.', $text);
        $this->assertNull($precedent->clause_marker_error);
    }
}
