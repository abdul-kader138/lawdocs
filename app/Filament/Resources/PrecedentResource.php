<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrecedentResource\Pages;
use App\Models\Precedent;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PrecedentResource extends Resource
{
    protected static ?string $model = Precedent::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationLabel(): string
    {
        return 'Precedents';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('precedent_tabs')->tabs([

                // ── Details ──────────────────────────────────────────────
                Tab::make('Details')->icon('heroicon-o-information-circle')->schema([
                    Section::make('Identity')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('title')
                                ->label('Title')
                                ->required()
                                ->maxLength(255),

                            Select::make('category')
                                ->label('Category')
                                ->options([
                                    'will'                => 'Will',
                                    'power_of_attorney'   => 'Power of Attorney',
                                    'trust'               => 'Trust',
                                    'other'               => 'Other',
                                ])
                                ->native(false),
                        ]),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive precedents cannot be selected when requesting a new document.'),
                    ]),
                ]),

                // ── Template File ────────────────────────────────────────
                Tab::make('Template File')->icon('heroicon-o-document')->schema([
                    Section::make('Precedent Document')
                        ->description('Upload the .docx precedent. Its text is extracted automatically as a style/structure reference for Claude.')
                        ->schema([
                            \Filament\Forms\Components\FileUpload::make('docx_path')
                                ->label('Precedent (.docx)')
                                ->disk('local')
                                ->directory('precedents')
                                ->visibility('private')
                                ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                ->storeFileNamesIn('docx_original_filename')
                                ->required()
                                ->helperText('Only .docx files are accepted.'),

                            Textarea::make('extracted_text')
                                ->label('Extracted Text (preview)')
                                ->rows(15)
                                ->disabled()
                                ->dehydrated(false)
                                ->helperText('Read-only. This is what Claude sees as the style/structure reference — if it looks wrong or empty after uploading, the file may use tables/text boxes that don\'t extract reliably.'),
                        ]),
                ]),

                // ── Questionnaire Fields ─────────────────────────────────
                Tab::make('Questionnaire Fields')->icon('heroicon-o-list-bullet')->schema([
                    Section::make('Fields staff must fill in for this precedent')
                        ->description('Each field becomes an input on the document-request form, and its description is given to Claude as an instruction.')
                        ->schema([
                            Repeater::make('questionnaire_fields')
                                ->label('')
                                ->schema([
                                    Grid::make(4)->schema([
                                        TextInput::make('name')
                                            ->label('Field name')
                                            ->required()
                                            ->regex('/^[a-z][a-z0-9_]*$/')
                                            ->helperText('e.g. testator_name')
                                            ->maxLength(50),

                                        TextInput::make('label')
                                            ->label('Staff-facing label')
                                            ->required()
                                            ->maxLength(100)
                                            ->helperText('e.g. "Testator\'s full name"'),

                                        Select::make('type')
                                            ->label('Input type')
                                            ->options([
                                                'text'     => 'Text',
                                                'textarea' => 'Textarea (multi-line)',
                                                'number'   => 'Number',
                                                'date'     => 'Date',
                                                'boolean'  => 'Yes / No',
                                            ])
                                            ->required()
                                            ->default('text'),

                                        Toggle::make('required')
                                            ->label('Required')
                                            ->default(true),
                                    ]),

                                    TextInput::make('description')
                                        ->label('Description (instruction for Claude)')
                                        ->required()
                                        ->maxLength(300)
                                        ->helperText('What does this field capture, and how should Claude use it?')
                                        ->columnSpanFull(),
                                ])
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string =>
                                    filled($state['name'] ?? null)
                                        ? ($state['label'] ?? $state['name']) . (($state['required'] ?? false) ? ' *' : '')
                                        : 'New field'
                                )
                                ->addActionLabel('Add field')
                                ->defaultItems(0),
                        ]),
                ]),

            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('category')
                    ->formatStateUsing(fn (?string $state) => $state ? str($state)->replace('_', ' ')->title() : '—'),

                TextColumn::make('questionnaire_fields')
                    ->label('Fields')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . ' fields' : '0 fields')
                    ->badge()
                    ->color('gray'),

                ToggleColumn::make('is_active')
                    ->label('Active'),

                TextColumn::make('updated_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('title')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPrecedents::route('/'),
            'create' => Pages\CreatePrecedent::route('/create'),
            'edit'   => Pages\EditPrecedent::route('/{record}/edit'),
        ];
    }
}
