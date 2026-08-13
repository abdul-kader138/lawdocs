<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentRequestResource\Pages;
use App\Filament\Resources\DocumentRequestResource\RelationManagers;
use App\Models\Client;
use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Services\DocxToPdfConverter;
use App\Support\JurisdictionRules;
use App\Support\PartyGroupFormBuilder;
use App\Support\QuestionnaireFormBuilder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class DocumentRequestResource extends Resource
{
    protected static ?string $model = DocumentRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationLabel(): string
    {
        return 'Document Requests';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Wizard::make([
                Step::make('Precedent')
                    ->icon('heroicon-o-rectangle-stack')
                    ->schema([
                        Select::make('client_id')
                            ->label('Client (optional)')
                            ->helperText('Pick an existing client to prefill their details below — nothing here is required, and you can still edit everything afterwards.')
                            ->options(fn () => Client::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->createOptionForm([
                                TextInput::make('name')->label('Full Name')->required()->maxLength(255),
                                TextInput::make('email')->label('Email')->email()->maxLength(255),
                                TextInput::make('phone')->label('Phone')->tel()->maxLength(50),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $data['created_by'] = auth()->id();

                                return Client::create($data)->getKey();
                            })
                            ->afterStateUpdated(fn (Set $set, Get $get) => static::applyClientPrefill($set, $get)),

                        Select::make('precedent_id')
                            ->label('Precedent')
                            ->options(fn () => Precedent::query()->where('is_active', true)->pluck('title', 'id'))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                                // The Details step's fields are built dynamically from the
                                // chosen precedent's questionnaire config (see the schema
                                // closure below). Their state paths (e.g. "answers.testator_gender")
                                // don't exist in the form's data yet at this point, so any
                                // Alpine-entangled component (Select, Textarea, DatePicker) that
                                // renders for them fails with "Livewire property ... cannot be
                                // found" and silently stops syncing to the server. Pre-seeding
                                // every expected key with null here — before those components are
                                // ever rendered — registers the path so entangle can bind to it.
                                $precedent = $state ? Precedent::find($state) : null;

                                $set('answers', $precedent
                                    ? collect($precedent->questionnaireFieldsConfig())
                                        ->map(fn () => null)
                                        ->all()
                                    : []);

                                // Same "cannot be found" failure class as above, but for
                                // Repeaters specifically — they need an empty ARRAY seeded
                                // per declared party group, not null.
                                $set('parties', $precedent
                                    ? collect($precedent->partyGroupsConfig())
                                        ->mapWithKeys(fn ($group) => [$group['key'] => []])
                                        ->all()
                                    : []);

                                static::applyClientPrefill($set, $get);
                            }),
                    ]),

                Step::make('Details')
                    ->icon('heroicon-o-pencil-square')
                    ->schema(function (Get $get) {
                        $precedent = Precedent::find($get('precedent_id'));

                        if (! $precedent) {
                            return [Placeholder::make('no_precedent')->label('')->content('Select a precedent first.')];
                        }

                        $client = ($clientId = $get('client_id')) ? Client::find($clientId) : null;

                        return [
                            ...QuestionnaireFormBuilder::components($precedent),
                            ...PartyGroupFormBuilder::components($precedent, $client),
                        ];
                    }),

                Step::make('Review')
                    ->icon('heroicon-o-check-circle')
                    ->schema([
                        TextInput::make('case_reference')
                            ->label('Case / Matter Reference')
                            ->maxLength(255)
                            ->helperText('Optional — for your own filing reference.'),

                        Placeholder::make('minimum_age_warning')
                            ->label('')
                            ->content(fn (Get $get) => static::minimumAgeWarning($get))
                            ->visible(fn (Get $get) => static::minimumAgeWarning($get) !== null),

                        Placeholder::make('review_note')
                            ->label('')
                            ->content('Submitting will draft the document now (takes 10-30 seconds). Review your answers on the previous step before continuing.'),
                    ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        // Computed once per table render, not once per row — isAvailable()
        // shells out to `which soffice`, so this avoids N shell executions
        // for N rows.
        $pdfExportAvailable = app(DocxToPdfConverter::class)->isAvailable();

        return $table
            ->columns([
                TextColumn::make('precedent_title_snapshot')
                    ->label('Precedent')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('requestedBy.name')
                    ->label('Requested By')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ]),

                TextColumn::make('approval_status')
                    ->label('Approval')
                    ->state(function (DocumentRequest $record) {
                        if ($record->status !== 'completed') {
                            return '—';
                        }

                        return match (true) {
                            ! $record->requiresApproval() => 'Not required',
                            $record->isApproved() => 'Approved',
                            default => 'Needs review',
                        };
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Approved' => 'success',
                        'Needs review' => 'warning',
                        'Not required' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('case_reference')
                    ->label('Case Reference')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Client')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('This confirms the generated draft has been reviewed and may be downloaded. This does not check the wording for you.')
                    ->action(fn (DocumentRequest $record) => $record->update(['approved_at' => now(), 'approved_by' => auth()->id()]))
                    ->visible(fn (DocumentRequest $record) => $record->status === 'completed'
                        && $record->requiresApproval()
                        && ! $record->isApproved()
                        && Gate::allows('approve', $record)),

                Action::make('download')
                    ->label('Download .docx')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (DocumentRequest $record) => route('document-requests.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (DocumentRequest $record) => $record->isReadyForDownload()),

                Action::make('downloadPdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->url(fn (DocumentRequest $record) => route('document-requests.download-pdf', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (DocumentRequest $record) => $pdfExportAvailable && $record->isReadyForDownload()),

                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\WitnessesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocumentRequests::route('/'),
            'create' => Pages\CreateDocumentRequest::route('/create'),
            'view' => Pages\ViewDocumentRequest::route('/{record}'),
        ];
    }

    /**
     * Copies whatever a Client attribute is mapped to (Precedent::client_field_map,
     * configured by an admin on the precedent — see PrecedentResource's
     * "Client Mapping" tab) into the matching answers.* field. Runs after
     * EITHER the client or the precedent selector changes, since both are
     * needed before there's anything to map; a no-op until both are set.
     * Only ever sets fields the mapping actually names — never touches
     * anything the admin didn't opt into prefilling.
     */
    private static function applyClientPrefill(Set $set, Get $get): void
    {
        $client = ($clientId = $get('client_id')) ? Client::find($clientId) : null;
        $precedent = ($precedentId = $get('precedent_id')) ? Precedent::find($precedentId) : null;

        if (! $client || ! $precedent) {
            return;
        }

        $attributes = $client->toPrefillAttributes();

        foreach ($precedent->clientFieldMapConfig() as $clientAttribute => $questionnaireFieldName) {
            if (filled($attributes[$clientAttribute] ?? null)) {
                $set("answers.{$questionnaireFieldName}", $attributes[$clientAttribute]);
            }
        }
    }

    /**
     * Soft warning only (per JurisdictionRules docblock) — looks for a
     * conventionally-named "testator_dob"/"principal_dob" answer field and
     * flags when it implies an age below the jurisdiction's ordinary
     * minimum, since court exceptions exist and this app has no authority
     * to block on it.
     */
    private static function minimumAgeWarning(Get $get): ?string
    {
        $precedent = Precedent::find($get('precedent_id'));
        if (! $precedent) {
            return null;
        }

        $minimumAge = JurisdictionRules::minimumAge($precedent->jurisdiction, $precedent->category);
        if ($minimumAge === null) {
            return null;
        }

        $answers = $get('answers') ?? [];
        $dob = $answers['testator_dob'] ?? $answers['principal_dob'] ?? null;
        if (! $dob) {
            return null;
        }

        try {
            $age = Carbon::parse($dob)->age;
        } catch (\Throwable) {
            return null;
        }

        if ($age >= $minimumAge) {
            return null;
        }

        return "⚠ The recorded date of birth implies an age of {$age}, below the standard minimum age of "
            ."{$minimumAge} for a {$precedent->jurisdiction} ".str($precedent->category ?? 'document')->replace('_', ' ')
            .'. Court authorisation may be required — confirm with a solicitor before proceeding.';
    }
}
