<?php

namespace App\Filament\Resources\ClientResource\RelationManagers;

use App\Support\AustralianJurisdictions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(2)->schema([
                TextInput::make('name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('relationship')
                    ->label('Relationship to Client')
                    ->placeholder('e.g. Spouse, Son, Accountant')
                    ->maxLength(100),

                Select::make('gender')
                    ->label('Gender')
                    ->options(['male' => 'Male', 'female' => 'Female'])
                    ->native(false),

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

            Textarea::make('notes')
                ->label('Notes')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('relationship')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('email')
                    ->toggleable(),

                TextColumn::make('phone')
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
