<?php

namespace App\Filament\Resources\PrecedentResource\Pages;

use App\Filament\Resources\PrecedentResource;
use App\Models\Precedent;
use App\Services\PrecedentQaService;
use App\Support\FieldCsvRowValidator;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EditPrecedent extends EditRecord
{
    protected static string $resource = PrecedentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('runQa')
                ->label('Run QA')->icon('heroicon-o-clipboard-document-check')->color('success')
                ->action(function (Precedent $record) {
                    $run = app(PrecedentQaService::class)->run($record, auth()->id());
                    $issues = collect($run->issues ?? []);
                    $scenarioResults = collect($run->scenario_results ?? []);
                    $failedScenarios = $scenarioResults->where('status', '!=', 'passed');

                    $details = $issues
                        ->map(fn (array $issue) => strtoupper($issue['severity']).': '.$issue['message'])
                        ->merge($failedScenarios->map(fn (array $result) => 'SCENARIO FAILED: '.$result['name']))
                        ->take(3);

                    $body = $issues->count().' validation issue(s); '
                        .$scenarioResults->where('status', 'passed')->count().'/'.$scenarioResults->count().' scenario(s) passed.';

                    if ($details->isNotEmpty()) {
                        $body .= "\n\n".$details->implode("\n");
                        if ($issues->count() + $failedScenarios->count() > $details->count()) {
                            $body .= "\nMore details are available below.";
                        }
                        $body .= "\nSelect View issues in Quality Assurance Runs for the full report.";
                    }

                    $notification = Notification::make()
                        ->title('QA run '.strtoupper($run->status))
                        ->body($body)
                        ->color(match ($run->status) { 'passed' => 'success', 'warning' => 'warning', default => 'danger' });

                    if ($run->status !== 'passed') {
                        $notification->persistent();
                    }

                    $notification->send();
                }),

            Actions\ActionGroup::make([
            Actions\Action::make('setQaBaseline')
                ->label('Set QA Baseline')->icon('heroicon-o-bookmark-square')->color('gray')->requiresConfirmation()
                ->modalDescription('Future QA runs will compare the configuration and source-template checksum against the current state.')
                ->action(function (Precedent $record) {
                    app(PrecedentQaService::class)->setBaseline($record, auth()->id());
                    Notification::make()->success()->title('QA comparison baseline saved')->send();
                }),

            Actions\Action::make('downloadTemplate')
                ->label('Download Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (Precedent $record) => Storage::disk('local')->download(
                    $record->docx_path,
                    $record->docx_original_filename ?: Str::slug($record->title).'.docx',
                )),

            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Creates an inactive copy with its own template file and all questionnaire, party, structure, mapping and formatting settings.')
                ->action(function (Precedent $record) {
                    $extension = pathinfo($record->docx_path, PATHINFO_EXTENSION) ?: 'docx';
                    $copyPath = 'precedents/'.Str::uuid().'.'.$extension;

                    if (! Storage::disk('local')->copy($record->docx_path, $copyPath)) {
                        throw new \RuntimeException('The precedent template could not be copied.');
                    }

                    try {
                        $copy = $record->replicate();
                        $copy->title = $record->title.' (Copy)';
                        $copy->docx_path = $copyPath;
                        $copy->is_active = false;
                        $copy->created_by = auth()->id();
                        $copy->save();
                    } catch (\Throwable $e) {
                        Storage::disk('local')->delete($copyPath);
                        throw $e;
                    }

                    Notification::make()
                        ->success()
                        ->title('Precedent duplicated as an inactive draft')
                        ->send();

                    return redirect(PrecedentResource::getUrl('edit', ['record' => $copy]));
                }),

            Actions\Action::make('downloadFieldsCsvSample')
                ->label('Download Sample CSV (Questionnaire Fields)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => response()->streamDownload(
                    fn () => print(self::questionnaireFieldsCsvSample()),
                    'questionnaire-fields-sample.csv',
                    ['Content-Type' => 'text/csv'],
                )),

            Actions\Action::make('importFieldsCsv')
                ->label('Import Fields from CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    Placeholder::make('csv_help')
                        ->label('Expected columns')
                        ->content('name, label, type, required, description, options — one row per field. '
                            .'type must be one of: '.implode(', ', FieldCsvRowValidator::VALID_TYPES).'. '
                            .'required accepts yes/true/1 or no/false/0 (blank = required, to match a new field\'s default). '
                            .'options (only for type "select") is pipe-separated key=Label pairs, e.g. male=Male|female=Female. '
                            .'A row whose name matches an existing field updates it in place; otherwise it\'s appended. '
                            .'Use "Download Sample CSV" above for a ready-made example file.'),

                    FileUpload::make('csv')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (Precedent $record, array $data) {
                    $this->importQuestionnaireFieldsCsv($record, $data['csv']);
                }),

            Actions\Action::make('downloadPartyGroupFieldsCsvSample')
                ->label('Download Sample CSV (Party Group Fields)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => response()->streamDownload(
                    fn () => print(self::partyGroupFieldsCsvSample()),
                    'party-group-fields-sample.csv',
                    ['Content-Type' => 'text/csv'],
                )),

            Actions\Action::make('importPartyGroupFieldsCsv')
                ->label('Import Party Group Fields from CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    Placeholder::make('csv_help')
                        ->label('Expected columns')
                        ->content('group_key, name, label, type, required, description, options — one row per row-field. '
                            .'group_key must match an existing Party Group key on this precedent (create the group on the Party Groups tab first — this only fills in its fields). '
                            .'The other columns are the same as the Questionnaire Fields import above. '
                            .'Use "Download Sample CSV" above for a ready-made example file.'),

                    FileUpload::make('csv')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (Precedent $record, array $data) {
                    $this->importPartyGroupFieldsCsv($record, $data['csv']);
                }),

            ])->label('More actions')->icon('heroicon-m-ellipsis-vertical')->color('gray')->button(),
        ];
    }

    private static function questionnaireFieldsCsvSample(): string
    {
        $rows = [
            ['name', 'label', 'type', 'required', 'description', 'options'],
            ['full_name', 'Full Name', 'text', 'yes', 'Full legal name of the party', ''],
            ['date_of_birth', 'Date of Birth', 'date', 'yes', 'Date of birth of the party', ''],
            ['gender', 'Gender', 'select', 'no', 'Gender of the party', 'male=Male|female=Female|other=Other'],
            ['notes', 'Additional Notes', 'textarea', 'no', 'Any additional notes about the party', ''],
        ];

        return self::rowsToCsv($rows);
    }

    private static function partyGroupFieldsCsvSample(): string
    {
        $rows = [
            ['group_key', 'name', 'label', 'type', 'required', 'description', 'options'],
            ['spouse', 'full_name', 'Full Name', 'text', 'yes', 'Full legal name of the spouse', ''],
            ['spouse', 'date_of_birth', 'Date of Birth', 'date', 'yes', 'Date of birth of the spouse', ''],
        ];

        return self::rowsToCsv($rows);
    }

    /** @param  array<int, array<int, string>>  $rows */
    private static function rowsToCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    private function importQuestionnaireFieldsCsv(Precedent $record, UploadedFile $csv): void
    {
        ['header' => $header, 'rows' => $rows] = FieldCsvRowValidator::readCsv($csv->getRealPath());
        $hasRequiredColumn = in_array('required', $header, true);

        $imported = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNumber = $i + 2; // +1 for zero-index, +1 for the header row already shifted off.
            if (FieldCsvRowValidator::isBlankRow($row)) {
                continue;
            }

            $raw = FieldCsvRowValidator::rowToAssoc($header, $row);
            ['field' => $field, 'errors' => $rowErrors] = FieldCsvRowValidator::validate($raw, $hasRequiredColumn);

            if ($rowErrors !== []) {
                $name = trim((string) ($raw['name'] ?? ''));
                $errors[] = "Row {$rowNumber} (\"{$name}\"): ".implode('; ', $rowErrors);

                continue;
            }

            // Last row wins on a duplicate name within one CSV.
            $imported[$field['name']] = $field;
        }

        $existingNames = collect($record->questionnaire_fields ?? [])->pluck('name')->filter()->all();
        $updatedCount = count(array_intersect_key($imported, array_flip($existingNames)));
        $addedCount = count($imported) - $updatedCount;

        if ($imported !== []) {
            $existing = collect($record->questionnaire_fields ?? [])->keyBy('name');
            $merged = $existing->merge(collect($imported))->values()->all();

            $record->update(['questionnaire_fields' => $merged]);
            $this->refreshFormData(['questionnaire_fields']);
        }

        Notification::make()
            ->title(count($imported).' field(s) imported ('.$addedCount.' added, '.$updatedCount.' updated)'.($errors !== [] ? ', '.count($errors).' row(s) skipped' : ''))
            ->body($errors !== [] ? implode("\n", $errors) : null)
            ->color($errors === [] ? 'success' : 'warning')
            ->send();
    }

    private function importPartyGroupFieldsCsv(Precedent $record, UploadedFile $csv): void
    {
        ['header' => $header, 'rows' => $rows] = FieldCsvRowValidator::readCsv($csv->getRealPath());
        $hasRequiredColumn = in_array('required', $header, true);
        $hasGroupKeyColumn = in_array('group_key', $header, true);

        $existingGroups = collect($record->party_groups ?? []);
        $existingGroupKeys = $existingGroups->pluck('key')->filter()->all();

        // Field rows to import, grouped by target party group key.
        $importedByGroup = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNumber = $i + 2;
            if (FieldCsvRowValidator::isBlankRow($row)) {
                continue;
            }

            $raw = FieldCsvRowValidator::rowToAssoc($header, $row);
            $groupKey = trim((string) ($raw['group_key'] ?? ''));
            $name = trim((string) ($raw['name'] ?? ''));

            if (! $hasGroupKeyColumn || $groupKey === '') {
                $errors[] = "Row {$rowNumber} (\"{$name}\"): group_key is required";

                continue;
            }
            if (! in_array($groupKey, $existingGroupKeys, true)) {
                $errors[] = "Row {$rowNumber} (\"{$name}\"): no Party Group with key \"{$groupKey}\" exists on this precedent yet — create it on the Party Groups tab first";

                continue;
            }

            ['field' => $field, 'errors' => $rowErrors] = FieldCsvRowValidator::validate($raw, $hasRequiredColumn);

            if ($rowErrors !== []) {
                $errors[] = "Row {$rowNumber} (\"{$name}\", group \"{$groupKey}\"): ".implode('; ', $rowErrors);

                continue;
            }

            // Last row wins on a duplicate name within the same group in one CSV.
            $importedByGroup[$groupKey][$field['name']] = $field;
        }

        $addedCount = 0;
        $updatedCount = 0;

        if ($importedByGroup !== []) {
            $merged = $existingGroups->map(function ($group) use ($importedByGroup, &$addedCount, &$updatedCount) {
                $imported = $importedByGroup[$group['key']] ?? null;
                if ($imported === null) {
                    return $group;
                }

                $existingFieldNames = collect($group['fields'] ?? [])->pluck('name')->filter()->all();
                $updatedCount += count(array_intersect_key($imported, array_flip($existingFieldNames)));
                $addedCount += count($imported) - count(array_intersect_key($imported, array_flip($existingFieldNames)));

                $existingFields = collect($group['fields'] ?? [])->keyBy('name');
                $group['fields'] = $existingFields->merge(collect($imported))->values()->all();

                return $group;
            })->values()->all();

            $record->update(['party_groups' => $merged]);
            $this->refreshFormData(['party_groups']);
        }

        $importedCount = $addedCount + $updatedCount;

        Notification::make()
            ->title($importedCount.' field(s) imported ('.$addedCount.' added, '.$updatedCount.' updated)'.($errors !== [] ? ', '.count($errors).' row(s) skipped' : ''))
            ->body($errors !== [] ? implode("\n", $errors) : null)
            ->color($errors === [] ? 'success' : 'warning')
            ->send();
    }
}
