<?php

namespace App\Services;

use App\Enums\ReadingBoundary;
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

        $effectiveStart = $this->determineEffectiveBoundary($readings, $startDate, ReadingBoundary::Start);
        $effectiveEnd = $this->determineEffectiveBoundary($readings, $endDate, ReadingBoundary::End);

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
     * Calculates total consumption between two dates.
     */
    public function getTotalConsumption(CarbonInterface $start, CarbonInterface $end): ?float
    {
        $readings = $this->getRelevantReadings($start, $end);

        if ($readings->isEmpty()) {
            return null;
        }

        $startValue = $this->interpolate($readings, $start);
        $endValue = $this->interpolate($readings, $end);

        if ($startValue === null || $endValue === null) {
            return null;
        }

        return $endValue - $startValue;
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
        if ($readingBefore == null) {
            return null;
        }

        // If no "after" reading exists, we cannot project forward, so return the last known value
        if ($readingAfter == null) {
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

    private function determineEffectiveBoundary(Collection $readings, CarbonInterface $requestedDate, ReadingBoundary $type): CarbonInterface
    {
        /**
         * @var Reading $closestReading
         * @var Reading $fallback
         */
        $closestReading = match ($type) {
            ReadingBoundary::Start => $readings->first(),
            ReadingBoundary::End => $readings->last(),
        };

        $isOutsideRange = match ($type) {
            ReadingBoundary::Start => $closestReading->date->lt($requestedDate),
            ReadingBoundary::End => $closestReading->date->gt($requestedDate),
        };

        if ($isOutsideRange && $closestReading->date->diffInDays($requestedDate) > $this->maxGapDays) {
            $fallback = match ($type) {
                ReadingBoundary::Start => $readings->first(fn (Reading $reading): bool => $reading->date->gte($requestedDate)),
                ReadingBoundary::End => $readings->last(fn (Reading $reading): bool => $reading->date->lte($requestedDate)),
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
