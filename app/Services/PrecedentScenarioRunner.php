<?php

namespace App\Services;

use App\Models\DocumentRequest;
use App\Models\PrecedentTestScenario;
use App\Services\Generators\GeneratorRegistry;
use Illuminate\Support\Facades\DB;

class PrecedentScenarioRunner
{
    public function __construct(
        private readonly DocumentRequestPartyPersister $partyPersister,
        private readonly GeneratorRegistry $registry,
    ) {}

    public function run(PrecedentTestScenario $scenario, int $userId): array
    {
        DB::beginTransaction();

        try {
            $precedent = $scenario->precedent;
            $request = DocumentRequest::create([
                'precedent_id' => $precedent->id,
                'precedent_title_snapshot' => $precedent->title,
                'precedent_jurisdiction_snapshot' => $precedent->jurisdiction,
                'requested_by' => $userId,
                'case_reference' => 'QA-'.$scenario->id,
                'answers' => $scenario->answers ?? [],
                'status' => 'pending',
            ]);

            $this->partyPersister->persist($request, $precedent, $scenario->parties ?? []);
            $draft = $this->registry->resolve($precedent->generator_class)->generate($request);
            $text = $this->flatten($draft['blocks'] ?? []);
            $failures = [];

            if (filled($scenario->expected_title) && $draft['title'] !== $scenario->expected_title) {
                $failures[] = "Expected title '{$scenario->expected_title}', got '{$draft['title']}'.";
            }

            foreach ($scenario->expected_includes ?? [] as $expected) {
                if (filled($expected) && ! str_contains($text, $expected)) {
                    $failures[] = "Expected output to include: {$expected}";
                }
            }

            foreach ($scenario->expected_excludes ?? [] as $unexpected) {
                if (filled($unexpected) && str_contains($text, $unexpected)) {
                    $failures[] = "Expected output to exclude: {$unexpected}";
                }
            }

            return ['name' => $scenario->name, 'status' => $failures === [] ? 'passed' : 'failed', 'failures' => $failures, 'title' => $draft['title']];
        } catch (\Throwable $e) {
            return ['name' => $scenario->name, 'status' => 'failed', 'failures' => [$e->getMessage()], 'title' => null];
        } finally {
            DB::rollBack();
        }
    }

    private function flatten(array $blocks): string
    {
        return collect($blocks)->map(function ($block) {
            if (($block['type'] ?? null) !== 'raw') {
                return (string) ($block['text'] ?? '');
            }

            return collect($block['elements'] ?? [])->map(function ($element) {
                $data = $element instanceof \JsonSerializable ? $element->jsonSerialize() : (array) $element;

                return (string) ($data['text'] ?? '');
            })->implode("\n");
        })->implode("\n");
    }
}
