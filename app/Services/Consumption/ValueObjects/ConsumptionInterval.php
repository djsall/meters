<?php

namespace App\Services\Consumption\ValueObjects;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Consumption measured on a single meter between the readings of two days,
 * assumed to be spread evenly over the days in between.
 *
 * Resolution is whole calendar days: a reading counts as the meter's value at
 * the end of its day, whatever time it was taken. The interval therefore covers
 * the days after the first reading's day, through the second reading's day.
 * Day ranges passed to this class work the same way: after one day, through another.
 */
readonly class ConsumptionInterval
{
    public function __construct(
        public int $fromReadingDay,
        public int $toReadingDay,
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

    /**
     * The last day fully over at the given midnight: October starts when
     * Sep 30 is over and ends when Oct 31 is.
     */
    public static function dayCompletedAt(CarbonInterface $midnight): int
    {
        return self::dayOf($midnight->copy()->subDay());
    }

    public function days(): int
    {
        return $this->toReadingDay - $this->fromReadingDay;
    }

    /**
     * Days of this interval that fall after $afterDay, through $throughDay.
     */
    public function overlapDays(int $afterDay, int $throughDay): int
    {
        return max(0, min($this->toReadingDay, $throughDay) - max($this->fromReadingDay, $afterDay));
    }

    /**
     * The share of this interval's consumption that falls after $afterDay, through $throughDay.
     */
    public function amountWithin(int $afterDay, int $throughDay): float
    {
        return $this->amount * $this->overlapDays($afterDay, $throughDay) / $this->days();
    }

    /**
     * Whether either reading bounding this interval was taken after $afterDay, through $throughDay.
     */
    public function hasReadingWithin(int $afterDay, int $throughDay): bool
    {
        return ($this->fromReadingDay > $afterDay && $this->fromReadingDay <= $throughDay)
            || ($this->toReadingDay > $afterDay && $this->toReadingDay <= $throughDay);
    }
}
