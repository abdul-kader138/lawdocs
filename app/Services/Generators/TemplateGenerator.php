<?php

namespace App\Services\Generators;

use App\Contracts\DeclaresPartyFlags;
use App\Contracts\DocumentGenerator;
use App\Models\DocumentRequest;
use App\Services\Clause\ClauseSequenceRenderer;
use App\Services\ClauseLibrary;
use App\Services\PartyGroupAssembler;
use App\Services\TemplateStringRenderer;

/**
 * No-code generator for precedents whose content can be expressed entirely
 * with clause markers, questionnaire placeholders and repeatable party rows.
 */
class TemplateGenerator implements DeclaresPartyFlags, DocumentGenerator
{
    public function __construct(
        private readonly ClauseSequenceRenderer $sequence,
        private readonly ClauseLibrary $clauses,
        private readonly PartyGroupAssembler $parties,
        private readonly TemplateStringRenderer $strings,
    ) {}

    public function generate(DocumentRequest $documentRequest): array
    {
        $precedent = $documentRequest->precedent;
        $answers = $documentRequest->answers ?? [];

        // Boolean questionnaire answers are directly usable as [[IF:key]]
        // flags. This keeps no-code precedents self-contained.
        $flags = collect($answers)
            ->filter(fn ($value) => is_bool($value))
            ->mapWithKeys(fn ($value, $key) => [$key => $value])
            ->all();

        $context = [
            'answers' => $answers,
            'flags' => $flags,
            'items' => $this->parties->forRequest($documentRequest),
        ];

        $fallback = collect(array_keys($this->clauses->allFor($precedent)))
            ->map(fn (string $tag) => [
                'heading' => str($tag)->replace('_', ' ')->title()->toString(),
                'kind' => 'clause',
                'tag_or_key' => $tag,
                'condition' => null,
            ])
            ->all();

        $titleTemplate = filled($precedent->output_title_template)
            ? $precedent->output_title_template
            : $precedent->title;

        return [
            'title' => $this->strings->render($titleTemplate, ['answers' => $answers]),
            'blocks' => $this->sequence->render($precedent, $context, $fallback),
        ];
    }

    public function availableFlags(): array
    {
        return [];
    }

    public function availableGroups(): array
    {
        return [];
    }
}
