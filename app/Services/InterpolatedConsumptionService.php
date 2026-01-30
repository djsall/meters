<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Reading;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

readonly class InterpolatedConsumptionService
{
    public function __construct(private Meter $meter) {}

    public function getMonthlyConsumption(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $readings = $this->getRelevantReadings($start, $end);
        $months = collect($start->copy()->startOfMonth()->monthsUntil($end))->values();

        // Initial value at the very start of the range
        $previousValue = $this->interpolate($readings, $start);

        return $months->map(function (CarbonInterface $month) use ($readings, &$previousValue) {
            $targetDate = $month->copy()->endOfMonth();
            $currentValue = $this->interpolate($readings, $targetDate);

            $consumption = max(0, $currentValue - $previousValue);
            $isEstimated = ! $readings->contains(fn (Reading $r) => $r->date->isSameMonth($month));

            $previousValue = $currentValue;

            return [
                'month' => $month,
                'consumption' => round($consumption),
                'is_estimated' => $isEstimated,
            ];
        });
    }

    public function getAverageDailyConsumption(CarbonInterface $start, CarbonInterface $end): ?float
    {
        // 1. Get the readings surrounding the entire range to ensure accurate interpolation
        $readings = $this->getRelevantReadings($start, $end);

        // 2. Interpolate the exact meter values at the start and end timestamps
        $startValue = $this->interpolate($readings, $start);
        $endValue = $this->interpolate($readings, $end);

        if ($startValue === null || $endValue === null) {
            return null;
        }

        // 3. Calculate the delta
        $totalConsumption = max(0, $endValue - $startValue);

        // 4. Calculate days (ensure at least 1 to avoid division by zero)
        $days = max(1, $start->diffInDays($end));

        return $totalConsumption / $days;
    }

    public function interpolate(Collection $readings, CarbonInterface $target): ?float
    {
        /**
         * @var ?Reading $before
         * @var ?Reading $after
         */
        $before = $readings->last(fn (Reading $reading): bool => $reading->date <= $target);
        $after = $readings->first(fn (Reading $reading): bool => $reading->date > $target);

        if (! $before) {
            return null;
        }

        if (! $after) {
            return $before->value;
        }

        $daysBetween = $before->date->diffInDays($after->date);
        $daysToTarget = $before->date->diffInDays($target);

        $slope = ($after->value - $before->value) / $daysBetween;

        return $before->value + ($daysToTarget * $slope);
    }

    private function getRelevantReadings(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $before = $this->meter->readings()->where('date', '<', $start)->latest('date')->first();
        $inside = $this->meter->readings()->whereBetween('date', [$start, $end])->oldest('date')->get();
        $after = $this->meter->readings()->where('date', '>', $end)->oldest('date')->first();

        return collect([$before, ...$inside, $after])->filter()->values();
    }
}
