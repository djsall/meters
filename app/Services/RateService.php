<?php

namespace App\Services;

use App\Models\Meter;
use Illuminate\Support\Carbon;

class RateService
{
    public function __construct(private readonly Meter $meter) {}

    public function getRateForRange(Carbon $start, Carbon $end): ?float
    {
        $earliest = $this->meter->readings()
            ->where('date', '>=', $start)
            ->oldest('date')
            ->limit(1)
            ->first();

        $earliest ??= $this->meter->readings()
            ->where('date', '<=', $start)
            ->latest('date')
            ->limit(1)
            ->first();

        if (! $earliest) {
            return null;
        }

        $latest = $this->meter->readings()
            ->where('date', '<=', $end)
            ->whereKeyNot($earliest->id)
            ->latest('date')
            ->limit(1)
            ->first();

        $latest ??= $this->meter->readings()
            ->where('date', '>=', $end)
            ->whereKeyNot($earliest->id)
            ->oldest('date')
            ->limit(1)
            ->first();

        if (! $latest) {
            return null;
        }

        $days = $earliest->date->diffInDays($latest->date);
        $delta = $latest->value - $earliest->value;

        return $delta > 0 ? $delta / $days : null;
    }
}
