<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Reading;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

readonly class InterpolatedConsumptionService
{
    private int $maxGapDays;

    public function __construct(private Meter $meter)
    {
        $this->maxGapDays = 31;
    }

    /**
     * Calculates monthly consumption. Returns null for consumption
     * if interpolation is not possible.
     */
    public function getMonthlyConsumption(CarbonInterface $rangeStart, CarbonInterface $rangeEnd): Collection
    {
        $readings = $this->getRelevantReadings($rangeStart, $rangeEnd);
        $months = collect($rangeStart->copy()->startOfMonth()->monthsUntil($rangeEnd))->values();

        $results = collect();
        $previousValue = $this->interpolate($readings, $rangeStart);

        foreach ($months as $monthStart) {
            $monthEnd = $monthStart->copy()->endOfMonth();
            $currentValue = $this->interpolate($readings, $monthEnd);

            $consumption = null;
            if ($previousValue !== null && $currentValue !== null) {
                $consumption = round($currentValue - $previousValue);
            }

            $results->push([
                'month' => $monthStart,
                'consumption' => $consumption,
                'is_estimated' => ! $this->hasReadingInMonth($readings, $monthStart),
            ]);

            $previousValue = $currentValue;
        }

        return $results;
    }

    /**
     * Calculates daily consumption. Returns null for consumption
     * if interpolation is not possible.
     */
    public function getDailyConsumption(CarbonInterface $rangeStart, CarbonInterface $rangeEnd): Collection
    {
        $readings = $this->getRelevantReadings($rangeStart, $rangeEnd);
        $days = collect($rangeStart->copy()->startOfMonth()->daysUntil($rangeEnd))->values();

        $results = collect();
        $previousValue = $this->interpolate($readings, $rangeStart);

        foreach ($days as $dayStart) {
            $dayEnd = $dayStart->copy()->endOfDay();
            $currentValue = $this->interpolate($readings, $dayEnd);

            $consumption = null;
            if ($previousValue !== null && $currentValue !== null) {
                $consumption = round($currentValue - $previousValue);
            }

            $results->push([
                'day' => $dayStart,
                'consumption' => $consumption,
                'is_estimated' => ! $this->hasReadingInDay($readings, $dayStart),
            ]);

            $previousValue = $currentValue;
        }

        return $results;
    }

    /**
     * Calculates the average daily usage. Returns null if no readings are available.
     */
    public function getAverageDailyConsumption(CarbonInterface $startDate, CarbonInterface $endDate): ?float
    {
        $readings = $this->getRelevantReadings($startDate, $endDate);

        if ($readings->isEmpty()) {
            return null;
        }

        $effectiveStart = $this->determineEffectiveBoundary($readings, $startDate, 'start');
        $effectiveEnd = $this->determineEffectiveBoundary($readings, $endDate, 'end');

        $startValue = $this->interpolate($readings, $effectiveStart);
        $endValue = $this->interpolate($readings, $effectiveEnd);

        if ($startValue === null || $endValue === null) {
            return null;
        }

        $totalConsumption = $endValue - $startValue;
        $totalDays = $effectiveStart->diffInDays($effectiveEnd);

        if ($totalDays < 1) {
            return null;
        }

        return $totalConsumption / $totalDays;
    }

    /**
     * Performs linear interpolation between two data points.
     */
    public function interpolate(Collection $readings, CarbonInterface $targetDate): ?float
    {
        /**
         * @var Reading $readingBefore
         * @var Reading $readingAfter
         */
        $readingBefore = $readings->last(fn (Reading $reading): bool => $reading->date <= $targetDate);
        $readingAfter = $readings->first(fn (Reading $reading): bool => $reading->date > $targetDate);

        // Cannot interpolate if there is no reading before the target date
        if (! $readingBefore) {
            return null;
        }

        // If no "after" reading exists, we cannot project forward, so return the last known value
        if (! $readingAfter) {
            return $readingBefore->value;
        }

        $daysBetweenReadings = $readingBefore->date->diffInDays($readingAfter->date);

        // Avoid division by zero if two readings have the exact same timestamp
        if ($daysBetweenReadings == 0) {
            return $readingBefore->value;
        }

        $daysFromBeforeToTarget = $readingBefore->date->diffInDays($targetDate);
        $dailySlope = ($readingAfter->value - $readingBefore->value) / $daysBetweenReadings;

        return $readingBefore->value + ($daysFromBeforeToTarget * $dailySlope);
    }

    private function determineEffectiveBoundary(Collection $readings, CarbonInterface $requestedDate, string $type): CarbonInterface
    {
        /**
         * @var Reading $closestReading
         * @var Reading $fallback
         */
        $closestReading = match ($type) {
            'start' => $readings->first(),
            'end' => $readings->last(),
        };

        $isOutsideRange = match ($type) {
            'start' => $closestReading->date->lt($requestedDate),
            'end' => $closestReading->date->gt($requestedDate),
        };

        if ($isOutsideRange && $closestReading->date->diffInDays($requestedDate) > $this->maxGapDays) {
            $fallback = match ($type) {
                'start' => $readings->first(fn (Reading $reading): bool => $reading->date->gte($requestedDate)),
                'end' => $readings->last(fn (Reading $reading): bool => $reading->date->lte($requestedDate)),
            };

            return $fallback ? $fallback->date : $requestedDate;
        }

        return $closestReading->date;
    }

    private function hasReadingInMonth(Collection $readings, CarbonInterface $month): bool
    {
        return $readings->contains(fn (Reading $reading) => $reading->date->isSameMonth($month));
    }

    private function hasReadingInDay(Collection $readings, CarbonInterface $day): bool
    {
        return $readings->contains(fn (Reading $reading) => $reading->date->isSameDay($day));
    }

    private function getRelevantReadings(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $readingBefore = $this->meter->readings()
            ->where('date', '<', $start)
            ->latest('date')
            ->limit(1)
            ->first();

        $readingsInside = $this->meter->readings()
            ->whereBetween('date', [$start, $end])
            ->oldest('date')
            ->get();

        $readingAfter = $this->meter->readings()
            ->where('date', '>', $end)
            ->oldest('date')
            ->limit(1)
            ->first();

        return collect([$readingBefore, ...$readingsInside, $readingAfter])->filter()->values();
    }
}
