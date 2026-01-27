<?php

namespace App\Filament\Resources\Reading\Widgets;

use App\Models\Meter;
use App\Models\Reading;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

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
        [$startOfYear, $endOfYear] = $this->dateRange;

        // 1. Fetch Boundary Readings (Anchors)
        $readingBeforeRange = $this->meter->readings()
            ->where('date', '<', $startOfYear)
            ->latest('date')
            ->limit(1)
            ->first();

        $readingAfterRange = $this->meter->readings()
            ->where('date', '>', $endOfYear)
            ->oldest('date')
            ->limit(1)
            ->first();

        // 2. Fetch all readings within the year
        $yearReadings = $this->meter->readings()
            ->whereBetween('date', [$startOfYear, $endOfYear])
            ->orderBy('date', 'asc')
            ->get();

        // Merge everything into one collection for easy searching
        $allRelevantReadings = collect([$readingBeforeRange, ...$yearReadings, $readingAfterRange])
            ->filter()
            ->values();

        // 3. Define our monthly timeline
        $monthPeriods = Trend::query($this->meter->readings()->getQuery())
            ->between($startOfYear, $endOfYear)
            ->dateColumn('date')
            ->perMonth()
            ->count('value')
            ->map(fn (TrendValue $trendValue) => Carbon::parse($trendValue->date));

        $monthlyValues = [];
        $isEstimated = [];

        foreach ($monthPeriods as $month) {
            $targetDate = $month->endOfMonth();

            // Check if a real reading exists exactly in this month
            $hasRealReading = $yearReadings->contains(fn (Reading $reading): bool => $reading->date->isSameMonth($month));

            $beforeReading = $allRelevantReadings->last(fn (Reading $reading): bool => $reading->date <= $targetDate);
            $afterReading = $allRelevantReadings->first(fn (Reading $reading): bool => $reading->date > $targetDate);

            if ($beforeReading && $afterReading) {
                $totalDaysBetween = $beforeReading->date->diffInDays($afterReading->date);
                $daysSinceBefore = $beforeReading->date->diffInDays($targetDate);
                $totalValueChange = $afterReading->value - $beforeReading->value;

                $slope = $totalValueChange / max(1, $totalDaysBetween);
                $interpolatedValue = $beforeReading->value + ($daysSinceBefore * $slope);

                $monthlyValues[$month->format('M')] = $interpolatedValue;
                $isEstimated[] = ! $hasRealReading;
            } else {
                $monthlyValues[$month->format('M')] = $beforeReading?->value ?? 0;
                $isEstimated[] = true;
            }
        }

        // 4. Calculate Final Consumption (Deltas) and Colors
        $finalConsumptionData = [];
        $backgroundColors = [];
        $monthKeys = array_keys($monthlyValues);

        foreach ($monthKeys as $index => $monthName) {
            $currentValue = $monthlyValues[$monthName];
            $previousValue = ($index === 0)
                ? ($readingBeforeRange?->value ?? 0)
                : $monthlyValues[$monthKeys[$index - 1]];

            $finalConsumptionData[] = max(0, $currentValue - $previousValue);

            $backgroundColors[] = $isEstimated[$index] ? 'transparent' : 'primary';
        }

        return [
            'datasets' => [
                [
                    'label' => __('charts.monthly_consumption.label'),
                    'data' => $finalConsumptionData,
                    'backgroundColor' => $backgroundColors,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $monthPeriods->map(fn ($month) => $month->format('Y. M')),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
