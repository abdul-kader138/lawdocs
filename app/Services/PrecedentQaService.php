<?php

namespace App\Services;

use App\Models\Precedent;
use App\Models\PrecedentQaBaseline;
use App\Models\PrecedentQaRun;
use Illuminate\Support\Facades\Storage;

class PrecedentQaService
{
    private const SNAPSHOT_FIELDS = [
        'title', 'output_title_template', 'category', 'jurisdiction', 'generator_class',
        'description', 'questionnaire_fields', 'party_groups', 'clause_sequence',
        'formatting', 'client_field_map', 'requires_review', 'is_active', 'extracted_text',
    ];

    public function __construct(private readonly PrecedentScenarioRunner $scenarios) {}

    public function run(Precedent $precedent, int $userId): PrecedentQaRun
    {
        $precedent->refresh()->load(['testScenarios' => fn ($query) => $query->where('is_active', true), 'qaBaseline']);
        $snapshot = $this->snapshot($precedent);
        $issues = $this->validate($precedent);
        $scenarioResults = $precedent->testScenarios
            ->map(fn ($scenario) => $this->scenarios->run($scenario, $userId))
            ->values()->all();
        $comparison = $this->compare($precedent->qaBaseline?->snapshot, $snapshot);

        $hasErrors = collect($issues)->contains(fn ($issue) => $issue['severity'] === 'error')
            || collect($scenarioResults)->contains(fn ($result) => $result['status'] === 'failed');
        $hasWarnings = collect($issues)->contains(fn ($issue) => $issue['severity'] === 'warning');

        return $precedent->qaRuns()->create([
            'run_by' => $userId,
            'fingerprint' => $this->fingerprint($precedent, $snapshot),
            'status' => $hasErrors ? 'failed' : ($hasWarnings ? 'warning' : 'passed'),
            'issues' => $issues,
            'scenario_results' => $scenarioResults,
            'comparison' => $comparison,
            'snapshot' => $snapshot,
        ]);
    }

    public function setBaseline(Precedent $precedent, int $userId): PrecedentQaBaseline
    {
        $precedent->refresh();
        $snapshot = $this->snapshot($precedent);

        return PrecedentQaBaseline::updateOrCreate(
            ['precedent_id' => $precedent->id],
            ['set_by' => $userId, 'fingerprint' => $this->fingerprint($precedent, $snapshot), 'snapshot' => $snapshot],
        );
    }

    public function isStale(PrecedentQaRun $run): bool
    {
        return ! hash_equals($run->fingerprint, $this->currentFingerprint($run->precedent));
    }

    public function currentFingerprint(Precedent $precedent): string
    {
        return $this->fingerprint($precedent, $this->snapshot($precedent));
    }

    private function validate(Precedent $precedent): array
    {
        $issues = [];
        $add = function (string $severity, string $code, string $message) use (&$issues): void {
            $issues[] = compact('severity', 'code', 'message');
        };

        if (! $precedent->docx_path || ! Storage::disk('local')->exists($precedent->docx_path)) {
            $add('error', 'missing_template', 'The source DOCX file is missing from private storage.');
        }
        if (filled($precedent->clause_marker_error)) {
            $add('error', 'marker_error', $precedent->clause_marker_error);
        }

        $fieldNames = collect($precedent->questionnaire_fields ?? [])->pluck('name')->filter();
        foreach ($fieldNames->duplicates()->unique() as $name) {
            $add('error', 'duplicate_field', "Questionnaire field '{$name}' is declared more than once.");
        }
        $groupKeys = collect($precedent->party_groups ?? [])->pluck('key')->filter();
        foreach ($groupKeys->duplicates()->unique() as $key) {
            $add('error', 'duplicate_group', "Party group '{$key}' is declared more than once.");
        }

        if ($precedent->generator_class === 'template') {
            foreach (collect($precedent->questionnaire_fields ?? [])->where('required', true) as $field) {
                $name = $field['name'] ?? null;
                if ($name && ! str_contains((string) $precedent->extracted_text, "answers.{$name}")) {
                    $add('warning', 'unused_required_field', "Required field '{$name}' is not referenced by the uploaded template text.");
                }
            }
        }

        if ($precedent->testScenarios->isEmpty()) {
            $add('warning', 'no_scenarios', 'No active automated test scenarios are configured.');
        }

        return $issues;
    }

    public function snapshot(Precedent $precedent): array
    {
        $snapshot = collect(self::SNAPSHOT_FIELDS)->mapWithKeys(fn ($field) => [$field => $precedent->{$field}])->all();
        $snapshot['template_filename'] = $precedent->docx_original_filename;
        $snapshot['template_sha256'] = $precedent->docx_path && Storage::disk('local')->exists($precedent->docx_path)
            ? hash_file('sha256', Storage::disk('local')->path($precedent->docx_path))
            : null;
        $snapshot['test_scenarios'] = $precedent->testScenarios()->orderBy('id')->get([
            'name', 'answers', 'parties', 'expected_title', 'expected_includes', 'expected_excludes', 'is_active',
        ])->toArray();

        return $snapshot;
    }

    private function fingerprint(Precedent $precedent, array $snapshot): string
    {
        return hash('sha256', json_encode($this->canonicalize($snapshot), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->canonicalize($item), $value);
    }

    private function compare(?array $baseline, array $current): array
    {
        if ($baseline === null) {
            return [['field' => 'baseline', 'change' => 'No comparison baseline has been saved.']];
        }

        $changes = [];
        foreach (array_unique([...array_keys($baseline), ...array_keys($current)]) as $field) {
            if (($baseline[$field] ?? null) !== ($current[$field] ?? null)) {
                $changes[] = ['field' => $field, 'change' => 'Changed', 'before' => $baseline[$field] ?? null, 'after' => $current[$field] ?? null];
            }
        }

        return $changes;
    }
}
