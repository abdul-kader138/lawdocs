<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Filament\Resources\ClientResource\RelationManagers\ContactsRelationManager;
use App\Models\Client;
use App\Support\AustralianJurisdictions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A Client is the reusable "who is this document for" record — entered
 * once, then selected (not re-typed) on every future document request for
 * the same person. This is what the wizard's client_id selector and the
 * precedent's client_field_map prefill against (see
 * App\Filament\Resources\DocumentRequestResource and Client::toPrefillAttributes()).
 * ClientContact rows (managed in the relation manager below) are the same
 * idea for the people around the client — spouse, children, professionals
 * — importable into a document request's party groups.
 */
class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Identity')->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->label('Full Name')
                        ->required()
                        ->maxLength(255),

                    Select::make('gender')
                        ->label('Gender')
                        ->options(['male' => 'Male', 'female' => 'Female'])
                        ->native(false)
                        ->helperText('Used for pronoun agreement when prefilling a document.'),

                    DatePicker::make('dob')
                        ->label('Date of Birth'),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->label('Phone')
                        ->tel()
                        ->maxLength(50),
                ]),
            ]),

            Section::make('Address')->schema([
                Grid::make(4)->schema([
                    TextInput::make('street')
                        ->label('Street')
                        ->columnSpan(2)
                        ->maxLength(255),

                    TextInput::make('suburb')
                        ->label('Suburb')
                        ->maxLength(255),

                    Select::make('state')
                        ->label('State')
                        ->options(AustralianJurisdictions::OPTIONS)
                        ->native(false),

                    TextInput::make('postcode')
                        ->label('Postcode')
                        ->maxLength(10),
                ]),
            ]),

            Section::make('Notes')->schema([
                Textarea::make('notes')
                    ->label('')
                    ->rows(3)
                    ->helperText('Internal only — never appears in a generated document.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('phone')
                    ->toggleable(),

                TextColumn::make('contacts_count')
                    ->label('Contacts')
                    ->counts('contacts')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('document_requests_count')
                    ->label('Documents')
                    ->counts('documentRequests')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ContactsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'view' => Pages\ViewClient::route('/{record}'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
