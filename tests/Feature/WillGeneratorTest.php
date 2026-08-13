<?php

namespace Tests\Feature;

use App\Exceptions\ClauseNotFoundException;
use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Models\User;
use App\Services\DocxBuilder;
use App\Services\Generators\WillGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\Support\WillPrecedentFixture;
use Tests\TestCase;

class WillGeneratorTest extends TestCase
{
    use RefreshDatabase;
    use WillPrecedentFixture;

    private function makeDocumentRequest(Precedent $precedent, array $answers, ?array $beneficiaryRows = null): DocumentRequest
    {
        $documentRequest = DocumentRequest::create([
            'precedent_id' => $precedent->id,
            'precedent_title_snapshot' => $precedent->title,
            'requested_by' => User::factory()->create()->id,
            'answers' => $answers,
            'status' => 'pending',
        ]);

        $this->attachBeneficiaries($documentRequest, $beneficiaryRows);

        return $documentRequest;
    }

    public function test_generates_expected_structure_with_no_alternate_executor(): void
    {
        $precedent = $this->makeWillPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->willAnswers());

        $result = app(WillGenerator::class)->generate($documentRequest);

        $this->assertSame('Last Will and Testament of Ashley Dewell', $result['title']);

        $text = collect($result['blocks'])->pluck('text')->filter()->implode(' | ');
        $this->assertStringContainsString('Ashley Dewell of 1 First Street, Sydney in the State of New South Wales', $text);
        $this->assertStringContainsString('I appoint Alfred Smith as my executor. If he is unable to act or continue to act as my executor then I appoint the Public Trustee as my executor.', $text);
    }

    public function test_alternate_executor_chains_to_public_trustee_with_correct_pronouns(): void
    {
        $precedent = $this->makeWillPrecedent();
        $answers = $this->willAnswers() + [
            'alternate_executor_name' => 'Bernadette Smith',
            'alternate_executor_gender' => 'female',
        ];
        $documentRequest = $this->makeDocumentRequest($precedent, $answers);

        $result = app(WillGenerator::class)->generate($documentRequest);
        $text = collect($result['blocks'])->pluck('text')->filter()->implode(' | ');

        $this->assertStringContainsString(
            'I appoint Alfred Smith as my executor. If he is unable to act or continue to act as my executor '
            .'then I appoint Bernadette Smith as my executor, provided that if she does not survive me then '
            .'I appoint the Public Trustee as my executor.',
            $text
        );
    }

    private function flattenAllText(array $result): string
    {
        $flatText = collect($result['blocks'])->pluck('text')->filter()->implode(' | ');
        $rawText = collect($result['blocks'])->where('type', 'raw')
            ->flatMap(fn ($b) => collect($b['elements'])->flatMap(fn ($el) => array_column($el->runs, 'text')))
            ->implode(' | ');

        return $flatText.' | '.$rawText;
    }

    public function test_beneficiary_survivorship_uses_correct_pronoun_per_beneficiary(): void
    {
        $precedent = $this->makeWillPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->willAnswers());

        $text = $this->flattenAllText(app(WillGenerator::class)->generate($documentRequest));

        $this->assertStringContainsString('I give my 50% of my estate to Alfred Smith.', $text);
        $this->assertStringContainsString(
            'If he does not survive me, leaving children that do survive me, then those children shall '
            .'take equally the share that would have been received by him.',
            $text
        );
        $this->assertStringContainsString('I give my 50% of my estate to Bernadette Smith.', $text);
        $this->assertStringContainsString(
            'If she does not survive me, leaving children that do survive me, then those children shall '
            .'take equally the share that would have been received by her.',
            $text
        );
    }

    public function test_beneficiary_without_per_stirpes_gets_no_substitution_proviso(): void
    {
        $precedent = $this->makeWillPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->willAnswers(), [
            ['name' => 'Alfred Smith', 'share' => 100, 'gender' => 'male', 'per_stirpes' => false],
        ]);

        $text = $this->flattenAllText(app(WillGenerator::class)->generate($documentRequest));

        $this->assertStringContainsString('I give my 100% of my estate to Alfred Smith.', $text);
        $this->assertStringNotContainsString('does not survive me', $text);
    }

    public function test_verbatim_clause_text_appears_unparaphrased(): void
    {
        $precedent = $this->makeWillPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->willAnswers());

        $result = app(WillGenerator::class)->generate($documentRequest);

        $rawBlocks = collect($result['blocks'])->where('type', 'raw');
        $allText = $rawBlocks->flatMap(fn ($b) => collect($b['elements'])->flatMap(fn ($el) => array_column($el->runs, 'text')))->implode(' ');

        $this->assertStringContainsString('I revoke all prior wills and testamentary acts made by me.', $allText);
        $this->assertStringContainsString('exercise any powers given to them by law', $allText);
    }

    public function test_admin_configured_clause_sequence_reorders_sections_and_renumbers(): void
    {
        $precedent = $this->makeWillPrecedent([
            'clause_sequence' => [
                ['heading' => 'Executor Powers', 'kind' => 'clause', 'tag_or_key' => 'executor_powers'],
                ['heading' => 'Revocation', 'kind' => 'clause', 'tag_or_key' => 'revocation'],
            ],
        ]);
        $documentRequest = $this->makeDocumentRequest($precedent, $this->willAnswers());

        $result = app(WillGenerator::class)->generate($documentRequest);
        $headings = collect($result['blocks'])->where('type', 'heading')->pluck('text')->values()->all();

        $this->assertSame(['1. Executor Powers', '2. Revocation'], $headings);

        // Sections left out of the admin-configured sequence (Executor, Beneficiaries)
        // don't appear at all — the sequence fully overrides the default, it doesn't merge.
        $allText = $this->flattenAllText($result);
        $this->assertStringNotContainsString('I appoint Alfred Smith as my executor', $allText);
    }

    public function test_precedent_without_front_matter_tag_uses_built_in_opening_sentence(): void
    {
        $precedent = $this->makeWillPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->willAnswers());

        $result = app(WillGenerator::class)->generate($documentRequest);
        $text = collect($result['blocks'])->pluck('text')->filter()->implode(' | ');

        $this->assertStringContainsString('This is the last will and testament of Ashley Dewell', $text);
        $this->assertStringNotContainsString('CUSTOM FRONT MATTER', $text);
    }

    public function test_precedent_with_front_matter_tag_overrides_built_in_opening_sentence(): void
    {
        $precedent = $this->makeWillPrecedent([], includeFrontMatter: true);
        $documentRequest = $this->makeDocumentRequest($precedent, $this->willAnswers());

        $result = app(WillGenerator::class)->generate($documentRequest);
        $text = $this->flattenAllText($result);

        $this->assertStringContainsString('CUSTOM FRONT MATTER for Ashley Dewell.', $text);
        $this->assertStringNotContainsString('This is the last will and testament of', $text);
    }

    public function test_missing_clause_tag_throws_instead_of_producing_an_incomplete_document(): void
    {
        Storage::fake('local');
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('placeholder — no clause markers at all');
        $tmp = tempnam(sys_get_temp_dir(), 'bad_precedent_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/bad.docx', file_get_contents($tmp));
        @unlink($tmp);

        $precedent = Precedent::create([
            'title' => 'Broken Precedent', 'docx_path' => 'precedents/bad.docx',
            'generator_class' => 'will', 'questionnaire_fields' => [], 'is_active' => true,
        ]);
        $documentRequest = $this->makeDocumentRequest($precedent, $this->willAnswers());

        $this->expectException(ClauseNotFoundException::class);
        app(WillGenerator::class)->generate($documentRequest);
    }

    public function test_full_pipeline_through_docxbuilder_preserves_style_fidelity(): void
    {
        $precedent = $this->makeWillPrecedent();
        $documentRequest = $this->makeDocumentRequest($precedent, $this->willAnswers());

        $result = app(WillGenerator::class)->generate($documentRequest);

        $outputPath = tempnam(sys_get_temp_dir(), 'generated_will_').'.docx';

        try {
            app(DocxBuilder::class)->buildAndSave($result['title'], $result['blocks'], $outputPath);

            $reloaded = IOFactory::load($outputPath, 'Word2007');
            $boldTextFound = false;

            foreach ($reloaded->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (! method_exists($element, 'getElements')) {
                        continue;
                    }
                    foreach ($element->getElements() as $child) {
                        if ($child instanceof Text && str_contains((string) $child->getText(), 'I revoke all prior wills')) {
                            $font = $child->getFontStyle();
                            $boldTextFound = is_object($font) && $font->isBold();
                        }
                    }
                }
            }

            $this->assertTrue($boldTextFound, 'The revocation clause\'s bold formatting must survive the full precedent -> generator -> DocxBuilder pipeline.');
        } finally {
            @unlink($outputPath);
        }
    }
}
