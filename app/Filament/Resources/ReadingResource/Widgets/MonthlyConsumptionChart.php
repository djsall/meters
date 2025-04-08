<?php

namespace App\Filament\Resources\ReadingResource\Widgets;

use App\Models\Reading;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class MonthlyConsumptionChart extends ChartWidget
{
    public ?string $filter = 'current_year';

    protected static ?string $pollingInterval = null;

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

    protected static ?string $maxHeight = '200px';

    protected int|string|array $columnSpan = 2;

    protected function getData(): array
    {
        $between = match ($this->filter) {
            'current_year' => ['start' => now()->startOfYear(), 'end' => now()->endOfYear()],
            'previous_year' => ['start' => now()->subYear()->startOfYear(), 'end' => now()->subYear()->endOfYear()],
        };

        $first = Reading::firstOfYear($between['start']->format('Y'))?->value;

        $previous =
            static fn ($date): int => Reading::query()
                ->tenant()
                ->whereBetween('date', [
                    Carbon::parse($date)->subMonth()->startOfMonth(),
                    Carbon::parse($date)->subMonth()->endOfMonth(),
                ])
                ->first()
                ?->value ?? 0;

        $data =
            Trend::query(
                Reading::query()->tenant()
            )
                ->dateColumn('date')
                ->between(
                    start: $between['start'],
                    end: $between['end'],
                )
                ->perMonth()
                ->average('value');

        return [
            'datasets' => [
                [
                    'label' => __('charts.monthly_consumption.label'),
                    'data' => $data
                        ->map(static function (TrendValue $value) use ($first, $previous): ?int {
                            if ($value->aggregate > 0) {
                                $previous = $previous($value->date);

                                if ($previous === 0) {
                                    return $value->aggregate - $first;
                                }

                                return $value->aggregate - $previous;
                            }

                            return null;
                        }),
                ],
            ],
            'labels' => $data->map(static fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
