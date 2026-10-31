<?php

namespace App\Services\Consumption;

use App\Models\Meter;
use App\Services\Consumption\ValueObjects\ConsumptionInterval;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Collection;

readonly class InterpolatedConsumptionService
{
    private ConsumptionIntervals $intervals;

    public function __construct(Meter $meter)
    {
        $this->intervals = new ConsumptionIntervals($meter);
    }

    /**
     * Calculates monthly consumption. Consumption is null for months
     * without any reading coverage.
     */
    public function getMonthlyConsumption(CarbonInterface $rangeStart, CarbonInterface $rangeEnd): Collection
    {
        return $this->getPeriodConsumption(
            'month',
            collect($rangeStart->copy()->startOfMonth()->monthsUntil($rangeEnd))->values(),
            fn (CarbonInterface $periodStart): CarbonInterface => $periodStart->copy()->addMonthNoOverflow(),
        );
    }

    /**
     * Calculates daily consumption. Consumption is null for days
     * without any reading coverage.
     */
    public function getDailyConsumption(CarbonInterface $rangeStart, CarbonInterface $rangeEnd): Collection
    {
        return $this->getPeriodConsumption(
            'day',
            collect($rangeStart->copy()->startOfMonth()->daysUntil($rangeEnd))->values(),
            fn (CarbonInterface $periodStart): CarbonInterface => $periodStart->copy()->addDay(),
        );
    }

    /**
     * @param  Collection<int, CarbonInterface>  $periods
     * @param  Closure(CarbonInterface): CarbonInterface  $nextPeriodStart
     */
    private function getPeriodConsumption(string $periodKey, Collection $periods, Closure $nextPeriodStart): Collection
    {
        if ($periods->isEmpty()) {
            return new Collection;
        }

        $intervals = $this->intervals->between($periods->first(), $nextPeriodStart($periods->last()));
        $today = ConsumptionInterval::dayOf(today());

        return $periods->map(function (CarbonInterface $periodStart) use ($periodKey, $intervals, $nextPeriodStart, $today): array {
            $startDay = ConsumptionInterval::dayOf($periodStart);
            $endDay = ConsumptionInterval::dayOf($nextPeriodStart($periodStart));
            $consumption = $this->consumptionWithin($intervals, $startDay, $endDay);

            // Only the elapsed days of a period need to be covered for it to count as measured.
            $elapsedEndDay = min($endDay, $today);
            $isFullyCovered = $this->coveredDays($intervals, $startDay, $elapsedEndDay) >= max(0, $elapsedEndDay - $startDay);
            $hasReading = $intervals->contains(
                fn (ConsumptionInterval $interval): bool => $interval->hasEndpointWithin($startDay, $endDay)
            );

            return [
                $periodKey => $periodStart,
                'consumption' => $consumption === null ? null : round($consumption),
                'is_estimated' => ! $hasReading || ! $isFullyCovered,
            ];
        })->values();
    }

    /**
     * Calculates the average daily usage over the part of the range covered
     * by readings. Returns null if less than a day is covered.
     */
    public function getAverageDailyConsumption(CarbonInterface $startDate, CarbonInterface $endDate): ?float
    {
        $intervals = $this->intervals->between($startDate, $endDate);
        $startDay = ConsumptionInterval::dayOf($startDate);
        $endDay = ConsumptionInterval::dayOf($endDate);
        $coveredDays = $this->coveredDays($intervals, $startDay, $endDay);

        if ($coveredDays < 1) {
            return null;
        }

        return $this->consumptionWithin($intervals, $startDay, $endDay) / $coveredDays;
    }

    /**
     * Calculates total consumption between two dates. Returns null if
     * no part of the range is covered by readings.
     */
    public function getTotalConsumption(CarbonInterface $start, CarbonInterface $end): ?float
    {
        return $this->consumptionWithin(
            $this->intervals->between($start, $end),
            ConsumptionInterval::dayOf($start),
            ConsumptionInterval::dayOf($end),
        );
    }

    /**
     * @param  Collection<int, ConsumptionInterval>  $intervals
     */
    private function consumptionWithin(Collection $intervals, int $startDay, int $endDay): ?float
    {
        if ($this->coveredDays($intervals, $startDay, $endDay) === 0) {
            return null;
        }

        return $intervals->sum(fn (ConsumptionInterval $interval): float => $interval->amountWithin($startDay, $endDay));
    }

    /**
     * @param  Collection<int, ConsumptionInterval>  $intervals
     */
    private function coveredDays(Collection $intervals, int $startDay, int $endDay): int
    {
        return $intervals->sum(fn (ConsumptionInterval $interval): int => $interval->overlapDays($startDay, $endDay));
    }
}
