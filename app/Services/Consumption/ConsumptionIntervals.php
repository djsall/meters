<?php

namespace App\Services\Consumption;

use App\Models\Meter;
use App\Models\Reading;
use App\Services\Consumption\ValueObjects\ConsumptionInterval;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;

/**
 * Builds consumption intervals for a meter and, if it replaced one, its predecessor.
 *
 * Intervals only ever span readings of the same meter, so no values from two
 * different meters are compared and no offsets are needed. Each meter only
 * contributes readings from its own active window: the predecessor up to and
 * including the successor's installation day, the successor from that day on.
 */
readonly class ConsumptionIntervals
{
    public function __construct(private Meter $meter) {}

    /**
     * @return Collection<int, ConsumptionInterval>
     */
    public function between(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $predecessor = $this->meter->previousMeter;
        $installedAt = $this->meter->installed_at;

        if ($predecessor === null || $installedAt === null) {
            return $this->toIntervals($this->readings($this->meter, $start, $end));
        }

        $intervals = $this->toIntervals($this->readings($this->meter, $start, $end, from: $installedAt));

        if ($installedAt->copy()->startOfDay()->addDay()->gt($start)) {
            $intervals = $this->toIntervals($this->readings($predecessor, $start, $end, until: $installedAt))
                ->merge($intervals);
        }

        return $intervals->sortBy(fn (ConsumptionInterval $interval): int => $interval->fromReadingDay)->values();
    }

    /**
     * Readings within [$start, $end], plus the nearest one on either side so
     * the range edges can be interpolated, limited to the meter's window.
     *
     * @return Collection<int, Reading>
     */
    private function readings(
        Meter $meter,
        CarbonInterface $start,
        CarbonInterface $end,
        ?CarbonInterface $from = null,
        ?CarbonInterface $until = null,
    ): Collection {
        $query = fn () => $meter->readings()
            ->when($until, fn ($query) => $this->withoutTenancyScope($query))
            ->when($from, fn ($query) => $query->where('date', '>=', $from->copy()->startOfDay()))
            ->when($until, fn ($query) => $query->where('date', '<', $until->copy()->startOfDay()->addDay()));

        $next = $query()->where('date', '>', $end)->oldest('date')->first();

        return collect([
            $query()->where('date', '<', $start)->latest('date')->first(),
            ...$query()->whereBetween('date', [$start, $end])->oldest('date')->get(),
            ...($next === null ? [] : $query()->whereDate('date', $next->date)->oldest('date')->get()),
        ])->filter()->values();
    }

    /**
     * Remove filament tenancy scope that automatically gets applied inside a panel.
     */
    private function withoutTenancyScope($query)
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        return $panel === null ? $query : $query->withoutGlobalScope($panel->getTenancyScopeName());
    }

    /**
     * A reading counts as the value at the end of its day, so the last reading of each day is used.
     *
     * @param  Collection<int, Reading>  $readings  ordered by date
     * @return Collection<int, ConsumptionInterval>
     */
    private function toIntervals(Collection $readings): Collection
    {
        return $readings
            ->keyBy(fn (Reading $reading): int => ConsumptionInterval::dayOf($reading->date))
            ->values()
            ->sliding(2)
            ->map(fn (Collection $pair): ConsumptionInterval => new ConsumptionInterval(
                ConsumptionInterval::dayOf($pair->first()->date),
                ConsumptionInterval::dayOf($pair->last()->date),
                $pair->last()->value - $pair->first()->value,
            ))
            ->values();
    }
}
