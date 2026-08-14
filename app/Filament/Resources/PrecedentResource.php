<?php

namespace App\Filament\Resources;

use App\Contracts\DeclaresComputedBlocks;
use App\Contracts\DeclaresPartyFlags;
use App\Filament\Resources\PrecedentResource\Pages;
use App\Filament\Resources\PrecedentResource\RelationManagers\QaRunsRelationManager;
use App\Filament\Resources\PrecedentResource\RelationManagers\TestScenariosRelationManager;
use App\Models\Precedent;
use App\Models\Setting;
use App\Services\Generators\GeneratorRegistry;
use App\Support\AustralianJurisdictions;
use App\Support\DocumentFormattingProfiles;
use App\Support\PrecedentFlagResolver;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                        Grid::make(['default' => 1, 'md' => 2])->schema([
                            TextInput::make('title')
                                ->label('Title')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('output_title_template')
                                ->label('Generated document title')
                                ->maxLength(255)
                                ->placeholder('Defaults to the precedent title')
                                ->helperText('Optional dynamic title, e.g. "Costs Agreement — {{answers.client_name}}". This controls the Word heading and downloaded filename.'),

                            Select::make('category')
                                ->label('Category')
                                ->options(fn () => static::categoryOptionsForCurrentUser())
                                ->native(false),

                            Select::make('jurisdiction')
                                ->label('Jurisdiction')
                                ->options(AustralianJurisdictions::OPTIONS)
                                ->default('NSW')
                                ->required()
                                ->native(false)
                                ->helperText('Which state/territory\'s law this precedent is drafted for. Different states get different Precedent rows, never a code branch.'),
                        ]),

                        Select::make('generator_class')
                            ->label('Document Generator')
                            ->options(fn () => GeneratorRegistry::options())
                            ->required()
                            ->native(false)
                            ->helperText('Choose “Template” for a no-code document built entirely from the uploaded clauses. Specialist generators remain available for computed legal wording.'),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive precedents cannot be selected when requesting a new document.'),

                        Toggle::make('requires_review')
                            ->label('Requires review before download')
                            ->default(true)
                            ->helperText('While on, a completed document generated from this precedent cannot be downloaded until someone with precedent-management access approves it. Turn off only once the clause content has been solicitor-reviewed and the firm is comfortable releasing drafts unreviewed.'),
                    ]),
                ]),

                // ── Template File ────────────────────────────────────────
                Tab::make('Template File')->icon('heroicon-o-document')->schema([
                    Section::make('Precedent Document')
                        ->description('Upload the .docx precedent. Tag reusable clauses with [[CLAUSE:name]] ... [[/CLAUSE]] on their own lines. Inside a clause, [[IF:flag]]...[[ELSE]]...[[/IF]] and [[REPEAT:group AS alias]]...[[/REPEAT]] may nest freely, with {{answers.field}} / {{alias.field}} placeholders — see the reference below for what\'s valid for the selected Document Generator.')
                        ->schema([
                            Placeholder::make('marker_reference')
                                ->label('Available flags, party groups & computed blocks for this generator')
                                ->content(function (Get $get) {
                                    $generatorKey = $get('generator_class');
                                    if (! $generatorKey) {
                                        return 'Select a Document Generator above to see its available [[IF]] flags and [[REPEAT]] groups.';
                                    }

                                    try {
                                        $generator = app(GeneratorRegistry::class)->resolve($generatorKey);
                                    } catch (\RuntimeException) {
                                        return 'Unknown generator.';
                                    }

                                    if (! $generator instanceof DeclaresPartyFlags) {
                                        return 'This generator has no [[IF]]/[[REPEAT]] markers — only verbatim [[CLAUSE:...]] tags.';
                                    }

                                    // Every questionnaire field (any type) and every configured party
                                    // group is available here, for every generator — not just the
                                    // no-code template generator — merged with whatever extra
                                    // flags/groups the generator itself declares. Must stay in sync
                                    // with Precedent::validateMarkerReferences(), hence the shared
                                    // PrecedentFlagResolver rather than a second copy of this logic.
                                    $availableFlags = PrecedentFlagResolver::availableFlags($get('questionnaire_fields') ?? [], $generator);
                                    $availableGroups = PrecedentFlagResolver::availableGroups($get('party_groups') ?? [], $generator);

                                    $flags = collect($availableFlags)
                                        ->map(fn ($desc, $key) => "• {$key} — {$desc}")
                                        ->implode("\n");
                                    $groups = collect($availableGroups)
                                        ->map(fn ($desc, $key) => "• {$key} — {$desc}")
                                        ->implode("\n");

                                    $text = "Flags (for [[IF:...]] and Structure conditions):\n".($flags ?: '(add a questionnaire field)')
                                        ."\n\nParty groups (for [[REPEAT:...]]):\n".($groups ?: '(none)')
                                        ."\n\nOptional: tag a [[CLAUSE:front_matter]] block to replace this generator's "
                                        .'built-in opening paragraph(s) with your own admin-editable wording — omit it '
                                        .'to keep the built-in default.';

                                    if ($generator instanceof DeclaresComputedBlocks) {
                                        $computed = collect($generator->availableComputedBlocks())
                                            ->map(fn ($desc, $key) => "• {$key} — {$desc}")
                                            ->implode("\n");

                                        $text .= "\n\nComputed blocks (for Structure entries of kind \"Computed block\"):\n".($computed ?: '(none)');
                                    }

                                    return $text;
                                })
                                ->columnSpanFull(),

                            FileUpload::make('docx_path')
                                ->label('Precedent (.docx)')
                                ->disk('local')
                                ->directory('precedents')
                                ->visibility('private')
                                ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                                ->storeFileNamesIn('docx_original_filename')
                                ->required()
                                ->helperText('Only .docx files are accepted. Uploading a replacement updates future generations; existing generated documents remain unchanged.'),

                            Textarea::make('clause_marker_error')
                                ->label('Clause Marker Check')
                                ->rows(6)
                                ->disabled()
                                ->dehydrated(false)
                                ->visible(fn (?Precedent $record) => filled($record?->clause_marker_error))
                                ->helperText('A malformed [[CLAUSE:...]] marker, or a reference to an unknown flag/group/field, was found — fix it and re-upload before assigning this precedent to a document request.'),

                            Textarea::make('extracted_text')
                                ->label('Extracted Text (preview)')
                                ->rows(15)
                                ->disabled()
                                ->dehydrated(false)
                                ->helperText('Read-only, for a quick sanity check after uploading. This is a flattened reference view (# for headings, - for list items) — if it looks wrong or empty, the file may use tables/text boxes that don\'t extract reliably.'),
                        ]),
                ]),

                // ── Structure ────────────────────────────────────────────
                Tab::make('Structure')->icon('heroicon-o-bars-3')->schema([
                    Section::make('Section order for this precedent')
                        ->description('Optional. Leave empty to use the Document Generator\'s built-in default order. Fill this in to control — without a developer — which sections this precedent\'s generated document has, their order, headings, and (via an optional condition) whether each one is shown. Numbering is automatic and skips hidden sections. See the reference in the Template File tab for valid clause tags / computed block keys / condition flags for the selected Document Generator.')
                        ->schema([
                            Repeater::make('clause_sequence')
                                ->label('')
                                ->schema([
                                    Grid::make(['default' => 1, 'sm' => 2, 'xl' => 4])->schema([
                                        TextInput::make('heading')
                                            ->label('Section heading')
                                            ->maxLength(150)
                                            ->helperText('e.g. "Beneficiaries" — numbered automatically, do not type a number.'),

                                        Select::make('kind')
                                            ->label('Content source')
                                            ->options([
                                                'clause' => 'Clause tag (from the .docx)',
                                                'computed' => 'Computed block (from the generator)',
                                            ])
                                            ->default('clause')
                                            ->required()
                                            ->native(false)
                                            ->live(),

                                        TextInput::make('tag_or_key')
                                            ->label(fn (Get $get) => $get('kind') === 'computed' ? 'Computed block key' : 'Clause tag')
                                            ->required()
                                            ->maxLength(100)
                                            ->helperText(fn (Get $get) => $get('kind') === 'computed'
                                                ? 'One of the computed block keys the assigned Document Generator supports.'
                                                : 'A [[CLAUSE:tag]] present in the uploaded .docx.'),

                                        TextInput::make('condition')
                                            ->label('Show only if (optional)')
                                            ->maxLength(300)
                                            ->helperText('Same [[IF:...]] flag grammar as inside the docx. Leave blank to always show this section.'),
                                    ]),
                                ])
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => filled($state['heading'] ?? null)
                                        ? $state['heading']
                                        : (filled($state['tag_or_key'] ?? null) ? $state['tag_or_key'] : 'New section')
                                )
                                ->addActionLabel('Add section')
                                ->defaultItems(0),
                        ]),
                ]),

                // ── Formatting ───────────────────────────────────────────
                Tab::make('Formatting')->icon('heroicon-o-paint-brush')->schema([
                    Section::make('Professional document style')
                        ->description('Choose a professional starting profile, then adjust individual settings. Font and paragraph controls affect generator-authored text; text inside uploaded clause blocks keeps its original Word formatting. Margins, footer and page numbering apply to the whole generated document.')
                        ->schema([
                            Select::make('formatting.profile')
                                ->label('Formatting Profile')
                                ->options(DocumentFormattingProfiles::options())
                                ->default('legal_traditional')
                                ->native(false)
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    foreach (DocumentFormattingProfiles::values($state) as $key => $value) {
                                        $set("formatting.{$key}", $value);
                                    }
                                })
                                ->helperText('Legal Traditional is recommended for wills and agreements. Court Filing uses double spacing and a wider left margin.'),

                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                Select::make('formatting.font_family')
                                    ->label('Font Family')
                                    ->options(array_combine(
                                        ['Times New Roman', 'Arial', 'Calibri', 'Georgia', 'Garamond'],
                                        ['Times New Roman', 'Arial', 'Calibri', 'Georgia', 'Garamond'],
                                    ))
                                    ->placeholder(fn () => Setting::get('docx_font_family', 'Times New Roman').' (global default)')
                                    ->searchable()
                                    ->native(false),

                                TextInput::make('formatting.font_size')
                                    ->label('Font Size (pt)')
                                    ->numeric()
                                    ->minValue(8)
                                    ->maxValue(24)
                                    ->placeholder(fn () => Setting::get('docx_font_size', 12).' (global default)'),
                            ]),

                            Grid::make(['default' => 1, 'sm' => 2, 'xl' => 4])->schema([
                                Select::make('formatting.body_alignment')
                                    ->label('Body Alignment')
                                    ->options(['left' => 'Left', 'both' => 'Justified', 'center' => 'Centred'])
                                    ->native(false),

                                Select::make('formatting.line_spacing')
                                    ->label('Line Spacing')
                                    ->options(['1' => 'Single', '1.15' => '1.15', '1.5' => '1.5', '2' => 'Double'])
                                    ->native(false),

                                TextInput::make('formatting.paragraph_space_after')
                                    ->label('After Paragraph (pt)')
                                    ->numeric()->minValue(0)->maxValue(36),

                                Select::make('formatting.heading_bold')
                                    ->label('Heading Weight')
                                    ->options(['1' => 'Bold', '0' => 'Not bold'])
                                    ->placeholder('Use global default (Bold)')
                                    ->native(false),

                                TextInput::make('formatting.heading_size_step')
                                    ->label('Heading Size Step (pt per level)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(10)
                                    ->placeholder('2 (global default)')
                                    ->helperText('How many points larger each heading level is than the one below it.'),
                            ]),

                            Section::make('Paragraph indentation')->compact()->schema([
                                Grid::make(['default' => 1, 'md' => 3])->schema([
                                    TextInput::make('formatting.first_line_indent')->label('First Line (mm)')->numeric()->minValue(0)->maxValue(30),
                                    TextInput::make('formatting.left_indent')->label('Left Indent (mm)')->numeric()->minValue(0)->maxValue(60),
                                    TextInput::make('formatting.right_indent')->label('Right Indent (mm)')->numeric()->minValue(0)->maxValue(60),
                                ]),
                                Toggle::make('formatting.apply_paragraph_style_to_clauses')
                                    ->label('Apply paragraph style to uploaded clauses')
                                    ->helperText('Recommended for uniform output. Applies alignment, line spacing, paragraph spacing and indentation to clause paragraphs and lists, while preserving their bold, italic, font runs and numbering.'),
                            ]),

                            Section::make('Page layout')->compact()->schema([
                                Grid::make(['default' => 1, 'sm' => 2, 'xl' => 4])->schema([
                                    TextInput::make('formatting.margin_top')->label('Top Margin (mm)')->numeric()->minValue(10)->maxValue(60),
                                    TextInput::make('formatting.margin_right')->label('Right Margin (mm)')->numeric()->minValue(10)->maxValue(60),
                                    TextInput::make('formatting.margin_bottom')->label('Bottom Margin (mm)')->numeric()->minValue(10)->maxValue(60),
                                    TextInput::make('formatting.margin_left')->label('Left Margin (mm)')->numeric()->minValue(10)->maxValue(60),
                                ]),
                                Grid::make(['default' => 1, 'md' => 2])->schema([
                                    TextInput::make('formatting.footer_text')
                                        ->label('Footer Text')
                                        ->maxLength(200)
                                        ->placeholder('Optional confidentiality or firm notice'),
                                    Toggle::make('formatting.page_numbers')
                                        ->label('Show page numbers')
                                        ->helperText('Adds “Page X of Y” at the bottom-right.'),
                                ]),
                            ]),
                        ]),
                ]),

                // ── Questionnaire Fields ─────────────────────────────────
                Tab::make('Questionnaire Fields')->icon('heroicon-o-list-bullet')->schema([
                    Section::make('Fields staff must fill in for this precedent')
                        ->description('Each field becomes an input on the document-request form. The Document Generator reads answers by field name.')
                        ->schema([
                            Repeater::make('questionnaire_fields')
                                ->label('')
                                ->schema([
                                    Grid::make(['default' => 1, 'sm' => 2, 'xl' => 4])->schema([
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
                                                'text' => 'Text',
                                                'textarea' => 'Textarea (multi-line)',
                                                'number' => 'Number',
                                                'date' => 'Date',
                                                'boolean' => 'Yes / No',
                                                'select' => 'Select (fixed choices)',
                                            ])
                                            ->required()
                                            ->default('text')
                                            ->live(),

                                        Toggle::make('required')
                                            ->label('Required')
                                            ->default(true),
                                    ]),

                                    KeyValue::make('options')
                                        ->label('Choices (value => label)')
                                        ->keyLabel('Value')
                                        ->valueLabel('Label')
                                        ->visible(fn (Get $get) => $get('type') === 'select')
                                        ->helperText('e.g. male => Male, female => Female. Use lowercase values that match GrammarResolver\'s tables for anything gender-related.')
                                        ->columnSpanFull(),

                                    TextInput::make('description')
                                        ->label('Description (for reference)')
                                        ->required()
                                        ->maxLength(300)
                                        ->helperText('What does this field capture? Notes for whoever maintains the Document Generator code.')
                                        ->columnSpanFull(),
                                ])
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null)
                                        ? ($state['label'] ?? $state['name']).(($state['required'] ?? false) ? ' *' : '')
                                        : 'New field'
                                )
                                ->addActionLabel('Add field')
                                ->defaultItems(0),
                        ]),
                ]),

                // ── Client Mapping ────────────────────────────────────────
                Tab::make('Client Mapping')->icon('heroicon-o-identification')->schema([
                    Section::make('Prefill from a Client record')
                        ->description('Optional. When staff pick an existing Client on the document request wizard, any questionnaire field mapped here is filled in automatically from that Client\'s details — nothing here is required, and staff can still edit every field afterwards. Field names differ per precedent (e.g. "testator_name" on a Will vs "principal_name" on a Power of Attorney) — this mapping is what lets the wizard prefill correctly regardless.')
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 3])->schema(
                                collect([
                                    'name' => "Client's Full Name",
                                    'dob' => 'Date of Birth',
                                    'gender' => 'Gender',
                                    'email' => 'Email',
                                    'phone' => 'Phone',
                                    'street' => 'Street',
                                    'suburb' => 'Suburb',
                                    'state' => 'State',
                                    'postcode' => 'Postcode',
                                ])->map(fn (string $label, string $attribute) => Select::make("client_field_map.{$attribute}")
                                    ->label($label)
                                    ->options(fn (Get $get) => collect($get('questionnaire_fields') ?? [])
                                        ->filter(fn ($field) => filled($field['name'] ?? null))
                                        ->mapWithKeys(fn ($field) => [$field['name'] => $field['label'] ?? $field['name']])
                                        ->all())
                                    ->placeholder('Not mapped')
                                    ->native(false))->values()->all()
                            ),
                        ]),
                ]),

                // ── Party Groups ─────────────────────────────────────────
                Tab::make('Party Groups')->icon('heroicon-o-user-group')->schema([
                    Section::make('Repeatable party roles for this precedent')
                        ->description('e.g. Beneficiaries, Executors, Guardians. Each group becomes an "Add row" list on the document-request form, and a [[REPEAT:group_key]] marker target inside the .docx. The Document Generator reads rows by group key.')
                        ->schema([
                            Repeater::make('party_groups')
                                ->label('')
                                ->schema([
                                    Grid::make(['default' => 1, 'sm' => 2, 'xl' => 4])->schema([
                                        TextInput::make('key')
                                            ->label('Group key')
                                            ->required()
                                            ->regex('/^[a-z][a-z0-9_]*$/')
                                            ->helperText('e.g. beneficiaries')
                                            ->maxLength(50),

                                        TextInput::make('label')
                                            ->label('Staff-facing label')
                                            ->required()
                                            ->maxLength(100)
                                            ->helperText('e.g. "Beneficiaries"'),

                                        Select::make('role_type')
                                            ->label('Role type')
                                            ->options([
                                                'beneficiary' => 'Beneficiary',
                                                'executor' => 'Executor',
                                                'guardian' => 'Guardian',
                                                'attorney' => 'Attorney',
                                                'custom' => 'Custom',
                                            ])
                                            ->required()
                                            ->default('custom')
                                            ->native(false),

                                        TextInput::make('min_items')
                                            ->label('Min rows')
                                            ->numeric()
                                            ->default(0),
                                    ]),

                                    Grid::make(['default' => 1, 'sm' => 2, 'xl' => 4])->schema([
                                        TextInput::make('max_items')
                                            ->label('Max rows (blank = unlimited)')
                                            ->numeric(),

                                        TextInput::make('share_field')
                                            ->label('Share field name (optional)')
                                            ->helperText('Must match a field name below. If set, that field must sum to 100 across all rows.'),

                                        Toggle::make('supports_substitute')
                                            ->label('Supports substitute')
                                            ->helperText('Allows a row to point at another row as a specific fallback.'),

                                        Toggle::make('supports_per_stirpes')
                                            ->label('Supports per-stirpes')
                                            ->helperText('Surfaces a per-stirpes toggle field for this group.'),
                                    ]),

                                    Repeater::make('fields')
                                        ->label('Fields captured per row')
                                        ->schema([
                                            Grid::make(['default' => 1, 'sm' => 2, 'xl' => 4])->schema([
                                                TextInput::make('name')
                                                    ->label('Field name')
                                                    ->required()
                                                    ->regex('/^[a-z][a-z0-9_]*$/')
                                                    ->helperText('e.g. name, share, gender')
                                                    ->maxLength(50),

                                                TextInput::make('label')
                                                    ->label('Row-facing label')
                                                    ->required()
                                                    ->maxLength(100),

                                                Select::make('type')
                                                    ->label('Input type')
                                                    ->options([
                                                        'text' => 'Text',
                                                        'textarea' => 'Textarea (multi-line)',
                                                        'number' => 'Number',
                                                        'date' => 'Date',
                                                        'boolean' => 'Yes / No',
                                                        'select' => 'Select (fixed choices)',
                                                    ])
                                                    ->required()
                                                    ->default('text')
                                                    ->live(),

                                                Toggle::make('required')
                                                    ->label('Required')
                                                    ->default(true),
                                            ]),

                                            KeyValue::make('options')
                                                ->label('Choices (value => label)')
                                                ->keyLabel('Value')
                                                ->valueLabel('Label')
                                                ->visible(fn (Get $get) => $get('type') === 'select')
                                                ->helperText('e.g. male => Male, female => Female. Use lowercase values that match GrammarResolver\'s tables for anything gender-related.')
                                                ->columnSpanFull(),
                                        ])
                                        ->reorderable()
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null)
                                                ? ($state['label'] ?? $state['name']).(($state['required'] ?? false) ? ' *' : '')
                                                : 'New field'
                                        )
                                        ->addActionLabel('Add field')
                                        ->defaultItems(0)
                                        ->columnSpanFull(),
                                ])
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => filled($state['key'] ?? null) ? ($state['label'] ?? $state['key']) : 'New group'
                                )
                                ->addActionLabel('Add party group')
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

                BadgeColumn::make('generator_class')
                    ->label('Generator')
                    ->color(fn (?string $state) => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (?string $state) => $state ? (GeneratorRegistry::options()[$state] ?? $state) : 'Not set'),

                TextColumn::make('questionnaire_fields')
                    ->label('Fields')
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state).' fields' : '0 fields')
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
            'index' => Pages\ListPrecedents::route('/'),
            'create' => Pages\CreatePrecedent::route('/create'),
            'edit' => Pages\EditPrecedent::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            TestScenariosRelationManager::class,
            QaRunsRelationManager::class,
        ];
    }

    /**
     * Hides out-of-scope categories from the list/count entirely for a
     * category-restricted user (User::precedent_categories) — the list-page
     * half of the scoping; PrecedentPolicy::view/update/delete is the
     * direct-URL-access half. Unrestricted users (precedent_categories
     * null/empty — the default for everyone) see every precedent, unchanged.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $categories = auth()->user()?->precedent_categories;
        if (! empty($categories)) {
            $query->whereIn('category', $categories);
        }

        return $query;
    }

    /**
     * The category Select's own options, restricted the same way — a
     * category-restricted user shouldn't even be offered a category they
     * can't manage when creating/editing a precedent.
     */
    private static function categoryOptionsForCurrentUser(): array
    {
        $all = [
            'will' => 'Will',
            'power_of_attorney' => 'Power of Attorney',
            'enduring_guardianship' => 'Enduring Guardianship',
            'costs_agreement' => 'Costs Agreement',
            'trust' => 'Trust',
            'other' => 'Other',
        ];

        $categories = auth()->user()?->precedent_categories;
        if (empty($categories)) {
            return $all;
        }

        return collect($all)->only($categories)->all();
    }
}
