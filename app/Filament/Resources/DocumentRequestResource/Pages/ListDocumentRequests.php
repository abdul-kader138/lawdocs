<?php

namespace App\Filament\Resources\DocumentRequestResource\Pages;

use App\Filament\Resources\DocumentRequestResource;
use App\Jobs\GenerateDocumentJob;
use App\Models\Client;
use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Services\DocumentRequestPartyPersister;
use App\Support\BatchCsvRowValidator;
use App\Support\FieldCsvRowValidator;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ListDocumentRequests extends ListRecords
{
    protected static string $resource = DocumentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New Document Request')->icon('heroicon-o-plus'),

            Actions\ActionGroup::make([
                Actions\Action::make('downloadBatchCsvTemplate')
                    ->label('Download Batch CSV Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->authorize('create', DocumentRequest::class)
                    ->form([
                        Select::make('precedent_id')
                            ->label('Precedent')
                            ->options(fn () => Precedent::query()->where('is_active', true)->pluck('title', 'id'))
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (array $data) {
                        Gate::authorize('create', DocumentRequest::class);

                        $precedent = Precedent::findOrFail($data['precedent_id']);
                        $header = BatchCsvRowValidator::buildTemplateHeader($precedent);
                        $csv = implode(',', $header)."\n";

                        return Response::streamDownload(
                            fn () => print ($csv),
                            Str::slug($precedent->title).'-batch-template.csv',
                            ['Content-Type' => 'text/csv'],
                        );
                    }),

            Actions\Action::make('batchGenerateCsv')
                ->label('Batch Generate from CSV')
                ->icon('heroicon-o-document-plus')
                ->color('gray')
                ->authorize('create', DocumentRequest::class)
                ->steps([
                    Step::make('Precedent & CSV')
                        ->schema([
                            Select::make('precedent_id')
                                ->label('Precedent')
                                ->options(fn () => Precedent::query()->where('is_active', true)->pluck('title', 'id'))
                                ->required()
                                ->live()
                                ->native(false),

                            Placeholder::make('format_help')
                                ->label('Expected columns for this precedent')
                                ->content(function (Get $get) {
                                    $precedent = $get('precedent_id') ? Precedent::find($get('precedent_id')) : null;
                                    if (! $precedent) {
                                        return 'Choose a precedent to see its exact column list.';
                                    }

                                    return new HtmlString(
                                        'One row per document. Columns: <code>'
                                        .implode('</code>, <code>', BatchCsvRowValidator::buildTemplateHeader($precedent))
                                        .'</code>. Leave a party-group slot\'s columns entirely blank to omit that row '
                                        .'(e.g. only fill beneficiaries.1.* for a document with one beneficiary). '
                                        .'Maximum '.BatchCsvRowValidator::MAX_ROWS.' rows per file.'
                                    );
                                }),

                            FileUpload::make('csv')
                                ->label('CSV File')
                                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                                ->storeFiles(false)
                                ->required()
                                ->live(),
                        ]),

                    Step::make('Preview')
                        ->schema([
                            Placeholder::make('preview')
                                ->label('')
                                ->content(function (Get $get) {
                                    $summary = self::summarizeBatch($get('precedent_id'), $get('csv'));

                                    return new HtmlString(self::renderSummaryHtml($summary));
                                }),
                        ]),
                ])
                ->modalSubmitActionLabel('Confirm & Generate')
                ->action(function (array $data) {
                    Gate::authorize('create', DocumentRequest::class);

                    $precedent = Precedent::findOrFail($data['precedent_id']);
                    $summary = self::summarizeBatch($data['precedent_id'], $data['csv']);

                    if ($summary['header_errors'] !== []) {
                        Notification::make()
                            ->danger()
                            ->title('The CSV could not be processed')
                            ->body(implode("\n", $summary['header_errors']))
                            ->persistent()
                            ->send();

                        return;
                    }

                    $completed = 0;
                    $failed = 0;

                    foreach ($summary['valid_rows'] as $row) {
                        $documentRequest = DocumentRequest::create([
                            'precedent_id' => $precedent->id,
                            'precedent_title_snapshot' => $precedent->title,
                            'precedent_jurisdiction_snapshot' => $precedent->jurisdiction,
                            'client_id' => $row['client_id'],
                            'requested_by' => auth()->id(),
                            'case_reference' => $row['case_reference'],
                            'answers' => $row['answers'],
                            'status' => 'pending',
                        ]);

                        app(DocumentRequestPartyPersister::class)->persist($documentRequest, $precedent, $row['parties']);

                        // Always synchronous regardless of the
                        // async_generation_enabled Setting — see
                        // GenerateDocumentJob's docblock: dispatch() silently
                        // strands documents with no queue worker running.
                        GenerateDocumentJob::dispatchSync($documentRequest);

                        $documentRequest->refresh()->status === 'completed' ? $completed++ : $failed++;
                    }

                    $skippedCount = count($summary['skip_messages']);

                    Notification::make()
                        ->title(
                            "{$completed} document(s) generated"
                            .($failed > 0 ? ", {$failed} failed" : '')
                            .($skippedCount > 0 ? ", {$skippedCount} row(s) skipped" : '')
                        )
                        ->body($summary['skip_messages'] !== [] ? implode("\n", $summary['skip_messages']) : null)
                        ->color($failed > 0 ? 'danger' : ($skippedCount > 0 ? 'warning' : 'success'))
                        ->persistent()
                        ->send();
                }),

            ])->label('Batch tools')->icon('heroicon-o-table-cells')->color('gray')->button(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')->badge(DocumentRequest::count()),
            'mine' => Tab::make('My Requests')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('requested_by', auth()->id()))
                ->badge(DocumentRequest::where('requested_by', auth()->id())->count()),
            'in_progress' => Tab::make('In Progress')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['pending', 'processing']))
                ->badge(DocumentRequest::whereIn('status', ['pending', 'processing'])->count())
                ->badgeColor('warning'),
            'needs_review' => Tab::make('Needs Review')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed')->whereNull('approved_at')
                    ->whereHas('precedent', fn (Builder $query) => $query->where('requires_review', true)))
                ->badge(DocumentRequest::where('status', 'completed')->whereNull('approved_at')
                    ->whereHas('precedent', fn (Builder $query) => $query->where('requires_review', true))->count())
                ->badgeColor('warning'),
            'failed' => Tab::make('Failed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'failed'))
                ->badge(DocumentRequest::where('status', 'failed')->count())
                ->badgeColor('danger'),
        ];
    }

    /**
     * Single source of truth for both the live Step-2 preview and the final
     * confirm action, so the previewed count and the actually-created count
     * can never drift apart. Re-parses the CSV fresh each call (cheap —
     * in-memory upload, no persistence) rather than trusting client state.
     *
     * @return array{
     *     header_errors: array<int, string>,
     *     valid_rows: array<int, array{answers: array, parties: array, case_reference: ?string, client_id: ?int}>,
     *     valid_count: int,
     *     skip_messages: array<int, string>,
     * }
     */
    private static function summarizeBatch(mixed $precedentId, mixed $csv): array
    {
        $empty = ['header_errors' => [], 'valid_rows' => [], 'valid_count' => 0, 'skip_messages' => []];

        // A FileUpload field's live (pre-dehydration) state is always
        // uuid-keyed array<string, TemporaryUploadedFile|string> — even for
        // a non-multiple field — because Filament only collapses it to a
        // bare value in dehydrateStateUsing() at actual submission time
        // (see BaseFileUpload::configure()). The final action() closure's
        // $data['csv'] is already the dehydrated single file; this Get()
        // call site (used for the live Step-2 preview) is the one that
        // still sees the raw array and must unwrap it the same way
        // BaseFileUpload's own afterStateUpdated hook does (Arr::first()).
        if (is_array($csv)) {
            $csv = Arr::first($csv);
        }

        if (! $precedentId || ! $csv instanceof UploadedFile) {
            return $empty;
        }

        $precedent = Precedent::find($precedentId);
        if (! $precedent) {
            return $empty;
        }

        ['header' => $header, 'rows' => $rows] = FieldCsvRowValidator::readCsv($csv->getRealPath());

        if (count($rows) > BatchCsvRowValidator::MAX_ROWS) {
            return [
                ...$empty,
                'header_errors' => ['This CSV has '.count($rows).' data rows; the maximum per batch is '
                    .BatchCsvRowValidator::MAX_ROWS.'. Split it into multiple files.'],
            ];
        }

        ['columns' => $columns, 'errors' => $headerErrors] = BatchCsvRowValidator::parseHeader($header, $precedent);
        if ($headerErrors !== []) {
            return [...$empty, 'header_errors' => $headerErrors];
        }

        $validClientIds = Client::query()->pluck('id');

        $validRows = [];
        $skipMessages = [];

        foreach ($rows as $i => $row) {
            $rowNumber = $i + 2;
            if (FieldCsvRowValidator::isBlankRow($row)) {
                continue;
            }

            $raw = FieldCsvRowValidator::rowToAssoc($header, $row);
            $result = BatchCsvRowValidator::validateRow($raw, $columns, $precedent, $validClientIds);

            if ($result['errors'] !== []) {
                $label = $result['case_reference'] ? "Row {$rowNumber} (\"{$result['case_reference']}\")" : "Row {$rowNumber}";
                $skipMessages[] = "{$label}: ".implode('; ', $result['errors']);

                continue;
            }

            $validRows[] = $result['data'];
        }

        return [
            'header_errors' => [],
            'valid_rows' => $validRows,
            'valid_count' => count($validRows),
            'skip_messages' => $skipMessages,
        ];
    }

    /** @param array{header_errors: array, valid_rows: array, valid_count: int, skip_messages: array} $summary */
    private static function renderSummaryHtml(array $summary): string
    {
        if ($summary['header_errors'] !== []) {
            $items = implode('', array_map(fn ($e) => '<li>'.e($e).'</li>', $summary['header_errors']));

            return "<strong>This CSV can't be processed:</strong><ul>{$items}</ul>";
        }

        $html = '<strong>'.$summary['valid_count'].' document(s) will be generated';
        if ($summary['skip_messages'] !== []) {
            $html .= ', '.count($summary['skip_messages']).' row(s) will be skipped';
        }
        $html .= '.</strong>';

        if ($summary['skip_messages'] !== []) {
            $items = implode('', array_map(fn ($e) => '<li>'.e($e).'</li>', $summary['skip_messages']));
            $html .= "<ul>{$items}</ul>";
        }

        return $html;
    }
}
