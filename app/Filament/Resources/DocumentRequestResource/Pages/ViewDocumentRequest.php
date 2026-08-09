<?php

namespace App\Filament\Resources\DocumentRequestResource\Pages;

use App\Filament\Resources\DocumentRequestResource;
use App\Models\DocumentRequest;
use Filament\Actions\Action;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentRequest extends ViewRecord
{
    protected static string $resource = DocumentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download .docx')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (DocumentRequest $record) => route('document-requests.download', $record))
                ->openUrlInNewTab()
                ->visible(fn (DocumentRequest $record) => $record->status === 'completed'),

            Action::make('regenerate')
                ->label('Regenerate')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->url(fn (DocumentRequest $record) => DocumentRequestResource::getUrl('create', ['regenerate_from' => $record->id])),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Request')->schema([
                TextEntry::make('precedent_title_snapshot')->label('Precedent'),
                TextEntry::make('requestedBy.name')->label('Requested By'),
                TextEntry::make('case_reference')->label('Case Reference')->placeholder('—'),
                TextEntry::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'completed' => 'success',
                    'failed'    => 'danger',
                    'processing' => 'warning',
                    default     => 'gray',
                }),
                TextEntry::make('error_message')->label('Error')->visible(fn (DocumentRequest $record) => $record->status === 'failed'),
                TextEntry::make('generated_title')->label('Generated Title')->placeholder('—'),
                TextEntry::make('generated_at')->dateTime()->placeholder('—'),
            ])->columns(2),

            Section::make('Submitted Answers')->schema([
                KeyValueEntry::make('answers')->label(''),
            ]),
        ]);
    }
}
