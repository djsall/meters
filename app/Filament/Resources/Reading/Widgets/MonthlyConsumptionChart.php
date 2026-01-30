<?php

namespace App\Filament\Resources\Reading\Widgets;

use App\Services\InterpolatedConsumptionService;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class MonthlyConsumptionChart extends ChartWidget
{
    public ?string $filter = 'current_year';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '200px';

    protected int|string|array $columnSpan = 2;

    public function getHeading(): string|Htmlable|null
    {
        return __('charts.monthly_consumption.heading');
    }

    protected function getFilters(): ?array
    {
        return [
            'current_year' => __('reading.filter.current_year'),
            'previous_year' => __('reading.filter.previous_year'),
        ];
    }

    protected function getData(): array
    {
        $service = new InterpolatedConsumptionService(Filament::getTenant());

        [$start, $end] = match ($this->filter) {
            'current_year' => [today()->startOfYear(), today()->endOfYear()],
            'previous_year' => [today()->subYear()->startOfYear(), today()->subYear()->endOfYear()],
        };

        $results = $service->getMonthlyConsumption($start, $end);

        return [
            'datasets' => [[
                'label' => __('charts.monthly_consumption.label'),
                'data' => $results->pluck('consumption')->toArray(),
                'backgroundColor' => $results->map(fn ($item) => $item['is_estimated'] ? 'transparent' : 'primary'
                )->toArray(),
                'borderRadius' => 4,
            ]],
            'labels' => $results->map(fn ($item) => $item['month']->translatedFormat('Y. M')
            )->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
