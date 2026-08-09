<?php

namespace App\Filament\Resources\DocumentRequestResource\Pages;

use App\Filament\Resources\DocumentRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentRequests extends ListRecords
{
    protected static string $resource = DocumentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
