<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\User;
use App\Services\DocxBuilder;
use App\Services\Generators\CostsAgreementGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use Tests\Support\CostsAgreementPrecedentFixture;
use Tests\TestCase;

class CostsAgreementGeneratorTest extends TestCase
{
    use RefreshDatabase;
    use CostsAgreementPrecedentFixture;

    private function makeDocumentRequest(Precedent $precedent, array $answers, ?array $workItemRows = null): DocumentRequest
    {
        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => User::factory()->create()->id,
            'answers' => $answers,
            'status' => 'pending',
        ]);

        $this->attachWorkItems($documentRequest, $workItemRows);

        return $documentRequest;
    }

    private function flattenAllText(array $result): string
    {
        $flatText = collect($result['blocks'])->pluck('text')->filter()->implode(' | ');
        $rawText = collect($result['blocks'])->where('type', 'raw')
            ->flatMap(fn ($b) => collect($b['elements'])->flatMap(function ($el) {
                if ($el->kind === 'table') {
                    return collect($el->tableRows)->flatMap(fn ($row) => collect($row['cells'])
                        ->flatMap(fn ($cell) => collect($cell['content'])->flatMap(fn ($leaf) => array_column($leaf->runs, 'text'))));
                }

                return array_column($el->runs, 'text');
            }))
            ->implode(' | ');

        return $flatText.' | '.$rawText;
    }

    public function test_generates_expected_title_and_opening_block(): void
    {
        $precedent = $this->makeCostsAgreementPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->costsAgreementAnswers());

        $result = app(CostsAgreementGenerator::class)->generate($documentRequest);

        $this->assertSame('Costs Agreement with Ashley Dewell', $result['title']);
        $text = $this->flattenAllText($result);
        $this->assertStringContainsString('AND	Ashley Dewell ("you", "your")', $text);
    }

    public function test_precedent_with_front_matter_tag_overrides_built_in_boilerplate_and_receives_generation_date(): void
    {
        $precedent = $this->makeCostsAgreementPrecedent([], includeFrontMatter: true);
        $documentRequest = $this->makeDocumentRequest($precedent, $this->costsAgreementAnswers());

        $result = app(CostsAgreementGenerator::class)->generate($documentRequest);
        $text = $this->flattenAllText($result);

        $this->assertStringContainsString('CUSTOM FRONT MATTER for Ashley Dewell, dated '.now()->format('j F Y').'.', $text);
        $this->assertStringNotContainsString('BETWEEN', $text);
        $this->assertStringNotContainsString('This document is an offer to provide legal services', $text);
    }

    public function test_work_items_repeat_renders_each_row(): void
    {
        $precedent = $this->makeCostsAgreementPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->costsAgreementAnswers(), [
            ['description' => 'Draft the agreement'],
            ['description' => 'Attend settlement'],
        ]);

        $text = $this->flattenAllText(app(CostsAgreementGenerator::class)->generate($documentRequest));

        $this->assertStringContainsString('Draft the agreement', $text);
        $this->assertStringContainsString('Attend settlement', $text);
    }

    public function test_gst_toggle_shows_suffix_when_true_and_omits_when_false(): void
    {
        $precedent = $this->makeCostsAgreementPrecedent();

        $withGst = $this->makeDocumentRequest($precedent, $this->costsAgreementAnswers(['estimate_completion_gst' => true]));
        $textWithGst = $this->flattenAllText(app(CostsAgreementGenerator::class)->generate($withGst));
        $this->assertStringContainsString('(inclusive of GST)', $textWithGst);

        $withoutGst = $this->makeDocumentRequest($precedent, $this->costsAgreementAnswers(['estimate_completion_gst' => false]));
        $textWithoutGst = $this->flattenAllText(app(CostsAgreementGenerator::class)->generate($withoutGst));
        $this->assertStringNotContainsString('(inclusive of GST)', $textWithoutGst);
    }

    public function test_trust_money_and_estimate_amounts_are_interpolated(): void
    {
        $precedent = $this->makeCostsAgreementPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->costsAgreementAnswers([
            'trust_money' => '$3,500.00',
            'estimate_fees_expenses' => '$9,000 - $12,000',
        ]));

        $text = $this->flattenAllText(app(CostsAgreementGenerator::class)->generate($documentRequest));

        $this->assertStringContainsString('$3,500.00', $text);
        $this->assertStringContainsString('$9,000 - $12,000', $text);
    }

    public function test_both_rate_tables_survive_full_pipeline_through_docxbuilder(): void
    {
        $precedent = $this->makeCostsAgreementPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->costsAgreementAnswers());

        $result = app(CostsAgreementGenerator::class)->generate($documentRequest);

        $outputPath = tempnam(sys_get_temp_dir(), 'generated_costs_agreement_').'.docx';

        try {
            app(DocxBuilder::class)->buildAndSave($result['title'], $result['blocks'], $outputPath);

            $reloaded = IOFactory::load($outputPath, 'Word2007');
            $tables = [];
            foreach ($reloaded->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if ($element instanceof Table) {
                        $tables[] = $element;
                    }
                }
            }

            $this->assertCount(2, $tables, 'Expected both the hourly-rate table (our_fees) and the disbursement table to survive the pipeline.');
        } finally {
            @unlink($outputPath);
        }
    }

    public function test_admin_configured_clause_sequence_reorders_sections_and_renumbers(): void
    {
        $precedent = $this->makeCostsAgreementPrecedent([
            'clause_sequence' => [
                ['heading' => 'Governing Law', 'kind' => 'clause', 'tag_or_key' => 'governing_law'],
                ['heading' => 'The Work', 'kind' => 'clause', 'tag_or_key' => 'the_work'],
            ],
        ]);
        $documentRequest = $this->makeDocumentRequest($precedent, $this->costsAgreementAnswers());

        $result = app(CostsAgreementGenerator::class)->generate($documentRequest);
        $headings = collect($result['blocks'])->where('type', 'heading')->pluck('text')->values()->all();

        $this->assertSame(['1. Governing Law', '2. The Work'], $headings);
    }
}
