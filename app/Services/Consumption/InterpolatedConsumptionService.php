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
            $this->monthsOf($rangeStart, $rangeEnd),
            fn (CarbonInterface $periodStart): CarbonInterface => $periodStart->copy()->addMonthNoOverflow(),
            precision: 1,
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
            precision: 2,
        );
    }

    /**
     * @param  Collection<int, CarbonInterface>  $periods
     * @param  Closure(CarbonInterface): CarbonInterface  $nextPeriodStart
     */
    private function getPeriodConsumption(string $periodKey, Collection $periods, Closure $nextPeriodStart, int $precision): Collection
    {
        if ($periods->isEmpty()) {
            return new Collection;
        }

        $intervals = $this->intervals->between($periods->first(), $nextPeriodStart($periods->last()));
        $lastCompletedDay = ConsumptionInterval::dayCompletedAt(today());

        return $periods->map(function (CarbonInterface $periodStart) use ($periodKey, $intervals, $nextPeriodStart, $lastCompletedDay, $precision): array {
            $afterDay = ConsumptionInterval::dayCompletedAt($periodStart);
            $throughDay = ConsumptionInterval::dayCompletedAt($nextPeriodStart($periodStart));
            $consumption = $this->consumptionWithin($intervals, $afterDay, $throughDay);

            // Only the elapsed days of a period need to be covered for it to count as measured.
            $elapsedThroughDay = min($throughDay, $lastCompletedDay);
            $isFullyCovered = $this->coveredDays($intervals, $afterDay, $elapsedThroughDay) >= max(0, $elapsedThroughDay - $afterDay);
            $hasReading = $intervals->contains(
                fn (ConsumptionInterval $interval): bool => $interval->hasReadingWithin($afterDay, $throughDay)
            );

            return [
                $periodKey => $periodStart,
                'consumption' => $consumption === null ? null : round($consumption, $precision),
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
        $afterDay = ConsumptionInterval::dayCompletedAt($startDate);
        $throughDay = ConsumptionInterval::dayCompletedAt($endDate);
        $coveredDays = $this->coveredDays($intervals, $afterDay, $throughDay);

        if ($coveredDays < 1) {
            return null;
        }

        return $this->consumptionWithin($intervals, $afterDay, $throughDay) / $coveredDays;
    }

    /**
     * Calculates the average monthly usage over the months covered by readings,
     * counting partially covered months by the share of their days covered.
     * Returns null if no part of the range is covered.
     */
    public function getAverageMonthlyConsumption(CarbonInterface $startDate, CarbonInterface $endDate): ?float
    {
        $intervals = $this->intervals->between($startDate, $endDate);
        $afterDay = ConsumptionInterval::dayCompletedAt($startDate);
        $throughDay = ConsumptionInterval::dayCompletedAt($endDate);

        $coveredMonths = $this->monthsOf($startDate, $endDate)->sum(function (CarbonInterface $monthStart) use ($intervals, $afterDay, $throughDay): float {
            $coveredDays = $this->coveredDays(
                $intervals,
                max(ConsumptionInterval::dayCompletedAt($monthStart), $afterDay),
                min(ConsumptionInterval::dayCompletedAt($monthStart->copy()->addMonthNoOverflow()), $throughDay),
            );

            return $coveredDays / $monthStart->daysInMonth;
        });

        if ($coveredMonths <= 0) {
            return null;
        }

        return $this->consumptionWithin($intervals, $afterDay, $throughDay) / $coveredMonths;
    }

    /**
     * Calculates total consumption between two dates. Returns null if
     * no part of the range is covered by readings.
     */
    public function getTotalConsumption(CarbonInterface $start, CarbonInterface $end): ?float
    {
        return $this->consumptionWithin(
            $this->intervals->between($start, $end),
            ConsumptionInterval::dayCompletedAt($start),
            ConsumptionInterval::dayCompletedAt($end),
        );
    }

    /**
     * @param  Collection<int, ConsumptionInterval>  $intervals
     */
    private function consumptionWithin(Collection $intervals, int $afterDay, int $throughDay): ?float
    {
        if ($this->coveredDays($intervals, $afterDay, $throughDay) === 0) {
            return null;
        }

        return $intervals->sum(fn (ConsumptionInterval $interval): float => $interval->amountWithin($afterDay, $throughDay));
    }

    /**
     * @param  Collection<int, ConsumptionInterval>  $intervals
     */
    private function coveredDays(Collection $intervals, int $afterDay, int $throughDay): int
    {
        return $intervals->sum(fn (ConsumptionInterval $interval): int => $interval->overlapDays($afterDay, $throughDay));
    }

    /**
     * @return Collection<int, CarbonInterface>
     */
    private function monthsOf(CarbonInterface $start, CarbonInterface $end): Collection
    {
        return collect($start->copy()->startOfMonth()->monthsUntil($end))->values();
    }
}
