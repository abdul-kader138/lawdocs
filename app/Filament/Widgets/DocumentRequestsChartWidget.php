<?php

namespace App\Filament\Widgets;

use App\Models\DocumentRequest;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Document requests created per day, stacked by status. Mirrors wma-bot's
 * date-ranged bar-chart pattern (BreaksDownByAccount), but grouped by
 * `status` instead of a WhatsApp account — the closest equivalent
 * "breakdown dimension" this domain has for a stacked bar chart.
 */
class DocumentRequestsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Document Requests';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 2;

    protected static ?string $pollingInterval = '30s';

    public ?string $filter = '14';

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Last 7 days',
            '14' => 'Last 14 days',
            '30' => 'Last 30 days',
        ];
    }

    private const STATUS_COLORS = [
        'pending' => '#9ca3af',
        'processing' => '#f59e0b',
        'completed' => '#22c55e',
        'failed' => '#ef4444',
    ];

    protected function getData(): array
    {
        $days = (int) ($this->filter ?? 14);
        $start = now()->subDays($days - 1)->startOfDay();

        $dateKeys = collect(range(0, $days - 1))
            ->map(fn (int $i) => $start->copy()->addDays($i)->toDateString());

        $rows = DocumentRequest::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as date, status, COUNT(*) as count')
            ->groupBy('date', 'status')
            ->get();

        $datasets = collect(array_keys(self::STATUS_COLORS))->map(function (string $status) use ($rows, $dateKeys) {
            $byDate = $rows->where('status', $status)->pluck('count', 'date');

            return [
                'label' => ucfirst($status),
                'data' => $dateKeys->map(fn (string $date) => (int) ($byDate[$date] ?? 0))->all(),
                'backgroundColor' => self::STATUS_COLORS[$status],
            ];
        })->values()->all();

        return [
            'datasets' => $datasets,
            'labels' => $dateKeys->map(fn (string $date) => Carbon::parse($date)->format('d M'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true],
            ],
        ];
    }
}
