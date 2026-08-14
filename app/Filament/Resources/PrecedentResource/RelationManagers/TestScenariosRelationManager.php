<?php

namespace App\Filament\Resources\PrecedentResource\RelationManagers;

use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TestScenariosRelationManager extends RelationManager
{
    protected static string $relationship = 'testScenarios';
    protected static ?string $title = 'Automated Test Scenarios';

    public function form(Form $form): Form
    {
        $encode = fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $state;
        $decode = fn ($state) => blank($state) ? [] : json_decode($state, true, 512, JSON_THROW_ON_ERROR);

        return $form->schema([
            TextInput::make('name')->required()->maxLength(150),
            Toggle::make('is_active')->default(true),
            Textarea::make('answers')->label('Answers (JSON object)')->required()->rows(10)
                ->formatStateUsing($encode)->dehydrateStateUsing($decode)->rules(['json'])
                ->helperText('Example: {"client_name":"Jane Doe","has_children":true}'),
            Textarea::make('parties')->label('Party rows (JSON object)')->rows(10)
                ->formatStateUsing($encode)->dehydrateStateUsing($decode)->rules(['nullable', 'json'])
                ->helperText('Keys are party-group names; values are arrays of row objects.'),
            TextInput::make('expected_title')->maxLength(255),
            TagsInput::make('expected_includes')->label('Output must include'),
            TagsInput::make('expected_excludes')->label('Output must not include'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\IconColumn::make('is_active')->boolean(),
            Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
        ])->headerActions([Tables\Actions\CreateAction::make()->label('New automated test scenario')])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }
}
