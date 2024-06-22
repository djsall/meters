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
    public function getHeading(): string|Htmlable|null
    {
        return trans('charts.monthly_consumption.heading');
    }

    protected static ?string $maxHeight = '200px';

    protected int|string|array $columnSpan = 2;

    protected function getData(): array
    {
        $first = Reading::firstOfYear()?->value;

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
                    start: now()->startOfYear(),
                    end: now()->endOfYear(),
                )
                ->perMonth()
                ->average('value');

        return [
            'datasets' => [
                [
                    'label' => trans('charts.monthly_consumption.heading'),
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
