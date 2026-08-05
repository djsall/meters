<?php

namespace App\Services;

use App\Enums\ReadingBoundary;
use App\Models\Meter;
use App\Models\Reading;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Collection;

readonly class InterpolatedConsumptionService
{
    private int $maxGapDays;

    private MeterReadingSeries $readings;

    public function __construct(private Meter $meter)
    {
        $this->maxGapDays = 31;
        $this->readings = new MeterReadingSeries($meter);
    }

    /**
     * Calculates monthly consumption. Returns null for consumption
     * if interpolation is not possible.
     */
    public function getMonthlyConsumption(CarbonInterface $rangeStart, CarbonInterface $rangeEnd): Collection
    {
        return $this->getPeriodConsumption(
            $rangeStart,
            $rangeEnd,
            'month',
            collect($rangeStart->copy()->startOfMonth()->monthsUntil($rangeEnd))->values(),
            fn (CarbonInterface $periodStart): CarbonInterface => $periodStart->copy()->endOfMonth(),
            fn (CarbonInterface $date, CarbonInterface $periodStart): bool => $date->isSameMonth($periodStart),
        );
    }

    /**
     * Calculates daily consumption. Returns null for consumption
     * if interpolation is not possible.
     */
    public function getDailyConsumption(CarbonInterface $rangeStart, CarbonInterface $rangeEnd): Collection
    {
        return $this->getPeriodConsumption(
            $rangeStart,
            $rangeEnd,
            'day',
            collect($rangeStart->copy()->startOfMonth()->daysUntil($rangeEnd))->values(),
            fn (CarbonInterface $periodStart): CarbonInterface => $periodStart->copy()->endofDay(),
            fn (CarbonInterface $date, CarbonInterface $periodStart): bool => $date->isSameDay($periodStart),
        );
    }

    private function getPeriodConsumption(
        CarbonInterface $rangeStart,
        CarbonInterface $rangeEnd,
        string $periodKey,
        Collection $periods,
        Closure $periodEnd,
        Closure $isSamePeriod
    ): Collection {
        $readings = $this->readings->between($rangeStart, $rangeEnd);
        $results = new Collection;

        $previousValue = $this->interpolate($readings, $rangeStart);

        foreach ($periods as $periodStart) {
            $currentValue = $this->interpolate($readings, $periodEnd($periodStart));

            $consumption = null;
            if ($previousValue !== null && $currentValue !== null) {
                $consumption = round($currentValue - $previousValue);
            }

            $results->push([
                $periodKey => $periodStart,
                'consumption' => $consumption,
                'is_estimated' => ! $readings->contains(fn (Reading $reading): bool => $isSamePeriod($reading->date, $periodStart)),
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
        $readings = $this->readings->between($startDate, $endDate);

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
        $readings = $this->readings->between($start, $end);

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
}
