<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Resources\ClientResource;
use App\Models\Client;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Identity')->schema([
                TextEntry::make('name'),
                TextEntry::make('email')->placeholder('—'),
                TextEntry::make('phone')->placeholder('—'),
                TextEntry::make('dob')->label('Date of Birth')->date()->placeholder('—'),
            ])->columns(4),

            Section::make('Document History')
                ->schema([
                    RepeatableEntry::make('documentRequests')
                        ->label('')
                        ->schema([
                            TextEntry::make('precedent_title_snapshot')->label('Precedent'),
                            TextEntry::make('status')->badge()->color(fn (string $state) => match ($state) {
                                'completed' => 'success',
                                'failed' => 'danger',
                                'processing' => 'warning',
                                default => 'gray',
                            }),
                            TextEntry::make('created_at')->label('Requested')->dateTime('d M Y'),
                        ])
                        ->columns(3),
                ])
                ->visible(fn (Client $record) => $record->documentRequests()->exists()),
        ]);
    }
}
