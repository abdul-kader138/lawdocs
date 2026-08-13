<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DocumentRequestsChartWidget;
use App\Filament\Widgets\RecentDocumentRequestsWidget;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\WelcomeHeaderWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            WelcomeHeaderWidget::class,
            StatsOverview::class,
            DocumentRequestsChartWidget::class,
            RecentDocumentRequestsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 3;
    }
}
