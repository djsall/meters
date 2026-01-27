<?php

namespace App\Services;

use App\Models\Meter;
use Illuminate\Support\Carbon;

class RateService
{
    public function __construct(private readonly Meter $meter) {}

    public function getEstimatedRate(Carbon $start, Carbon $end): ?float
    {
        $earliest = $this->meter->readings()
            ->where('date', '<=', $start)
            ->latest('date')
            ->limit(1)
            ->first();

        $earliest ??= $this->meter->readings()
            ->where('date', '>=', $start)
            ->oldest('date')
            ->limit(1)
            ->first();

        $latest = $this->meter->readings()
            ->where('date', '>=', $end)
            ->oldest('date')
            ->limit(1)
            ->first();

        $latest ??= $this->meter->readings()
            ->where('date', '<=', $end)
            ->latest('date')
            ->limit(1)
            ->first();

        if (! $earliest || ! $latest || $earliest->id === $latest->id) {
            return null;
        }

        $days = $earliest?->date->diffInDays($latest?->date);
        $delta = $latest?->value - $earliest?->value;

        if ($days == 0 || $delta <= 0) {
            return null;
        }

        return $delta / $days;
    }
}
