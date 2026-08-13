<?php

namespace App\Filament\Resources\DocumentRequestResource\RelationManagers;

use App\Models\DocumentRequest;
use App\Rules\WitnessNotAParty;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Records who actually witnessed the signing, after the fact (signing
 * happens on paper, outside this app) — this is a firm file-keeping record,
 * not a live attestation flow. See JurisdictionRules::minimumWitnesses()
 * (surfaced on the parent ViewDocumentRequest page's "Signing" section) for
 * the soft, guidance-only expected count, and WitnessNotAParty for the one
 * hard check this app CAN make from data it already has: a witness must
 * not also be a party to the document.
 */
class WitnessesRelationManager extends RelationManager
{
    protected static string $relationship = 'witnesses';

    /**
     * This relation manager only ever appears on ViewDocumentRequest (there
     * is no separate edit page) — Filament's default is to treat relation
     * managers on ViewRecord pages as read-only, which would silently hide
     * the create/edit/delete actions this workflow needs.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        /** @var DocumentRequest $documentRequest */
        $documentRequest = $this->getOwnerRecord();

        return $form->schema([
            TextInput::make('name')
                ->label('Full Name')
                ->required()
                ->maxLength(255)
                ->rule(new WitnessNotAParty($documentRequest)),

            TextInput::make('address')
                ->label('Address')
                ->maxLength(255),

            TextInput::make('occupation')
                ->label('Occupation')
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('address')->toggleable(),
                TextColumn::make('occupation')->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        $data['position'] = $this->getOwnerRecord()->witnesses()->count();

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
