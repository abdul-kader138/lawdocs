<?php

namespace App\Filament\Resources\PrecedentResource\RelationManagers;

use App\Models\PrecedentQaRun;
use App\Services\PrecedentQaService;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class QaRunsRelationManager extends RelationManager
{
    protected static string $relationship = 'qaRuns';
    protected static ?string $title = 'Quality Assurance Runs';

    public function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) { 'passed' => 'success', 'warning' => 'warning', default => 'danger' }),
            Tables\Columns\TextColumn::make('issues')
                ->label('Validation issues')
                ->state(fn (PrecedentQaRun $record) => count($record->issues ?? []))
                ->badge()
                ->color(fn (PrecedentQaRun $record) => collect($record->issues)->contains('severity', 'error')
                    ? 'danger'
                    : (count($record->issues ?? []) > 0 ? 'warning' : 'success'))
                ->description(fn (PrecedentQaRun $record) => data_get($record->issues, '0.message', 'No validation issues.'))
                ->wrap(),
            Tables\Columns\TextColumn::make('scenario_results')->label('Scenarios')->state(fn (PrecedentQaRun $record) => collect($record->scenario_results)->where('status', 'passed')->count().'/'.count($record->scenario_results ?? []).' passed'),
            Tables\Columns\IconColumn::make('stale')->label('Stale')->state(fn (PrecedentQaRun $record) => app(PrecedentQaService::class)->isStale($record))->boolean(),
            Tables\Columns\TextColumn::make('runner.name')->label('Run by'),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->actions([
            Tables\Actions\Action::make('report')
                ->label(fn (PrecedentQaRun $record) => count($record->issues ?? []) > 0 ? 'View issues' : 'View report')
                ->icon('heroicon-o-clipboard-document-check')
                ->button()
                ->color(fn (PrecedentQaRun $record) => $record->status === 'failed' ? 'danger' : ($record->status === 'warning' ? 'warning' : 'gray'))
                ->modalHeading(fn (PrecedentQaRun $record) => 'QA report · '.strtoupper($record->status))
                ->modalWidth('5xl')
                ->fillForm(fn (PrecedentQaRun $record) => [
                    'issues_report' => collect($record->issues)->map(fn ($issue) => strtoupper($issue['severity'])." [{$issue['code']}] {$issue['message']}")->implode("\n") ?: 'No validation issues.',
                    'scenario_report' => collect($record->scenario_results)->map(fn ($result) => strtoupper($result['status'])." — {$result['name']}".($result['failures'] ? "\n  ".implode("\n  ", $result['failures']) : ''))->implode("\n") ?: 'No scenarios were run.',
                    'comparison_report' => collect($record->comparison)->map(function ($change) {
                        $line = ($change['field'] ?? 'unknown').': '.($change['change'] ?? 'Changed');
                        if (array_key_exists('before', $change) || array_key_exists('after', $change)) {
                            $render = fn ($value) => is_array($value) ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : var_export($value, true);
                            $line .= "\nBEFORE:\n".$render($change['before'] ?? null)."\nAFTER:\n".$render($change['after'] ?? null);
                        }

                        return $line;
                    })->implode("\n\n") ?: 'No changes from baseline.',
                ])->form([
                    Textarea::make('issues_report')->label('Validation issues')->rows(10)->disabled(),
                    Textarea::make('scenario_report')->label('Scenario results')->rows(10)->disabled(),
                    Textarea::make('comparison_report')->label('Changes from baseline')->rows(10)->disabled(),
                ])->modalSubmitAction(false)->modalCancelActionLabel('Close'),
        ]);
    }
}
