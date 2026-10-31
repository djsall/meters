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
        $today = ConsumptionInterval::dayOf(today());

        return $periods->map(function (CarbonInterface $periodStart) use ($periodKey, $intervals, $nextPeriodStart, $today, $precision): array {
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
        $startDay = ConsumptionInterval::dayOf($startDate);
        $endDay = ConsumptionInterval::dayOf($endDate);
        $coveredDays = $this->coveredDays($intervals, $startDay, $endDay);

        if ($coveredDays < 1) {
            return null;
        }

        return $this->consumptionWithin($intervals, $startDay, $endDay) / $coveredDays;
    }

    /**
     * Calculates the average monthly usage over the months covered by readings,
     * counting partially covered months by the share of their days covered.
     * Returns null if no part of the range is covered.
     */
    public function getAverageMonthlyConsumption(CarbonInterface $startDate, CarbonInterface $endDate): ?float
    {
        $intervals = $this->intervals->between($startDate, $endDate);
        $startDay = ConsumptionInterval::dayOf($startDate);
        $endDay = ConsumptionInterval::dayOf($endDate);

        $coveredMonths = $this->monthsOf($startDate, $endDate)->sum(function (CarbonInterface $monthStart) use ($intervals, $startDay, $endDay): float {
            $coveredDays = $this->coveredDays(
                $intervals,
                max(ConsumptionInterval::dayOf($monthStart), $startDay),
                min(ConsumptionInterval::dayOf($monthStart->copy()->addMonthNoOverflow()), $endDay),
            );

            return $coveredDays / $monthStart->daysInMonth;
        });

        if ($coveredMonths <= 0) {
            return null;
        }

        return $this->consumptionWithin($intervals, $startDay, $endDay) / $coveredMonths;
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

    /**
     * @return Collection<int, CarbonInterface>
     */
    private function monthsOf(CarbonInterface $start, CarbonInterface $end): Collection
    {
        return collect($start->copy()->startOfMonth()->monthsUntil($end))->values();
    }
}
