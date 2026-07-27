<?php

namespace App\Filament\Resources\Reading\Widgets;

use App\Models\Meter;
use App\Services\InterpolatedConsumptionService;
use Filament\Support\Colors\Color;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;

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
        $meter = Meter::getFilamentTenant();

        $service = new InterpolatedConsumptionService($meter);

        [$start, $end] = match ($this->filter) {
            'previous_year' => [today()->subYear()->startOfYear(), today()->subYear()->endOfYear()],
            default => [today()->startOfYear(), today()->endOfYear()],
        };

        $results = Cache::remember(
            "{$this->filter}_monthly_consumption_{$meter->id}",
            60,
            fn () => $service->getMonthlyConsumption($start, $end)
        );

        return [
            'datasets' => [[
                'label' => __('charts.monthly_consumption.label'),
                'data' => $results->pluck('consumption')->toArray(),
                'backgroundColor' => $results->map(
                    fn ($item) => Color::convertToHex($item['is_estimated'] ? Color::Purple[200] : Color::Purple[400])
                )->toArray(),
                'borderRadius' => 8,
                'borderColor' => 'none',
            ]],
            'labels' => $results->map(
                fn ($item) => $item['month']->translatedFormat('Y. M')
            )->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
