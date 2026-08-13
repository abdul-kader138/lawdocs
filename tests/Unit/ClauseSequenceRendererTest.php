<?php

namespace Tests\Unit;

use App\Exceptions\ClauseTemplateException;
use App\Models\Precedent;
use App\Services\Clause\ClauseSequenceRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;
use Tests\TestCase;

class ClauseSequenceRendererTest extends TestCase
{
    use RefreshDatabase;

    /** A precedent with three trivial verbatim clauses: section_a/section_b/section_c. */
    private function makeThreeSectionPrecedent(array $overrides = []): Precedent
    {
        Storage::fake('local');

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        foreach (['section_a', 'section_b', 'section_c'] as $tag) {
            $section->addText("[[CLAUSE:{$tag}]]");
            $section->addText("Body of {$tag}.");
            $section->addText('[[/CLAUSE]]');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sequence_precedent_').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        Storage::disk('local')->put('precedents/sequence.docx', file_get_contents($tmp));
        @unlink($tmp);

        return Precedent::create(array_merge([
            'title' => 'Sequence Test Precedent',
            'docx_path' => 'precedents/sequence.docx',
            'generator_class' => 'will',
            'questionnaire_fields' => [],
            'is_active' => true,
        ], $overrides));
    }

    private function fallback(): array
    {
        return [
            ['heading' => 'Section A', 'kind' => 'clause', 'tag_or_key' => 'section_a', 'condition' => null],
            ['heading' => 'Section B', 'kind' => 'clause', 'tag_or_key' => 'section_b', 'condition' => null],
            ['heading' => 'Section C', 'kind' => 'clause', 'tag_or_key' => 'section_c', 'condition' => null],
        ];
    }

    /** @return array<int, string> flattened "N. Heading" + body text pairs, in render order */
    private function flatten(array $blocks): array
    {
        return collect($blocks)
            ->map(function ($block) {
                if ($block['type'] === 'heading') {
                    return $block['text'];
                }
                if ($block['type'] === 'raw') {
                    return collect($block['elements'])
                        ->flatMap(fn ($el) => array_column($el->runs, 'text'))
                        ->implode('');
                }

                return $block['text'] ?? '';
            })
            ->all();
    }

    public function test_empty_clause_sequence_falls_back_to_generator_default(): void
    {
        $precedent = $this->makeThreeSectionPrecedent();

        $blocks = app(ClauseSequenceRenderer::class)->render($precedent, [], $this->fallback());

        $this->assertSame(
            ['1. Section A', 'Body of section_a.', '2. Section B', 'Body of section_b.', '3. Section C', 'Body of section_c.'],
            $this->flatten($blocks)
        );
    }

    public function test_admin_configured_sequence_fully_overrides_fallback_order(): void
    {
        $precedent = $this->makeThreeSectionPrecedent([
            'clause_sequence' => [
                ['heading' => 'Third First', 'kind' => 'clause', 'tag_or_key' => 'section_c'],
                ['heading' => 'Then First', 'kind' => 'clause', 'tag_or_key' => 'section_a'],
            ],
        ]);

        $blocks = app(ClauseSequenceRenderer::class)->render($precedent, [], $this->fallback());

        $this->assertSame(
            ['1. Third First', 'Body of section_c.', '2. Then First', 'Body of section_a.'],
            $this->flatten($blocks)
        );
    }

    public function test_hidden_section_is_skipped_and_remaining_sections_renumber_without_a_gap(): void
    {
        $precedent = $this->makeThreeSectionPrecedent([
            'clause_sequence' => [
                ['heading' => 'Section A', 'kind' => 'clause', 'tag_or_key' => 'section_a'],
                ['heading' => 'Section B', 'kind' => 'clause', 'tag_or_key' => 'section_b', 'condition' => 'show_b'],
                ['heading' => 'Section C', 'kind' => 'clause', 'tag_or_key' => 'section_c'],
            ],
        ]);

        $blocks = app(ClauseSequenceRenderer::class)->render($precedent, ['flags' => ['show_b' => false]], $this->fallback());

        $this->assertSame(
            ['1. Section A', 'Body of section_a.', '2. Section C', 'Body of section_c.'],
            $this->flatten($blocks)
        );
    }

    public function test_condition_true_keeps_the_section_and_numbers_it_in_place(): void
    {
        $precedent = $this->makeThreeSectionPrecedent([
            'clause_sequence' => [
                ['heading' => 'Section A', 'kind' => 'clause', 'tag_or_key' => 'section_a'],
                ['heading' => 'Section B', 'kind' => 'clause', 'tag_or_key' => 'section_b', 'condition' => 'show_b'],
            ],
        ]);

        $blocks = app(ClauseSequenceRenderer::class)->render($precedent, ['flags' => ['show_b' => true]], $this->fallback());

        $this->assertSame(
            ['1. Section A', 'Body of section_a.', '2. Section B', 'Body of section_b.'],
            $this->flatten($blocks)
        );
    }

    public function test_computed_entry_dispatches_to_resolver_with_key_and_context(): void
    {
        $precedent = $this->makeThreeSectionPrecedent([
            'clause_sequence' => [
                ['heading' => 'Computed Section', 'kind' => 'computed', 'tag_or_key' => 'greeting'],
            ],
        ]);

        $context = ['answers' => ['name' => 'Ashley']];
        $seenKey = null;
        $seenContext = null;

        $blocks = app(ClauseSequenceRenderer::class)->render(
            $precedent,
            $context,
            $this->fallback(),
            function (string $key, array $ctx) use (&$seenKey, &$seenContext) {
                $seenKey = $key;
                $seenContext = $ctx;

                return ['type' => 'paragraph', 'text' => "Hello, {$ctx['answers']['name']}."];
            }
        );

        $this->assertSame('greeting', $seenKey);
        $this->assertSame($context, $seenContext);
        $this->assertSame(['1. Computed Section', 'Hello, Ashley.'], $this->flatten($blocks));
    }

    public function test_computed_entry_with_no_resolver_throws(): void
    {
        $precedent = $this->makeThreeSectionPrecedent([
            'clause_sequence' => [
                ['heading' => 'Computed Section', 'kind' => 'computed', 'tag_or_key' => 'greeting'],
            ],
        ]);

        $this->expectException(RuntimeException::class);

        app(ClauseSequenceRenderer::class)->render($precedent, [], $this->fallback());
    }

    public function test_condition_referencing_unknown_flag_throws(): void
    {
        $precedent = $this->makeThreeSectionPrecedent([
            'clause_sequence' => [
                ['heading' => 'Section A', 'kind' => 'clause', 'tag_or_key' => 'section_a', 'condition' => 'nonexistent_flag'],
            ],
        ]);

        $this->expectException(ClauseTemplateException::class);

        app(ClauseSequenceRenderer::class)->render($precedent, ['flags' => []], $this->fallback());
    }
}
