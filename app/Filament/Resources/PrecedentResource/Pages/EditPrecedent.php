<?php

namespace App\Filament\Resources\PrecedentResource\Pages;

use App\Filament\Resources\PrecedentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPrecedent extends EditRecord
{
    protected static string $resource = PrecedentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
