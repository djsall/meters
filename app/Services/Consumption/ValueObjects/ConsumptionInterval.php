<?php

namespace App\Services\Consumption\ValueObjects;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Consumption measured on a single meter between the readings of two days,
 * assumed to be spread evenly over the days in between.
 *
 * Resolution is whole calendar days: a reading counts as the meter's value at
 * the start of its day, regardless of the time it was taken or DST changes.
 */
readonly class ConsumptionInterval
{
    public function __construct(
        public int $fromDay,
        public int $toDay,
        public float $amount,
    ) {}

    /**
     * Sequential number of the calendar day the date falls on. Counted on
     * UTC calendar dates, so every day is exactly one day long, DST included.
     */
    public static function dayOf(CarbonInterface $date): int
    {
        $epoch = CarbonImmutable::createMidnightDate(1970, 1, 1, 'UTC');

        return (int) $epoch->diffInDays(CarbonImmutable::createMidnightDate($date->year, $date->month, $date->day, 'UTC'));
    }

    public function days(): int
    {
        return $this->toDay - $this->fromDay;
    }

    /**
     * Days of this interval that fall within [$startDay, $endDay).
     */
    public function overlapDays(int $startDay, int $endDay): int
    {
        return max(0, min($this->toDay, $endDay) - max($this->fromDay, $startDay));
    }

    /**
     * The share of this interval's consumption that falls within [$startDay, $endDay).
     */
    public function amountWithin(int $startDay, int $endDay): float
    {
        return $this->amount * $this->overlapDays($startDay, $endDay) / $this->days();
    }

    public function hasEndpointWithin(int $startDay, int $endDay): bool
    {
        return ($this->fromDay >= $startDay && $this->fromDay < $endDay)
            || ($this->toDay >= $startDay && $this->toDay < $endDay);
    }
}
