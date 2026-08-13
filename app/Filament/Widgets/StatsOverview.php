<?php

namespace App\Filament\Widgets;

use App\Models\DocumentRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        return [
            Stat::make('In Progress', DocumentRequest::whereIn('status', ['pending', 'processing'])->count())
                ->description('Requests being drafted')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Completed', DocumentRequest::where('status', 'completed')->count())
                ->description('Drafts generated successfully')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Failed', DocumentRequest::where('status', 'failed')->count())
                ->description('Generation errors')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('Awaiting Review', DocumentRequest::query()
                ->where('status', 'completed')
                ->whereNull('approved_at')
                ->whereHas('precedent', fn ($query) => $query->where('requires_review', true))
                ->count())
                ->description('Completed, not yet approved for download')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color('gray'),
        ];
    }
}
