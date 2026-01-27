<?php

namespace App\Filament\Resources\Reading\Widgets;

use App\Models\Meter;
use App\Models\Reading;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

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

    protected Meter $meter {
        get {
            return Filament::getTenant();
        }
    }

    protected array $dateRange {
        get {
            return match ($this->filter) {
                'current_year' => [today()->startOfYear(), today()->endOfYear()],
                'previous_year' => [today()->subYear()->startOfYear(), today()->subYear()->endOfYear()],
            };
        }
    }

    protected function getData(): array
    {
        [$start, $end] = $this->dateRange;

        $readings = $this->getRelevantReadings($start, $end);
        $months = $this->getMonthlyPeriods($start, $end);

        // Initial value at the very start of the year range
        $previousValue = $this->interpolate($readings, $start);

        $results = $months->map(function (CarbonInterface $month) use ($readings, &$previousValue) {
            $targetDate = $month->copy()->endOfMonth();

            // 1. Calculate current interpolated meter reading
            $currentValue = $this->interpolate($readings, $targetDate);

            // 2. Consumption is the difference from the last point
            $consumption = max(0, $currentValue - $previousValue);

            // 3. Determine if this month is "Real" (has a reading within its boundaries)
            $isEstimated = ! $readings->contains(fn (Reading $reading): bool => $reading->date->isSameMonth($month));

            // Update the pointer for the next month's calculation
            $previousValue = $currentValue;

            return [
                'label' => $month->translatedFormat('Y. M'),
                'value' => round($consumption),
                'color' => $isEstimated ? 'transparent' : 'primary',
            ];
        });

        return [
            'datasets' => [[
                'label' => __('charts.monthly_consumption.label'),
                'data' => $results->pluck('value')->toArray(),
                'backgroundColor' => $results->pluck('color')->toArray(),
                'borderRadius' => 4,
            ]],
            'labels' => $results->pluck('label')->toArray(),
        ];
    }

    /**
     * Perform linear interpolation: y = y1 + ((x - x1) * (y2 - y1) / (x2 - x1))
     */
    private function interpolate(Collection $readings, CarbonInterface $target): float
    {
        /**
         * @var ?Reading $before
         * @var ?Reading $after
         */
        $before = $readings->last(fn (Reading $reading): bool => $reading->date <= $target);
        $after = $readings->first(fn (Reading $reading): bool => $reading->date > $target);

        if (! $before) {
            return 0;
        }
        if (! $after) {
            return $before->value;
        }

        $daysBetweenReadings = $before->date->diffInDays($after->date);
        $daysToTarget = $before->date->diffInDays($target);

        $slope = ($after->value - $before->value) / max(1, $daysBetweenReadings);

        return $before->value + ($daysToTarget * $slope);
    }

    private function getRelevantReadings(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $before = $this->meter->readings()
            ->where('date', '<', $start)
            ->latest('date')
            ->limit(1)
            ->first();

        $inside = $this->meter->readings()
            ->whereBetween('date', [$start, $end])
            ->oldest('date')
            ->get();

        $after = $this->meter->readings()
            ->where('date', '>', $end)
            ->oldest('date')
            ->limit(1)
            ->first();

        return collect([$before, ...$inside, $after])->filter()->values();
    }

    private function getMonthlyPeriods(CarbonInterface $start, CarbonInterface $end): Collection
    {
        return collect($start->copy()->startOfMonth()->monthsUntil($end))->values();
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
