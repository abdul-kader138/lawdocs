<?php

namespace App\Filament\Resources\PrecedentResource\Pages;

use App\Filament\Resources\PrecedentResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePrecedent extends CreateRecord
{
    protected static string $resource = PrecedentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
