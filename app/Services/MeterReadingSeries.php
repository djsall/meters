<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Reading;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

readonly class MeterReadingSeries
{
    public function __construct(private Meter $meter) {}

    public function between(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $readings = $this->fetchOwnReadings($this->meter, $start, $end);

        if ($this->meter->previousMeter === null) {
            return $readings->values();
        }

        $hasReadingBefore = $readings->contains(fn (Reading $reading): bool => $reading->date->lt($start));
        $readings = $this->rebase($this->meter, $readings);

        if ($hasReadingBefore || $this->meter->installed_at === null) {
            return $readings->sortBy('date')->values();
        }

        $predecessor = $this->meter->previousMeter;
        $predecessorReadings = $this->rebase($predecessor, $this->fetchOwnReadings($predecessor, $start, $this->meter->installed_at));

        return $readings->merge($predecessorReadings)->sortBy('date')->values();
    }

    private function fetchOwnReadings(Meter $meter, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $readingBefore = $meter->readings()
            ->where('date', '<', $start)
            ->latest('date')
            ->limit(1)
            ->first();

        $readingsInside = $meter->readings()
            ->whereBetween('date', [$start, $end])
            ->oldest('date')
            ->get();

        $readingAfter = $meter->readings()
            ->where('date', '>', $end)
            ->oldest('date')
            ->limit(1)
            ->first();

        return collect([$readingBefore, ...$readingsInside, $readingAfter])->filter();
    }

    private function rebase(Meter $meter, Collection $readings): Collection
    {
        $baseline = (float) $meter->readings()->oldest('date')->value('value');
        $offset = $meter->previousMeter->retired_total_consumption ?? 0.0;

        if ($baseline === 0.0 && $offset === 0.0) {
            return $readings;
        }

        return $readings->map(function (Reading $reading) use ($baseline, $offset): Reading {
            $reading->value = $reading->value - $baseline + $offset;

            return $reading;
        });
    }
}
