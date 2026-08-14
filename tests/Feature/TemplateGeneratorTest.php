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

    /**
     * Proves the gap this feature closes: a conditional-appointee-with-
     * fallback paragraph (the same shape as WillGenerator::executorParagraph(),
     * previously only expressible as hand-written PHP) built entirely from
     * marker content — [[IF:alternate_executor_name]] testing a plain
     * optional text field, and {{answers.executor_pronoun_subject}} an
     * auto-computed pronoun for a top-level "*_gender" answer, both via
     * AnswerContextBuilder, no PHP generator logic involved.
     */
    public function test_conditional_appointee_paragraph_with_auto_pronouns_is_expressible_purely_via_markers(): void
    {
        Storage::fake('local');

        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('[[CLAUSE:executor_paragraph]]');
        $section->addText('I appoint {{answers.executor_name}} as my executor.');
        $section->addText('[[IF:alternate_executor_name]]');
        $section->addText('If {{answers.executor_pronoun_subject}} is unable to act then I appoint {{answers.alternate_executor_name}} as my executor.');
        $section->addText('[[ELSE]]');
        $section->addText('If {{answers.executor_pronoun_subject}} is unable to act then I appoint the Public Trustee as my executor.');
        $section->addText('[[/IF]]');
        $section->addText('[[/CLAUSE]]');
        $temporaryPath = tempnam(sys_get_temp_dir(), 'appointee_precedent_').'.docx';
        IOFactory::createWriter($word, 'Word2007')->save($temporaryPath);
        Storage::disk('local')->put('precedents/appointee.docx', file_get_contents($temporaryPath));
        @unlink($temporaryPath);

        $precedent = Precedent::create([
            'title' => 'Appointee Test Precedent',
            'docx_path' => 'precedents/appointee.docx',
            'generator_class' => 'template',
            'questionnaire_fields' => [
                ['name' => 'executor_name', 'label' => 'Executor Name', 'type' => 'text', 'required' => true],
                ['name' => 'executor_gender', 'label' => 'Executor Gender', 'type' => 'select', 'required' => true, 'options' => ['male' => 'Male', 'female' => 'Female']],
                ['name' => 'alternate_executor_name', 'label' => 'Alternate Executor Name', 'type' => 'text', 'required' => false],
            ],
            'is_active' => true,
        ]);
        $this->assertNull($precedent->clause_marker_error);

        $requester = User::factory()->create()->id;
        $textOf = fn (array $draft) => collect($draft['blocks'][1]['elements'])
            ->flatMap(fn ($element) => $element->runs)
            ->pluck('text')
            ->implode(' ');

        // Alternate executor named: takes the [[IF]] branch, with a
        // female-resolved pronoun for the primary executor.
        $withAlternate = app(TemplateGenerator::class)->generate(DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => $requester,
            'answers' => ['executor_name' => 'Jane Doe', 'executor_gender' => 'female', 'alternate_executor_name' => 'Bob Smith'],
            'status' => 'pending',
        ]));
        $withAlternateText = $textOf($withAlternate);
        $this->assertStringContainsString('If she is unable to act then I appoint Bob Smith as my executor.', $withAlternateText);
        $this->assertStringNotContainsString('Public Trustee', $withAlternateText);

        // No alternate named (field entirely blank, not just missing):
        // takes the [[ELSE]] branch, male-resolved pronoun.
        $withoutAlternate = app(TemplateGenerator::class)->generate(DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => $requester,
            'answers' => ['executor_name' => 'John Doe', 'executor_gender' => 'male', 'alternate_executor_name' => ''],
            'status' => 'pending',
        ]));
        $withoutAlternateText = $textOf($withoutAlternate);
        $this->assertStringContainsString('If he is unable to act then I appoint the Public Trustee as my executor.', $withoutAlternateText);
    }
}
