<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentRequestResource\Pages;
use App\Models\DocumentRequest;
use App\Models\Precedent;
use App\Support\QuestionnaireFormBuilder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                        Select::make('precedent_id')
                            ->label('Precedent')
                            ->options(fn () => Precedent::query()->where('is_active', true)->pluck('title', 'id'))
                            ->required()
                            ->searchable()
                            ->live(),
                    ]),

                Step::make('Details')
                    ->icon('heroicon-o-pencil-square')
                    ->schema(function (Get $get) {
                        $precedent = Precedent::find($get('precedent_id'));

                        if (! $precedent) {
                            return [Placeholder::make('no_precedent')->label('')->content('Select a precedent first.')];
                        }

                        return QuestionnaireFormBuilder::components($precedent);
                    }),

                Step::make('Review')
                    ->icon('heroicon-o-check-circle')
                    ->schema([
                        TextInput::make('case_reference')
                            ->label('Case / Matter Reference')
                            ->maxLength(255)
                            ->helperText('Optional — for your own filing reference.'),

                        Placeholder::make('review_note')
                            ->label('')
                            ->content('Submitting will draft the document now (takes 10-30 seconds). Review your answers on the previous step before continuing.'),
                    ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('precedent_title_snapshot')
                    ->label('Precedent')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('requestedBy.name')
                    ->label('Requested By')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->colors([
                        'gray'    => 'pending',
                        'warning' => 'processing',
                        'success' => 'completed',
                        'danger'  => 'failed',
                    ]),

                TextColumn::make('case_reference')
                    ->label('Case Reference')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (DocumentRequest $record) => route('document-requests.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (DocumentRequest $record) => $record->status === 'completed'),

                \Filament\Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDocumentRequests::route('/'),
            'create' => Pages\CreateDocumentRequest::route('/create'),
            'view'   => Pages\ViewDocumentRequest::route('/{record}'),
        ];
    }
}
