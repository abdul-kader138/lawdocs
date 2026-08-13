<?php

namespace App\Filament\Widgets;

use App\Models\DocumentRequest;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentDocumentRequestsWidget extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(DocumentRequest::query()->latest()->limit(8))
            ->columns([
                Tables\Columns\TextColumn::make('precedent_title_snapshot')
                    ->label('Precedent'),

                Tables\Columns\TextColumn::make('requestedBy.name')
                    ->label('Requested By'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since(),
            ])
            ->paginated(false);
    }
}
